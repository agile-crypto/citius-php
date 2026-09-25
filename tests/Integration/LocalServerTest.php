<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Integration;

use Citius\Client\Config\Config;
use Citius\Client\Config\Service;
use Citius\Client\Dial\Connector;
use Citius\Client\Tests\Support\TestPki;
use Citius\Grpc\Crypto\V1\CryptoServiceClient;
use Citius\Grpc\Crypto\V1\SignRequest;
use Citius\Grpc\Crypto\V1\VerifyRequest;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end calls from YAML configuration to a local gRPC server (tests/Support/test-server.php)
 * over plaintext, TLS and mutual TLS, with and without bearer tokens. Self-contained: no Citius server needed.
 *
 * The PHP gRPC server cannot require client certificates, so rejection of clients without one is
 * covered by {@see GoMtlsExampleTest}.
 */
class LocalServerTest extends TestCase {
	private const TLS_TOKEN = 'tls-token';
	private const MTLS_TOKEN = 'mtls-token';

	private static TestPki $pki;
	/** @var array<string, int> */
	private static array $ports = [];
	/** @var list<resource> */
	private static array $processes = [];

	#[\Override]
	public static function setUpBeforeClass(): void {
		self::$pki = new TestPki();
		file_put_contents(self::$pki->path('token'), self::MTLS_TOKEN . "\n");
		self::startServer('insecure', '');
		self::startServer('tls', self::TLS_TOKEN);
		self::startServer('mtls', self::MTLS_TOKEN);
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		foreach (self::$processes as $process) {
			proc_terminate($process);
			proc_close($process);
		}
		self::$pki->remove();
	}

	public function testPlaintext(): void {
		$client = $this->client('insecure', <<<'YAML'
			tls:
			  insecure: true
			YAML);

		$this->assertSignAndVerify($client);
	}

	public function testTlsWithStaticToken(): void {
		$client = $this->client('tls', <<<YAML
			tls:
			  ca_cert: "{$this->pki('ca_cert.pem')}"
			  server_name: "citius.test.example.com"
			  min_version: "1.2"
			auth:
			  enabled: true
			  static_token: "tls-token"
			YAML);

		$this->assertSignAndVerify($client);
	}

	public function testMutualTlsWithTokenSource(): void {
		$client = $this->client('mtls', <<<YAML
			tls:
			  ca_cert: "{$this->pki('ca_cert.pem')}"
			  client_cert: "{$this->pki('client_cert.pem')}"
			  client_key: "{$this->pki('client_key.pem')}"
			  server_name: "citius.test.example.com"
			auth:
			  enabled: true
			  token_source: "{$this->pki('token')}"
			YAML);

		$this->assertSignAndVerify($client);
	}

	public function testServiceOverrideReplacesDefaultAuth(): void {
		$yaml = <<<YAML
			default:
			  endpoint: "127.0.0.1:{$this->port('tls')}"
			  tls:
			    ca_cert: "{$this->pki('ca_cert.pem')}"
			    server_name: "citius.test.example.com"
			  auth:
			    enabled: true
			    static_token: "wrong-token"
			  timeout: "5s"
			crypto:
			  auth:
			    enabled: true
			    static_token: "tls-token"
			YAML;
		$connection = Connector::connect(Config::fromYaml($yaml)->resolve(Service::Crypto));

		$this->assertSignAndVerify($connection->createClient(CryptoServiceClient::class));
	}

	public function testTlsRejectsWrongServerName(): void {
		$client = $this->client('tls', <<<YAML
			tls:
			  ca_cert: "{$this->pki('ca_cert.pem')}"
			  server_name: "citius.other.example.org"
			auth:
			  enabled: true
			  static_token: "tls-token"
			YAML);

		$this->assertStatus(\Grpc\STATUS_UNAVAILABLE, $client);
	}

	public function testTlsRejectsUntrustedServer(): void {
		$client = $this->client('tls', <<<'YAML'
			tls:
			  server_name: "citius.test.example.com"
			auth:
			  enabled: true
			  static_token: "tls-token"
			YAML);

		$this->assertStatus(\Grpc\STATUS_UNAVAILABLE, $client);
	}

	public function testTlsClientCannotReachPlaintextServer(): void {
		$client = $this->client('insecure', <<<YAML
			tls:
			  ca_cert: "{$this->pki('ca_cert.pem')}"
			  server_name: "citius.test.example.com"
			YAML);

		$this->assertStatus(\Grpc\STATUS_UNAVAILABLE, $client);
	}

	public function testMissingTokenIsUnauthenticated(): void {
		$client = $this->client('tls', <<<YAML
			tls:
			  ca_cert: "{$this->pki('ca_cert.pem')}"
			  server_name: "citius.test.example.com"
			YAML);

		$this->assertStatus(\Grpc\STATUS_UNAUTHENTICATED, $client, 'missing authorization header');
	}

	public function testWrongTokenIsUnauthenticated(): void {
		$client = $this->client('tls', <<<YAML
			tls:
			  ca_cert: "{$this->pki('ca_cert.pem')}"
			  server_name: "citius.test.example.com"
			auth:
			  enabled: true
			  static_token: "wrong-token"
			YAML);

		$this->assertStatus(\Grpc\STATUS_UNAUTHENTICATED, $client, 'invalid token');
	}

	public function testConfiguredTimeoutApplies(): void {
		$client = $this->client('insecure', <<<'YAML'
			tls:
			  insecure: true
			YAML, '500ms');

		[, $status] = $client->Sign($this->signRequest('slow'))->wait();

		$this->assertSame(\Grpc\STATUS_DEADLINE_EXCEEDED, $status->code);
	}

	public function testCallTimeoutOverridesConfiguredTimeout(): void {
		$client = $this->client('insecure', <<<'YAML'
			tls:
			  insecure: true
			YAML, '500ms');

		[$response, $status] = $client->Sign($this->signRequest('slow'), [], ['timeout' => 5_000_000])->wait();

		$this->assertSame(\Grpc\STATUS_OK, $status->code, $status->details);
		$this->assertSame('sig:slow', $response->getSignature());
	}

	/**
	 * Client for the server $mode, configured by a "default" section with the given settings.
	 */
	private function client(string $mode, string $settings, string $timeout = '5s'): CryptoServiceClient {
		$indented = preg_replace('/^/m', '  ', $settings);
		$yaml = "default:\n  endpoint: \"127.0.0.1:{$this->port($mode)}\"\n  timeout: \"{$timeout}\"\n{$indented}\ncrypto: {}\n";
		$file = $this->pki('config-' . $this->name() . '.yaml');
		file_put_contents($file, $yaml);
		return Connector::connectFromFile($file, Service::Crypto)->createClient(CryptoServiceClient::class);
	}

	private function assertSignAndVerify(CryptoServiceClient $client): void {
		[$signed, $status] = $client->Sign($this->signRequest('example plaintext'))->wait();
		$this->assertSame(\Grpc\STATUS_OK, $status->code, $status->details);
		$this->assertSame('sig:example plaintext', $signed->getSignature());

		$verify = (new VerifyRequest())->setKeyName('test-key-name')->setInput('example plaintext')->setSignature($signed->getSignature());
		[$verified, $status] = $client->Verify($verify)->wait();
		$this->assertSame(\Grpc\STATUS_OK, $status->code, $status->details);
		$this->assertTrue($verified->getValid());
	}

	private function assertStatus(int $code, CryptoServiceClient $client, string $details = ''): void {
		[$response, $status] = $client->Sign($this->signRequest('example plaintext'))->wait();

		$this->assertNull($response);
		$this->assertSame($code, $status->code, $status->details);
		if ($details !== '') {
			$this->assertSame($details, $status->details);
		}
	}

	private function signRequest(string $input): SignRequest {
		return (new SignRequest())->setKeyName('test-key-name')->setInput($input);
	}

	private function pki(string $file): string {
		return self::$pki->path($file);
	}

	private function port(string $mode): int {
		return self::$ports[$mode];
	}

	private static function startServer(string $mode, string $token): void {
		$socket = stream_socket_server('tcp://127.0.0.1:0');
		$port = (int)substr((string)stream_socket_get_name($socket, false), strrpos((string)stream_socket_get_name($socket, false), ':') + 1);
		fclose($socket);

		$command = [PHP_BINARY, dirname(__DIR__) . '/Support/test-server.php', $mode, (string)$port, self::$pki->dir, $token];
		$process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', self::$pki->path("server-$mode.log"), 'w']], $pipes, null, ['XDEBUG_MODE' => 'off']);
		self::$processes[] = $process;
		self::$ports[$mode] = $port;

		$deadline = microtime(true) + 10;
		while (@fsockopen('127.0.0.1', $port) === false) {
			if (microtime(true) > $deadline || !proc_get_status($process)['running']) {
				self::fail(sprintf('test server "%s" did not start: %s', $mode, self::$pki->read("server-$mode.log")));
			}
			usleep(50_000);
		}
	}
}
