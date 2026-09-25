<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Dial;

use Citius\Client\Config\AuthConfig;
use Citius\Client\Config\Service;
use Citius\Client\Config\ServiceConfig;
use Citius\Client\Config\TlsConfig;
use Citius\Client\Dial\Connector;
use Citius\Client\Dial\TimeoutInterceptor;
use Citius\Client\Exception\ConfigException;
use Citius\Client\Tests\Support\TestPki;
use Citius\Grpc\Crypto\V1\CryptoServiceClient;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use Grpc\Internal\InterceptorChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Connection building only: no call reaches a server. See tests/Integration for calls over TLS, mTLS and auth.
 */
class ConnectorTest extends TestCase {
	private static TestPki $pki;

	#[\Override]
	public static function setUpBeforeClass(): void {
		self::$pki = new TestPki();
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		self::$pki->remove();
	}

	public function testInsecureConnection(): void {
		$connection = Connector::connect(new ServiceConfig('localhost:50051', new TlsConfig(insecure: true)));

		$this->assertSame('localhost:50051', $connection->hostname);
		$this->assertArrayHasKey('credentials', $connection->opts);
		$this->assertNull($connection->opts['credentials']);
		$this->assertArrayNotHasKey('grpc.ssl_target_name_override', $connection->opts);
		$this->assertInstanceOf(Channel::class, $connection->channel);
	}

	public function testTlsConnectionOverridesTargetName(): void {
		$connection = Connector::connect(new ServiceConfig('localhost:50051', $this->tls()));

		$this->assertInstanceOf(ChannelCredentials::class, $connection->opts['credentials']);
		$this->assertSame(TestPki::SERVER_NAME, $connection->opts['grpc.ssl_target_name_override']);
		$this->assertInstanceOf(Channel::class, $connection->channel);
	}

	public function testTlsWithSystemRoots(): void {
		$connection = Connector::connect(new ServiceConfig('citius.example.com:443', new TlsConfig(serverName: 'citius.example.com')));

		$this->assertInstanceOf(Channel::class, $connection->channel);
	}

	public function testMutualTls(): void {
		$tls = $this->tls(clientCert: self::$pki->path('client_cert.pem'), clientKey: self::$pki->path('client_key.pem'));

		$this->assertInstanceOf(Channel::class, Connector::connect(new ServiceConfig('localhost:50051', $tls))->channel);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function acceptedMinVersions(): array {
		return ['unspecified' => [''], 'TLS 1.2' => ['1.2']];
	}

	#[DataProvider('acceptedMinVersions')]
	public function testAcceptedMinVersion(string $minVersion): void {
		$this->assertInstanceOf(Channel::class, Connector::connect(new ServiceConfig('localhost:50051', $this->tls(minVersion: $minVersion)))->channel);
	}

	public function testAuthAndTimeoutAddInterceptors(): void {
		$connection = Connector::connect(new ServiceConfig(
			'localhost:50051',
			new TlsConfig(insecure: true),
			new AuthConfig(enabled: true, staticToken: 'secret'),
			'10s',
		));

		$this->assertInstanceOf(InterceptorChannel::class, $connection->channel);
	}

	public function testZeroTimeoutAddsNoInterceptor(): void {
		$connection = Connector::connect(new ServiceConfig('localhost:50051', new TlsConfig(insecure: true), timeout: '0s'));

		$this->assertInstanceOf(Channel::class, $connection->channel);
	}

	public function testCreateClient(): void {
		$connection = Connector::connect(new ServiceConfig('localhost:50051', new TlsConfig(insecure: true), new AuthConfig(enabled: true, staticToken: 't')));

		$this->assertInstanceOf(CryptoServiceClient::class, $connection->createClient(CryptoServiceClient::class));
		$this->assertInstanceOf(CryptoServiceClient::class, new CryptoServiceClient($connection->hostname, $connection->opts, $connection->channel));
	}

	public function testConnectFromFile(): void {
		$file = self::$pki->path('config.yaml');
		file_put_contents($file, "default:\n  endpoint: \"localhost:50051\"\n  tls:\n    insecure: true\ncrypto: {}\n");

		$this->assertSame('localhost:50051', Connector::connectFromFile($file, Service::Crypto)->hostname);
	}

	/**
	 * @return array<string, array{\Closure(): ServiceConfig, string}>
	 */
	public static function invalidConfigs(): array {
		$pki = static fn (string $file): string => self::$pki->path($file);
		$tls = static fn (mixed ...$args): TlsConfig => new TlsConfig(...array_merge(['caCert' => $pki('ca_cert.pem'), 'serverName' => TestPki::SERVER_NAME], $args));
		return [
			'missing endpoint' => [static fn () => new ServiceConfig('', new TlsConfig(insecure: true)), 'endpoint is required'],
			'TLS without server name' => [static fn () => new ServiceConfig('h:1', $tls(serverName: '')), 'TLS server name is required'],
			'TLS 1.3 minimum' => [static fn () => new ServiceConfig('h:1', $tls(minVersion: '1.3')), 'cannot be enforced'],
			'unknown TLS version' => [static fn () => new ServiceConfig('h:1', $tls(minVersion: '1.1')), 'unsupported tls.min_version "1.1"'],
			'missing CA file' => [static fn () => new ServiceConfig('h:1', $tls(caCert: $pki('missing.pem'))), 'read CA cert'],
			'CA file without certificate' => [static fn () => new ServiceConfig('h:1', $tls(caCert: $pki('server_key.pem'))), 'no valid certificates'],
			'CA file with invalid certificate' => [static fn () => new ServiceConfig('h:1', $tls(caCert: $pki('not_a_cert.pem'))), 'no valid certificates'],
			'client cert without key' => [static fn () => new ServiceConfig('h:1', $tls(clientCert: $pki('client_cert.pem'))), 'requires both'],
			'client key without cert' => [static fn () => new ServiceConfig('h:1', $tls(clientKey: $pki('client_key.pem'))), 'requires both'],
			'missing client key file' => [static fn () => new ServiceConfig('h:1', $tls(clientCert: $pki('client_cert.pem'), clientKey: $pki('missing.pem'))), 'read client key'],
			'client key not matching cert' => [static fn () => new ServiceConfig('h:1', $tls(clientCert: $pki('client_cert.pem'), clientKey: $pki('other_key.pem'))), 'does not match'],
			'client cert is a key' => [static fn () => new ServiceConfig('h:1', $tls(clientCert: $pki('client_key.pem'), clientKey: $pki('client_key.pem'))), 'not valid unencrypted PEM'],
			'auth without token' => [static fn () => new ServiceConfig('h:1', new TlsConfig(insecure: true), new AuthConfig(enabled: true)), 'no token is configured'],
			'invalid timeout' => [static fn () => new ServiceConfig('h:1', new TlsConfig(insecure: true), timeout: '30'), 'invalid duration'],
			'negative timeout' => [static fn () => new ServiceConfig('h:1', new TlsConfig(insecure: true), timeout: '-1s'), 'must not be negative'],
		];
	}

	/**
	 * @param \Closure(): ServiceConfig $config
	 */
	#[DataProvider('invalidConfigs')]
	public function testInvalidConfigFails(\Closure $config, string $message): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage($message);
		Connector::connect($config());
	}

	public function testTimeoutInterceptorOnlySetsMissingTimeout(): void {
		$interceptor = new TimeoutInterceptor(5_000_000);
		$captureOptions = static fn (string $method, mixed $argument, mixed $deserialize, array $metadata, array $options): array => $options;

		$this->assertSame(['timeout' => 5_000_000], $interceptor->interceptUnaryUnary('/m', 'arg', null, $captureOptions));
		$this->assertSame(['timeout' => 1_000], $interceptor->interceptUnaryUnary('/m', 'arg', null, $captureOptions, [], ['timeout' => 1_000]));
	}

	private function tls(string $clientCert = '', string $clientKey = '', string $minVersion = ''): TlsConfig {
		return new TlsConfig(
			caCert: self::$pki->path('ca_cert.pem'),
			clientCert: $clientCert,
			clientKey: $clientKey,
			serverName: TestPki::SERVER_NAME,
			minVersion: $minVersion,
		);
	}
}
