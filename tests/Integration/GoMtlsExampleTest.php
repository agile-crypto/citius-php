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
 * Requires the example server to be running and:
 *   CITIUS_MTLS_EXAMPLE_RESOURCES  copy of examples/mtls/resources (certificates and mtls-config.yaml)
 *   CITIUS_MTLS_EXAMPLE_ADDR       server address, default "localhost:50051"
 */
class GoMtlsExampleTest extends TestCase {
	private string $resources;
	private ServiceConfig $config;

	#[\Override]
	protected function setUp(): void {
		$resources = (string)getenv('CITIUS_MTLS_EXAMPLE_RESOURCES');
		if ($resources === '') {
			$this->markTestSkipped('CITIUS_MTLS_EXAMPLE_RESOURCES is not set');
		}
		$this->resources = $resources;

		// The example configuration uses paths relative to the parent of resources/, like the Go client
		$cwd = (string)getcwd();
		chdir(dirname($resources));
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
		$path = fn (string $file): string => $file === '' ? '' : dirname($this->resources) . '/' . $file;
		return new TlsConfig($path($tls->caCert), $path($tls->clientCert), $path($tls->clientKey), $tls->insecure, $tls->serverName, $tls->minVersion);
	}
}
