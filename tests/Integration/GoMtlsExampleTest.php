<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Integration;

use Citius\Client\Config\AuthConfig;
use Citius\Client\Config\Config;
use Citius\Client\Config\Service;
use Citius\Client\Config\ServiceConfig;
use Citius\Client\Config\TlsConfig;
use Citius\Client\Dial\Connector;
use Citius\Grpc\Crypto\V1\CryptoServiceClient;
use Citius\Grpc\Crypto\V1\SignRequest;
use PHPUnit\Framework\TestCase;

/**
 * Interoperability with the Go SDK mutual TLS example (citius-go-sdk/examples/mtls), whose server requires
 * a client certificate and the static token "crypto-test-token".
 *
 * The client certificates and configuration of the example are in resources/. The server address defaults to
 * the one of the example configuration, "localhost:50051", and can be changed with CITIUS_MTLS_EXAMPLE_ADDR,
 * e.g. "host.containers.internal:50051" from a container. Tests are skipped when the server is not reachable.
 */
class GoMtlsExampleTest extends TestCase {
	private ServiceConfig $config;

	#[\Override]
	protected function setUp(): void {
		// The example configuration uses paths relative to the parent of resources/, like the Go client
		$cwd = (string)getcwd();
		chdir(__DIR__);
		try {
			$example = Config::load('resources/mtls-config.yaml')->resolve(Service::Crypto);
		} finally {
			chdir($cwd);
		}
		$this->config = new ServiceConfig(
			(string)(getenv('CITIUS_MTLS_EXAMPLE_ADDR') ?: $example->endpoint),
			$this->absolute($example->tls),
			$example->auth,
			$example->timeout,
		);

		[$host, $port] = [parse_url('//' . $this->config->endpoint, PHP_URL_HOST), parse_url('//' . $this->config->endpoint, PHP_URL_PORT)];
		$socket = @fsockopen((string)$host, (int)$port, $errno, $error, 1.0);
		if ($socket === false) {
			$this->markTestSkipped(sprintf('Go mTLS example server not reachable at %s (%s); set CITIUS_MTLS_EXAMPLE_ADDR', $this->config->endpoint, $error));
		}
		fclose($socket);
	}

	public function testExampleConfiguration(): void {
		$this->assertSame('crypto.test.example.com', $this->config->tls->serverName);
		$this->assertSame(TlsConfig::MIN_VERSION_1_2, $this->config->tls->minVersion);
		$this->assertSame('crypto-test-token', $this->config->auth->staticToken);
		$this->assertSame('10s', $this->config->timeout);

		$this->assertSame(\Grpc\STATUS_OK, $this->sign($this->config));
	}

	public function testClientWithoutCertificateIsRejected(): void {
		$tls = $this->config->tls;
		$withoutClientCert = new TlsConfig(caCert: $tls->caCert, serverName: $tls->serverName, minVersion: $tls->minVersion);

		$this->assertSame(\Grpc\STATUS_UNAVAILABLE, $this->sign($this->with(tls: $withoutClientCert)));
	}

	public function testWrongTokenIsUnauthenticated(): void {
		$this->assertSame(\Grpc\STATUS_UNAUTHENTICATED, $this->sign($this->with(auth: new AuthConfig(enabled: true, staticToken: 'wrong-token'))));
	}

	public function testMissingTokenIsUnauthenticated(): void {
		$this->assertSame(\Grpc\STATUS_UNAUTHENTICATED, $this->sign($this->with(auth: new AuthConfig())));
	}

	private function sign(ServiceConfig $config): int {
		$client = Connector::connect($config)->createClient(CryptoServiceClient::class);
		[, $status] = $client->Sign((new SignRequest())->setKeyName('test-key-name')->setInput('example plaintext'))->wait();
		return $status->code;
	}

	private function with(?TlsConfig $tls = null, ?AuthConfig $auth = null): ServiceConfig {
		return new ServiceConfig($this->config->endpoint, $tls ?? $this->config->tls, $auth ?? $this->config->auth, $this->config->timeout);
	}

	private function absolute(TlsConfig $tls): TlsConfig {
		$path = static fn (string $file): string => $file === '' ? '' : __DIR__ . '/' . $file;
		return new TlsConfig($path($tls->caCert), $path($tls->clientCert), $path($tls->clientKey), $tls->insecure, $tls->serverName, $tls->minVersion);
	}
}
