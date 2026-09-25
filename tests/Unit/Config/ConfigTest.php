<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Config;

use Citius\Client\Auth\TokenSource;
use Citius\Client\Config\AuthConfig;
use Citius\Client\Config\Config;
use Citius\Client\Config\Service;
use Citius\Client\Config\ServiceConfig;
use Citius\Client\Config\TlsConfig;
use Citius\Client\Exception\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Port of client/config/config_test.go from the Go SDK, on the same fixtures.
 */
class ConfigTest extends TestCase {
	private const FIXTURES = 'tests/fixtures/config/';

	public function testLoadFullConfig(): void {
		$config = Config::load(self::FIXTURES . 'full.yaml');

		$default = $config->default;
		$this->assertSame('default.example.com:443', $default->endpoint);
		$this->assertSame('/etc/certs/ca.pem', $default->tls->caCert);
		$this->assertSame('/etc/certs/client.pem', $default->tls->clientCert);
		$this->assertSame('/etc/certs/client.key', $default->tls->clientKey);
		$this->assertSame('default.example.com', $default->tls->serverName);
		$this->assertSame(TlsConfig::MIN_VERSION_1_3, $default->tls->minVersion);
		$this->assertFalse($default->tls->insecure);
		$this->assertTrue($default->auth->enabled);
		$this->assertSame('default-token', $default->auth->staticToken);
		$this->assertSame('30s', $default->timeout);

		foreach (Service::cases() as $service) {
			$this->assertNotNull($config->overrides[$service->value] ?? null, $service->value);
		}
	}

	public function testLoadPartialConfig(): void {
		$config = Config::load(self::FIXTURES . 'partial.yaml');

		$this->assertNotNull($config->overrides[Service::KeyManagement->value] ?? null);
		$this->assertNotNull($config->overrides[Service::Crypto->value] ?? null);
		foreach ([Service::CryptoPolicy, Service::Discovery, Service::KeyEstablishment, Service::Provider, Service::StreamingCrypto] as $service) {
			$this->assertArrayNotHasKey($service->value, $config->overrides);
		}
	}

	public function testLoadMissingFileFails(): void {
		$this->expectException(ConfigException::class);
		Config::load(self::FIXTURES . 'nonexistent.yaml');
	}

	public function testLoadUnknownServiceFails(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('unknown service name "invalid_service"');
		Config::load(self::FIXTURES . 'invalid_service_name.yaml');
	}

	/**
	 * @return array<string, array{Service, \Closure(ServiceConfig, self): void}>
	 */
	public static function fullConfigResolutions(): array {
		return [
			'empty override inherits all defaults' => [Service::KeyManagement, static function (ServiceConfig $svc, self $t): void {
				$t->assertSame('default.example.com:443', $svc->endpoint);
				$t->assertSame('/etc/certs/ca.pem', $svc->tls->caCert);
				$t->assertTrue($svc->auth->enabled);
				$t->assertSame('default-token', $svc->auth->staticToken);
				$t->assertSame('30s', $svc->timeout);
			}],
			'endpoint override inherits tls and auth' => [Service::Crypto, static function (ServiceConfig $svc, self $t): void {
				$t->assertSame('crypto.example.com:443', $svc->endpoint);
				$t->assertSame('/etc/certs/ca.pem', $svc->tls->caCert);
				$t->assertTrue($svc->auth->enabled);
				$t->assertSame('default-token', $svc->auth->staticToken);
			}],
			'auth override replaces auth only' => [Service::CryptoPolicy, static function (ServiceConfig $svc, self $t): void {
				$t->assertSame('default.example.com:443', $svc->endpoint);
				$t->assertSame('/etc/certs/ca.pem', $svc->tls->caCert);
				$t->assertFalse($svc->auth->enabled);
				$t->assertSame('', $svc->auth->staticToken);
			}],
			'full override replaces all fields' => [Service::Discovery, static function (ServiceConfig $svc, self $t): void {
				$t->assertSame('discovery.example.com:443', $svc->endpoint);
				$t->assertTrue($svc->tls->insecure);
				$t->assertSame('', $svc->tls->caCert);
				$t->assertFalse($svc->auth->enabled);
				$t->assertSame('5s', $svc->timeout);
			}],
			'timeout override only' => [Service::KeyEstablishment, static function (ServiceConfig $svc, self $t): void {
				$t->assertSame('default.example.com:443', $svc->endpoint);
				$t->assertSame('60s', $svc->timeout);
				$t->assertTrue($svc->auth->enabled);
			}],
			'streaming crypto endpoint override' => [Service::StreamingCrypto, static function (ServiceConfig $svc, self $t): void {
				$t->assertSame('streaming.example.com:443', $svc->endpoint);
				$t->assertTrue($svc->auth->enabled);
				$t->assertSame('30s', $svc->timeout);
			}],
		];
	}

	/**
	 * @param \Closure(ServiceConfig, self): void $check
	 */
	#[DataProvider('fullConfigResolutions')]
	public function testResolveFullConfig(Service $service, \Closure $check): void {
		$check(Config::load(self::FIXTURES . 'full.yaml')->resolve($service), $this);
		$check(Config::resolveFromFile(self::FIXTURES . 'full.yaml', $service), $this);
	}

	public function testResolvePartialConfig(): void {
		$config = Config::load(self::FIXTURES . 'partial.yaml');

		$keyManagement = $config->resolve(Service::KeyManagement);
		$this->assertSame('default.example.com:443', $keyManagement->endpoint);
		$this->assertSame('/etc/certs/ca.pem', $keyManagement->tls->caCert);
		$this->assertTrue($keyManagement->auth->enabled);

		$crypto = $config->resolve(Service::Crypto);
		$this->assertSame('crypto.example.com:443', $crypto->endpoint);
		$this->assertTrue($crypto->auth->enabled);
		$this->assertSame('my-token', $crypto->auth->staticToken);
	}

	public function testResolveUnconfiguredServiceFails(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('service "discovery" is not configured');
		Config::load(self::FIXTURES . 'partial.yaml')->resolve(Service::Discovery);
	}

	public function testResolveNullServiceSectionFails(): void {
		$config = Config::fromYaml("default:\n  endpoint: \"x:1\"\ncrypto:\n");

		$this->expectException(ConfigException::class);
		$config->resolve(Service::Crypto);
	}

	public function testResolveFromMissingFileFails(): void {
		$this->expectException(ConfigException::class);
		Config::resolveFromFile(self::FIXTURES . 'nonexistent.yaml', Service::Crypto);
	}

	public function testTokenSourceProvider(): void {
		$config = Config::load(self::FIXTURES . 'token_source.yaml');
		foreach ([Service::KeyManagement, Service::Crypto] as $service) {
			$svc = $config->resolve($service);
			$this->assertSame('default.example.com:443', $svc->endpoint);
			$this->assertSame('/etc/certs/ca.pem', $svc->tls->caCert);
			$this->assertTrue($svc->auth->enabled);
			$this->assertSame('', $svc->auth->staticToken);
			$this->assertSame(self::FIXTURES . 'example.token', $svc->auth->tokenSource);

			$provider = $svc->resolveTokenProvider();
			$this->assertInstanceOf(TokenSource::class, $provider);
			$this->assertSame('example-token', $provider->token());
		}
	}

	public function testStaticTokenAndTokenSourceFails(): void {
		$svc = Config::load(self::FIXTURES . 'multiple_token.yaml')->resolve(Service::Crypto);

		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('multiple token providers');
		$svc->resolveTokenProvider();
	}

	public function testAuthEnabledWithoutTokenFails(): void {
		$svc = new ServiceConfig('localhost:5000', new TlsConfig(insecure: true), new AuthConfig(enabled: true));

		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('no token is configured');
		$svc->resolveTokenProvider();
	}

	public function testAuthEnabledWithMissingTokenSourceFails(): void {
		$svc = new ServiceConfig('localhost:5000', new TlsConfig(insecure: true), new AuthConfig(enabled: true, tokenSource: 'invalid_path.token'));

		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('token source file does not exist');
		$svc->resolveTokenProvider();
	}

	public function testAuthDisabledHasNoTokenProvider(): void {
		$svc = new ServiceConfig('localhost:5000', auth: new AuthConfig(enabled: false, staticToken: 'ignored'));

		$this->assertNull($svc->resolveTokenProvider());
	}

	public function testServiceNamesMatchGoSdk(): void {
		$this->assertSame(
			['key_management', 'crypto', 'crypto_policy', 'discovery', 'key_establishment', 'provider', 'streaming_crypto'],
			array_map(static fn (Service $s): string => $s->value, Service::cases()),
		);
	}

	public function testEmptyDocumentHasNoServices(): void {
		$config = Config::fromYaml('');

		$this->assertSame([], $config->overrides);
		$this->assertSame('', $config->default->endpoint);
	}

	public function testFromArray(): void {
		$config = Config::fromArray([
			'default' => ['endpoint' => 'citius:50051', 'tls' => ['insecure' => true]],
			'crypto' => ['timeout' => '5s'],
		]);

		$svc = $config->resolve(Service::Crypto);
		$this->assertSame('citius:50051', $svc->endpoint);
		$this->assertTrue($svc->tls->insecure);
		$this->assertSame('5s', $svc->timeout);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidDocuments(): array {
		return [
			'invalid YAML' => ["default: [unclosed\n", 'invalid YAML'],
			'scalar document' => ['just a string', 'must be a mapping'],
			'string for boolean' => ["default:\n  tls:\n    insecure: \"yes\"\n", 'default.tls.insecure: expected a boolean, got string'],
			'mapping for string' => ["default:\n  endpoint: {host: x}\n", 'default.endpoint: expected a string, got array'],
			'scalar for section' => ["crypto: 42\n", 'crypto: expected a mapping, got int'],
		];
	}

	#[DataProvider('invalidDocuments')]
	public function testInvalidDocumentFails(string $yaml, string $message): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage($message);
		Config::fromYaml($yaml);
	}
}
