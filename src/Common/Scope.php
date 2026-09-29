<?php

declare(strict_types=1);

namespace Citius\Client\Common;

use Citius\Grpc\Crypto\V1\ScopeSpecification;

/**
 * Operational scopes of all primitives, flattened into one enumeration. Values are the snake_case names used
 * in policy files, identical to the Go SDK's Scope.String().
 */
enum Scope: string {
	case SignatureStandard = 'signature_standard';
	case SignatureWithContext = 'signature_with_context';
	case SignaturePrehashed = 'signature_prehashed';
	case SignaturePrehashedWithContext = 'signature_prehashed_with_context';

	case AeadStandard = 'aead_standard';
	case AeadDeterministic = 'aead_deterministic';
	case AeadStreaming = 'aead_streaming';

	case MacStandard = 'mac_standard';
	case MacStreaming = 'mac_streaming';

	case KemStandard = 'kem_standard';
	case KemHybrid = 'kem_hybrid';

	case KeyAgreementStandard = 'key_agreement_standard';
	case KeyAgreementHybrid = 'key_agreement_hybrid';

	case KdfExtractExpand = 'kdf_extract_expand';
	case KdfPassword = 'kdf_password';
	case KdfAgreement = 'kdf_agreement';
	case KdfCounter = 'kdf_counter';
	case KdfTls = 'kdf_tls';
	case KdfGost = 'kdf_gost';
	case KdfVendor = 'kdf_vendor';

	case HashStandard = 'hash_standard';
	case HashXof = 'hash_xof';

	case KeyWrappingStandard = 'key_wrapping_standard';
	case KeyWrappingWithPadding = 'key_wrapping_with_padding';

	case SymmetricCipherBlock = 'symmetric_cipher_block';
	case SymmetricCipherStream = 'symmetric_cipher_stream';

	case DiskEncryptionStandard = 'disk_encryption_standard';

	case GenericSecretStandard = 'generic_secret_standard';

	case AsymmetricEncryptionStandard = 'asymmetric_encryption_standard';
	case AsymmetricEncryptionRaw = 'asymmetric_encryption_raw';

	/**
	 * Primitive families, as prefixes of the case values. Each family has a proto enum <Family>Scope, a message
	 * <Family>ScopeSpec and a ScopeSpecification field of the same name.
	 */
	private const FAMILIES = [
		'signature', 'aead', 'mac', 'kem', 'key_agreement', 'kdf', 'hash', 'key_wrapping',
		'symmetric_cipher', 'disk_encryption', 'generic_secret', 'asymmetric_encryption',
	];

	/**
	 * Scope specification holding only this scope, e.g. { signature: { scope: SIGNATURE_SCOPE_STANDARD } }.
	 */
	public function toScopeSpecification(): ScopeSpecification {
		$family = $this->family();
		$studly = str_replace('_', '', ucwords($family, '_'));
		$enumClass = 'Citius\\Grpc\\Crypto\\V1\\' . $studly . 'Scope';
		$specClass = 'Citius\\Grpc\\Crypto\\V1\\' . $studly . 'ScopeSpec';
		$variant = substr($this->value, strlen($family) + 1);

		$spec = (new $specClass())->setScope(constant($enumClass . '::' . strtoupper($family . '_scope_' . $variant)));
		return (new ScopeSpecification())->{'set' . $studly}($spec);
	}

	private function family(): string {
		foreach (self::FAMILIES as $family) {
			if (str_starts_with($this->value, $family . '_')) {
				return $family;
			}
		}
		throw new \LogicException(sprintf('scope "%s" belongs to no primitive family', $this->value));
	}
}
