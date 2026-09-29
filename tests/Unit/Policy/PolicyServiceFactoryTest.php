<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Policy;

use Citius\Client\Config\Config;
use Citius\Client\Exception\ConfigException;
use Citius\Client\Policy\LocalPolicyService;
use Citius\Client\Policy\PolicyServiceFactory;
use Citius\Client\Policy\RemotePolicyAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PolicyServiceFactoryTest extends TestCase {
	public function testLocalMode(): void {
		$config = Config::fromYaml("crypto_policy:\n  mode: local\n  policies: tests/fixtures/policy/policies.yaml\n");

		$this->assertInstanceOf(LocalPolicyService::class, PolicyServiceFactory::fromConfig($config));
	}

	public function testLocalModeNeedsNoConnectionSettings(): void {
		$config = Config::fromYaml("default:\n  tls:\n    min_version: \"1.3\"\ncrypto_policy:\n  mode: local\n  policies: tests/fixtures/policy/policies.yaml\n");

		$this->assertInstanceOf(LocalPolicyService::class, PolicyServiceFactory::fromConfig($config));
	}

	public function testRemoteMode(): void {
		$config = Config::fromYaml("default:\n  endpoint: \"localhost:50051\"\n  tls:\n    insecure: true\ncrypto_policy:\n  mode: remote\n");

		$this->assertInstanceOf(RemotePolicyAdapter::class, PolicyServiceFactory::fromConfig($config));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidConfigs(): array {
		return [
			'no crypto_policy section' => ["crypto: {}\n", 'service "crypto_policy" is not configured'],
			'mode missing' => ["crypto_policy:\n  policies: tests/fixtures/policy/policies.yaml\n", 'crypto_policy.mode is required'],
			'unknown mode' => ["crypto_policy:\n  mode: hybrid\n", 'unknown mode "hybrid"'],
			'local without policies' => ["crypto_policy:\n  mode: local\n", 'crypto_policy.policies is required in local mode'],
			'local with missing file' => ["crypto_policy:\n  mode: local\n  policies: tests/fixtures/policy/missing.yaml\n", 'cannot read policy file'],
			'remote without endpoint' => ["crypto_policy:\n  mode: remote\n", 'endpoint is required'],
		];
	}

	#[DataProvider('invalidConfigs')]
	public function testInvalidConfigFails(string $yaml, string $message): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage($message);
		PolicyServiceFactory::fromConfig(Config::fromYaml($yaml));
	}
}
