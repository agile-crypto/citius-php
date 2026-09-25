<?php

declare(strict_types=1);

namespace Citius\Client\Config;

use Citius\Client\Auth\StaticToken;
use Citius\Client\Auth\TokenProvider;
use Citius\Client\Auth\TokenSource;
use Citius\Client\Exception\ConfigException;

/**
 * Fully resolved configuration of one service: the "default" section with the service's overrides applied.
 */
final class ServiceConfig {
	/**
	 * @param string $endpoint gRPC target, e.g. "citius.example.com:443"
	 * @param string $timeout per-request timeout as a Go duration, e.g. "30s"; empty for none
	 */
	public function __construct(
		public readonly string $endpoint = '',
		public readonly TlsConfig $tls = new TlsConfig(),
		public readonly AuthConfig $auth = new AuthConfig(),
		public readonly string $timeout = '',
	) {
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data, string $path): self {
		$reader = new ArrayReader($data, $path);
		return new self(
			endpoint: $reader->string('endpoint'),
			tls: TlsConfig::fromArray($reader->map('tls') ?? [], $reader->key('tls')),
			auth: AuthConfig::fromArray($reader->map('auth') ?? [], $reader->key('auth')),
			timeout: $reader->string('timeout'),
		);
	}

	/**
	 * Token provider described by the auth settings, or null when authentication is disabled.
	 *
	 * @throws ConfigException when auth is enabled with no token, with both token kinds, or with a missing token file
	 */
	public function resolveTokenProvider(): ?TokenProvider {
		if (!$this->auth->enabled) {
			return null;
		}
		$hasStatic = $this->auth->staticToken !== '';
		$hasSource = $this->auth->tokenSource !== '';
		if ($hasStatic && $hasSource) {
			throw new ConfigException('auth is enabled but multiple token providers are configured; only one of static_token or token_source can be used');
		}
		if ($hasStatic) {
			return new StaticToken($this->auth->staticToken);
		}
		if ($hasSource) {
			return new TokenSource($this->auth->tokenSource);
		}
		throw new ConfigException('auth is enabled but no token is configured; set static_token or token_source');
	}
}
