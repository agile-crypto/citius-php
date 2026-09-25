<?php

declare(strict_types=1);

namespace Citius\Client\Config;

/**
 * TLS transport settings (YAML key "tls").
 *
 * File paths are used as given: relative paths resolve against the current working directory.
 */
final class TlsConfig {
	public const MIN_VERSION_UNSPECIFIED = '';
	public const MIN_VERSION_1_2 = '1.2';
	public const MIN_VERSION_1_3 = '1.3';

	/**
	 * @param string $caCert PEM CA certificate file; empty uses the system root CAs
	 * @param string $clientCert PEM client certificate file; with $clientKey, enables mutual TLS
	 * @param string $clientKey PEM client private key file
	 * @param bool $insecure plaintext connection without TLS; development only
	 * @param string $serverName name the server certificate is verified against; required with TLS
	 * @param string $minVersion minimum TLS version: "", "1.2" or "1.3"
	 */
	public function __construct(
		public readonly string $caCert = '',
		public readonly string $clientCert = '',
		public readonly string $clientKey = '',
		public readonly bool $insecure = false,
		public readonly string $serverName = '',
		public readonly string $minVersion = self::MIN_VERSION_UNSPECIFIED,
	) {
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data, string $path): self {
		$reader = new ArrayReader($data, $path);
		return new self(
			caCert: $reader->string('ca_cert'),
			clientCert: $reader->string('client_cert'),
			clientKey: $reader->string('client_key'),
			insecure: $reader->bool('insecure'),
			serverName: $reader->string('server_name'),
			minVersion: $reader->string('min_version'),
		);
	}
}
