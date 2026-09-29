<?php

declare(strict_types=1);

namespace Citius\Client\Policy;

/**
 * Algorithms a policy allows for one scope, as CycloneDX Cryptography Registry template IDs
 * (e.g. "ML-DSA-44", "ECDSA-P-256-SHA-256"), each list in order of preference.
 */
final class AllowedAlgorithmsResult {
	/**
	 * @param list<string> $allowedTemplates usable without restriction
	 * @param list<string> $legacyTemplates usable only by recipients (e.g. decryption, signature verification),
	 *                                      never to produce new cryptographic artifacts
	 */
	public function __construct(
		public readonly array $allowedTemplates = [],
		public readonly array $legacyTemplates = [],
	) {
	}
}
