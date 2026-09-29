<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Integration;

use Citius\Client\Common\Scope;
use Citius\Client\Config\Config;
use Citius\Client\Exception\PolicyNotFoundException;
use Citius\Client\Policy\PolicyServiceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Local policy mode end to end: client configuration → factory → policy file → results matching the file's content
 * for every policy and every scope.
 */
class LocalPolicyTest extends TestCase {
	private const POLICIES = 'tests/fixtures/policy/policies.yaml';

	public function testResultsMatchPolicyFile(): void {
		$service = PolicyServiceFactory::fromConfig(Config::fromYaml(
			"crypto_policy:\n  mode: local\n  policies: \"" . self::POLICIES . "\"\n",
		));

		$document = Yaml::parseFile(self::POLICIES);
		$this->assertNotEmpty($document['policies']);
		foreach ($document['policies'] as $policy) {
			foreach (Scope::cases() as $scope) {
				$expected = $policy['scopes'][$scope->value] ?? [];
				$result = $service->listAllowedAlgorithms($policy['name'], $scope);

				$context = $policy['name'] . ' / ' . $scope->value;
				$this->assertSame($expected['allowed_algorithms'] ?? [], $result->allowedTemplates, $context);
				$this->assertSame($expected['legacy_algorithms'] ?? [], $result->legacyTemplates, $context);
			}
		}
	}

	public function testUnknownPolicy(): void {
		$service = PolicyServiceFactory::fromConfig(Config::fromYaml(
			"crypto_policy:\n  mode: local\n  policies: \"" . self::POLICIES . "\"\n",
		));

		$this->expectException(PolicyNotFoundException::class);
		$service->listAllowedAlgorithms('not-in-file', Scope::SignatureStandard);
	}
}
