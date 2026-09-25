<?php

declare(strict_types=1);

namespace Citius\Client\Config;

/**
 * Request authentication settings (YAML key "auth").
 *
 * When enabled, exactly one of $staticToken and $tokenSource must be set.
 */
final class AuthConfig {
	/**
	 * @param bool $enabled attach a bearer token to every request
	 * @param string $staticToken fixed bearer token
	 * @param string $tokenSource file containing the bearer token, read on every request
	 */
	public function __construct(
		public readonly bool $enabled = false,
		public readonly string $staticToken = '',
		public readonly string $tokenSource = '',
	) {
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data, string $path): self {
		$reader = new ArrayReader($data, $path);
		return new self(
			enabled: $reader->bool('enabled'),
			staticToken: $reader->string('static_token'),
			tokenSource: $reader->string('token_source'),
		);
	}
}
