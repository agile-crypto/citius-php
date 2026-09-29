<?php

declare(strict_types=1);

namespace Citius\Client\Config;

use Citius\Client\Exception\ConfigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Client configuration, in the YAML schema of the Citius Go SDK:
 *
 *     default:            # settings shared by all services
 *       endpoint: "citius.example.com:443"
 *       tls: { ca_cert, client_cert, client_key, insecure, server_name, min_version }
 *       auth: { enabled, static_token, token_source }
 *       timeout: "30s"
 *     crypto: {}          # one section per service to use; fields override "default"
 *     key_management:
 *       endpoint: "km.example.com:443"
 *
 * A service can only be resolved when its section is present, even if empty.
 */
final class Config {
	/**
	 * @param array<string, ServiceConfigOverride|null> $overrides service sections, keyed by {@see Service} value
	 * @param array<string, array<mixed>|null> $sections raw service sections, including keys outside the Go SDK
	 *                                                   schema such as the crypto_policy "mode", keyed by {@see Service} value
	 */
	public function __construct(
		public readonly ServiceConfig $default = new ServiceConfig(),
		public readonly array $overrides = [],
		public readonly array $sections = [],
	) {
	}

	/**
	 * Reads and parses a YAML configuration file.
	 *
	 * @throws ConfigException
	 */
	public static function load(string $path): self {
		$yaml = @file_get_contents($path);
		if ($yaml === false) {
			throw new ConfigException(sprintf('cannot read config file "%s"', $path));
		}
		return self::fromYaml($yaml);
	}

	/**
	 * @throws ConfigException
	 */
	public static function fromYaml(string $yaml): self {
		try {
			$data = Yaml::parse($yaml);
		} catch (ParseException $e) {
			throw new ConfigException('invalid YAML: ' . $e->getMessage(), 0, $e);
		}
		if ($data === null) {
			return new self();
		}
		if (!is_array($data)) {
			throw new ConfigException('invalid config: the document must be a mapping');
		}
		return self::fromArray($data);
	}

	/**
	 * Builds the configuration from an already decoded document, e.g. an array stored in an application config.
	 *
	 * @param array<mixed> $data
	 * @throws ConfigException
	 */
	public static function fromArray(array $data): self {
		$reader = new ArrayReader($data, '');
		$default = ServiceConfig::fromArray($reader->map('default') ?? [], 'default');

		$overrides = [];
		$sections = [];
		foreach (array_keys($data) as $key) {
			if ($key === 'default') {
				continue;
			}
			$service = Service::tryFrom((string)$key);
			if ($service === null) {
				throw new ConfigException(sprintf('unknown service name "%s"', $key));
			}
			$section = $reader->map((string)$key);
			$overrides[$service->value] = $section === null ? null : ServiceConfigOverride::fromArray($section, (string)$key);
			$sections[$service->value] = $section;
		}
		return new self($default, $overrides, $sections);
	}

	/**
	 * Configuration of $service: the default section with the service's overrides applied.
	 *
	 * @throws ConfigException when the service has no section
	 */
	public function resolve(Service $service): ServiceConfig {
		$override = $this->overrides[$service->value] ?? null;
		if ($override === null) {
			throw new ConfigException(sprintf('service "%s" is not configured', $service->value));
		}
		return $override->applyTo($this->default);
	}

	/**
	 * @throws ConfigException
	 */
	public static function resolveFromFile(string $path, Service $service): ServiceConfig {
		return self::load($path)->resolve($service);
	}
}
