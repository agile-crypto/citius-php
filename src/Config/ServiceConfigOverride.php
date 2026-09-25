<?php

declare(strict_types=1);

namespace Citius\Client\Config;

/**
 * Per-service section of the configuration. Empty or null fields inherit from the "default" section;
 * a "tls" or "auth" block replaces the default block as a whole.
 */
final class ServiceConfigOverride {
	public function __construct(
		public readonly string $endpoint = '',
		public readonly ?TlsConfig $tls = null,
		public readonly ?AuthConfig $auth = null,
		public readonly string $timeout = '',
	) {
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data, string $path): self {
		$reader = new ArrayReader($data, $path);
		$tls = $reader->map('tls');
		$auth = $reader->map('auth');
		return new self(
			endpoint: $reader->string('endpoint'),
			tls: $tls === null ? null : TlsConfig::fromArray($tls, $reader->key('tls')),
			auth: $auth === null ? null : AuthConfig::fromArray($auth, $reader->key('auth')),
			timeout: $reader->string('timeout'),
		);
	}

	public function applyTo(ServiceConfig $default): ServiceConfig {
		return new ServiceConfig(
			endpoint: $this->endpoint !== '' ? $this->endpoint : $default->endpoint,
			tls: $this->tls ?? $default->tls,
			auth: $this->auth ?? $default->auth,
			timeout: $this->timeout !== '' ? $this->timeout : $default->timeout,
		);
	}
}
