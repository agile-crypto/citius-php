<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Policy;

use Citius\Client\Common\Scope;
use Citius\Client\Exception\ConfigException;
use Citius\Client\Exception\PolicyNotFoundException;
use Citius\Client\Policy\AllowedAlgorithmsResult;
use Citius\Client\Policy\LocalPolicyService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalPolicyServiceTest extends TestCase {
	private const FIXTURE = 'tests/fixtures/policy/policies.yaml';

	public function testFromFileKeepsOrderAndLegacyTemplates(): void {
		$result = LocalPolicyService::fromFile(self::FIXTURE)->listAllowedAlgorithms('nextcloud-webauthn', Scope::SignatureStandard);

		$this->assertSame(['ML-DSA-44', 'ECDSA-P-256-SHA-256'], $result->allowedTemplates);
		$this->assertSame(['RSA-PKCS1-1.5-SHA-256-2048'], $result->legacyTemplates);
	}

	public function testScopesAreIndependent(): void {
		$service = LocalPolicyService::fromFile(self::FIXTURE);

		$prehashed = $service->listAllowedAlgorithms('nextcloud-webauthn', Scope::SignaturePrehashed);
		$this->assertSame(['ECDSA-P-256-SHA-256'], $prehashed->allowedTemplates);
		$this->assertSame([], $prehashed->legacyTemplates);

		$classical = $service->listAllowedAlgorithms('classical-only', Scope::SignatureStandard);
		$this->assertSame(['ECDSA-P-256-SHA-256', 'RSA-PKCS1-1.5-SHA-256-2048'], $classical->allowedTemplates);
	}

	public function testMissingScopeHasNoAlgorithms(): void {
		$service = LocalPolicyService::fromFile(self::FIXTURE);

		foreach ([['nextcloud-webauthn', Scope::AeadStandard], ['empty', Scope::SignatureStandard]] as [$policy, $scope]) {
			$result = $service->listAllowedAlgorithms($policy, $scope);
			$this->assertSame([], $result->allowedTemplates);
			$this->assertSame([], $result->legacyTemplates);
		}
	}

	public function testUnknownPolicyFails(): void {
		$this->expectException(PolicyNotFoundException::class);
		$this->expectExceptionMessage('policy "missing" not found');
		LocalPolicyService::fromFile(self::FIXTURE)->listAllowedAlgorithms('missing', Scope::SignatureStandard);
	}

	public function testFromLists(): void {
		$service = new LocalPolicyService([
			'p' => [Scope::SignatureStandard->value => new AllowedAlgorithmsResult(['ML-DSA-44'], ['ECDSA-P-256-SHA-256'])],
		]);

		$result = $service->listAllowedAlgorithms('p', Scope::SignatureStandard);
		$this->assertSame(['ML-DSA-44'], $result->allowedTemplates);
		$this->assertSame(['ECDSA-P-256-SHA-256'], $result->legacyTemplates);
	}

	public function testMissingFileFails(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('cannot read policy file');
		LocalPolicyService::fromFile('tests/fixtures/policy/missing.yaml');
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidDocuments(): array {
		return [
			'invalid YAML' => ["policies: [unclosed\n", 'invalid YAML'],
			'no policies key' => ["other: 1\n", '"policies" must be a list'],
			'policies not a list' => ["policies:\n  p: {}\n", '"policies" must be a list'],
			'missing name' => ["policies:\n  - scopes: {}\n", 'policies[0].name must be a non-empty string'],
			'duplicate name' => ["policies:\n  - name: p\n  - name: p\n", 'duplicate policy name "p"'],
			'scopes as list' => ["policies:\n  - name: p\n    scopes: [signature_standard]\n", 'scopes must be a mapping'],
			'unknown scope' => ["policies:\n  - name: p\n    scopes:\n      signature_fast: {}\n", 'unknown scope "signature_fast"'],
			'templates not a list' => ["policies:\n  - name: p\n    scopes:\n      signature_standard:\n        allowed_algorithms: ML-DSA-44\n", 'allowed_algorithms must be a list'],
			'non-string template' => ["policies:\n  - name: p\n    scopes:\n      signature_standard:\n        allowed_algorithms: [42]\n", 'must contain only strings, got int'],
			'empty template' => ["policies:\n  - name: p\n    scopes:\n      signature_standard:\n        allowed_algorithms: ['']\n", 'allowed templates must be non-empty strings'],
			'duplicate template' => ["policies:\n  - name: p\n    scopes:\n      signature_standard:\n        allowed_algorithms: [ML-DSA-44, ML-DSA-44]\n", 'duplicate allowed template'],
			'allowed and legacy' => ["policies:\n  - name: p\n    scopes:\n      signature_standard:\n        allowed_algorithms: [ML-DSA-44]\n        legacy_algorithms: [ML-DSA-44]\n", 'both allowed and legacy: ML-DSA-44'],
		];
	}

	#[DataProvider('invalidDocuments')]
	public function testInvalidDocumentFails(string $yaml, string $message): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage($message);
		LocalPolicyService::fromYaml($yaml);
	}

	public function testInvalidListsFail(): void {
		$this->expectException(ConfigException::class);
		$this->expectExceptionMessage('unknown scope "signature"');
		new LocalPolicyService(['p' => ['signature' => new AllowedAlgorithmsResult(['ML-DSA-44'])]]);
	}
}
