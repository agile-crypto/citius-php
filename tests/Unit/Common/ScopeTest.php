<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Common;

use Citius\Client\Common\Scope;
use Citius\Grpc\Crypto\V1\AeadScope;
use Citius\Grpc\Crypto\V1\AsymmetricEncryptionScope;
use Citius\Grpc\Crypto\V1\KdfScope;
use Citius\Grpc\Crypto\V1\KeyAgreementScope;
use Citius\Grpc\Crypto\V1\ScopeSpecification;
use Citius\Grpc\Crypto\V1\SignatureScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase {
	/**
	 * @return array<string, array{Scope, string, int}>
	 */
	public static function mappings(): array {
		return [
			'signature standard' => [Scope::SignatureStandard, 'signature', SignatureScope::SIGNATURE_SCOPE_STANDARD],
			'signature prehashed with context' => [Scope::SignaturePrehashedWithContext, 'signature', SignatureScope::SIGNATURE_SCOPE_PREHASHED_WITH_CONTEXT],
			'aead deterministic' => [Scope::AeadDeterministic, 'aead', AeadScope::AEAD_SCOPE_DETERMINISTIC],
			'key agreement hybrid' => [Scope::KeyAgreementHybrid, 'key_agreement', KeyAgreementScope::KEY_AGREEMENT_SCOPE_HYBRID],
			'kdf agreement' => [Scope::KdfAgreement, 'kdf', KdfScope::KDF_SCOPE_AGREEMENT],
			'asymmetric encryption raw' => [Scope::AsymmetricEncryptionRaw, 'asymmetric_encryption', AsymmetricEncryptionScope::ASYMMETRIC_ENCRYPTION_SCOPE_RAW],
		];
	}

	#[DataProvider('mappings')]
	public function testToScopeSpecification(Scope $scope, string $field, int $protoScope): void {
		$spec = $scope->toScopeSpecification();

		$this->assertSame($field, $spec->getScopeSpec());
		$this->assertSame($protoScope, $spec->{'get' . str_replace('_', '', ucwords($field, '_'))}()->getScope());
	}

	/**
	 * Every value of every proto <Family>Scope enum has exactly one Scope case.
	 */
	public function testCoversAllProtoScopes(): void {
		$expected = [];
		foreach (glob(dirname(__DIR__, 3) . '/gen/Citius/Grpc/Crypto/V1/*Scope.php') as $file) {
			$class = 'Citius\\Grpc\\Crypto\\V1\\' . basename($file, '.php');
			foreach ((new \ReflectionClass($class))->getConstants() as $name => $value) {
				if ($value !== 0) {
					$expected[] = $class . '::' . $name;
				}
			}
		}

		$actual = [];
		foreach (Scope::cases() as $scope) {
			$spec = $scope->toScopeSpecification();
			$family = $spec->getScopeSpec();
			$familySpec = $spec->{'get' . str_replace('_', '', ucwords($family, '_'))}();
			$enumClass = 'Citius\\Grpc\\Crypto\\V1\\' . str_replace('_', '', ucwords($family, '_')) . 'Scope';
			$actual[] = $enumClass . '::' . $enumClass::name($familySpec->getScope());
		}

		sort($expected);
		sort($actual);
		$this->assertSame($expected, $actual);
	}

	public function testSpecificationSerializes(): void {
		$bytes = Scope::SignatureStandard->toScopeSpecification()->serializeToString();
		$decoded = new ScopeSpecification();
		$decoded->mergeFromString($bytes);

		$this->assertSame(SignatureScope::SIGNATURE_SCOPE_STANDARD, $decoded->getSignature()->getScope());
	}
}
