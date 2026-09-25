<?php

declare(strict_types=1);

namespace Citius\Client\Dial;

use Citius\Client\Auth\AuthInterceptor;
use Citius\Client\Config\Config;
use Citius\Client\Config\Duration;
use Citius\Client\Config\Service;
use Citius\Client\Config\ServiceConfig;
use Citius\Client\Config\TlsConfig;
use Citius\Client\Exception\ConfigException;
use Grpc\BaseStub;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use Grpc\Interceptor;

/**
 * Builds gRPC connections from a resolved service configuration.
 */
final class Connector {
	/**
	 * Builds the arguments for a generated client of the configured service. No network I/O happens here:
	 * the channel connects on the first call.
	 *
	 * @throws ConfigException when the configuration is incomplete or references unreadable or invalid files
	 */
	public static function connect(ServiceConfig $config): Connection {
		if ($config->endpoint === '') {
			throw new ConfigException('endpoint is required');
		}

		$opts = ['credentials' => self::buildCredentials($config->tls)];
		if (!$config->tls->insecure) {
			$opts['grpc.ssl_target_name_override'] = $config->tls->serverName;
		}

		$interceptors = [];
		$tokenProvider = $config->resolveTokenProvider();
		if ($tokenProvider !== null) {
			$interceptors[] = new AuthInterceptor($tokenProvider);
		}
		if ($config->timeout !== '') {
			$timeout = Duration::toMicroseconds($config->timeout);
			if ($timeout < 0) {
				throw new ConfigException(sprintf('timeout must not be negative: "%s"', $config->timeout));
			}
			if ($timeout > 0) {
				$interceptors[] = new TimeoutInterceptor($timeout);
			}
		}

		$channel = new Channel($config->endpoint, $opts);
		return new Connection(
			$config->endpoint,
			$opts,
			$interceptors === [] ? $channel : Interceptor::intercept($channel, $interceptors),
		);
	}

	/**
	 * Resolves $service from a YAML configuration file and connects to it.
	 *
	 * @throws ConfigException
	 */
	public static function connectFromFile(string $path, Service $service): Connection {
		return self::connect(Config::resolveFromFile($path, $service));
	}

	/**
	 * @return ChannelCredentials|null null for a plaintext channel, as returned by ChannelCredentials::createInsecure()
	 */
	private static function buildCredentials(TlsConfig $tls): ?ChannelCredentials {
		if ($tls->insecure) {
			return ChannelCredentials::createInsecure();
		}
		if ($tls->serverName === '') {
			throw new ConfigException('TLS server name is required; set tls.server_name');
		}
		match ($tls->minVersion) {
			TlsConfig::MIN_VERSION_UNSPECIFIED, TlsConfig::MIN_VERSION_1_2 => null,
			// gRPC PHP exposes no setting for the TLS version; gRPC core only guarantees TLS >= 1.2
			TlsConfig::MIN_VERSION_1_3 => throw new ConfigException('tls.min_version "1.3" cannot be enforced by the PHP gRPC extension; use "1.2"'),
			default => throw new ConfigException(sprintf('unsupported tls.min_version "%s"; must be "1.2" or "1.3"', $tls->minVersion)),
		};

		$rootCerts = $tls->caCert !== '' ? self::readCaCerts($tls->caCert) : self::systemRootCerts();

		$privateKey = null;
		$certChain = null;
		if ($tls->clientCert !== '' || $tls->clientKey !== '') {
			if ($tls->clientCert === '' || $tls->clientKey === '') {
				throw new ConfigException('mutual TLS requires both tls.client_cert and tls.client_key');
			}
			$certChain = self::readFile($tls->clientCert, 'client certificate');
			$privateKey = self::readFile($tls->clientKey, 'client key');
			$certificate = @openssl_x509_read($certChain);
			$key = @openssl_pkey_get_private($privateKey);
			if ($certificate === false || $key === false) {
				throw new ConfigException(sprintf('load client cert/key: "%s" or "%s" is not valid unencrypted PEM', $tls->clientCert, $tls->clientKey));
			}
			if (!openssl_x509_check_private_key($certificate, $key)) {
				throw new ConfigException(sprintf('load client cert/key: "%s" does not match the certificate "%s"', $tls->clientKey, $tls->clientCert));
			}
		}

		return ChannelCredentials::createSsl($rootCerts, $privateKey, $certChain);
	}

	private static function readCaCerts(string $path): string {
		$pem = self::readFile($path, 'CA cert');
		if (self::countCertificates($pem) === 0) {
			throw new ConfigException(sprintf('no valid certificates found in "%s"', $path));
		}
		return $pem;
	}

	/**
	 * System trust store, as for the Go SDK. Falls back to the roots bundled with grpc/grpc.
	 */
	private static function systemRootCerts(): ?string {
		$locations = openssl_get_cert_locations();
		$candidates = [getenv($locations['default_cert_file_env'] ?? 'SSL_CERT_FILE'), $locations['default_cert_file'] ?? ''];
		foreach ($candidates as $file) {
			if (is_string($file) && $file !== '' && is_readable($file)) {
				$pem = (string)file_get_contents($file);
				if (self::countCertificates($pem) > 0) {
					return $pem;
				}
			}
		}

		if (!ChannelCredentials::isDefaultRootsPemSet()) {
			$bundled = dirname((string)(new \ReflectionClass(BaseStub::class))->getFileName(), 3) . '/etc/roots.pem';
			if (is_readable($bundled)) {
				ChannelCredentials::setDefaultRootsPem((string)file_get_contents($bundled));
			}
		}
		return null;
	}

	private static function countCertificates(string $pem): int {
		preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $pem, $matches);
		return count(array_filter($matches[0], static fn (string $cert): bool => @openssl_x509_read($cert) !== false));
	}

	private static function readFile(string $path, string $what): string {
		$content = @file_get_contents($path);
		if ($content === false) {
			throw new ConfigException(sprintf('read %s "%s": file is missing or unreadable', $what, $path));
		}
		return $content;
	}
}
