<?php

declare(strict_types=1);

namespace Citius\Client\Policy;

use Citius\Client\Common\Scope;
use Citius\Client\Exception\ConfigException;
use Citius\Client\Exception\PolicyNotFoundException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Policy evaluation without a server, from policies held in memory. Policy file format:
 *
 *     policies:
 *       - name: nextcloud-webauthn
 *         scopes:
 *           signature_standard:          # a Scope value
 *             allowed_algorithms:        # in order of preference
 *               - ML-DSA-44
 *               - ECDSA-P-256-SHA-256
 *             legacy_algorithms:         # recipient usage only
 *               - RSA-PKCS1-1.5-SHA-256-2048
 *
 * A scope missing from a policy has no allowed algorithms.
 */
final class LocalPolicyService implements PolicyService {
	/** @var array<string, array<string, AllowedAlgorithmsResult>> */
	private readonly array $policies;

	/**
	 * @param array<string, array<string, AllowedAlgorithmsResult>> $policies policy name => Scope value => algorithms
	 * @throws ConfigException on an empty or invalid template or scope, or a template both allowed and legacy
	 */
	public function __construct(array $policies) {
		foreach ($policies as $name => $scopes) {
			if (!is_string($name) || $name === '') {
				throw new ConfigException('policy names must be non-empty strings');
			}
			foreach ($scopes as $scope => $result) {
				if (Scope::tryFrom((string)$scope) === null) {
					throw new ConfigException(sprintf('policy "%s": unknown scope "%s"', $name, $scope));
				}
				self::validate($result, sprintf('policy "%s", scope "%s"', $name, $scope));
			}
		}
		$this->policies = $policies;
	}

	/**
	 * @throws ConfigException when the file cannot be read or is invalid
	 */
	public static function fromFile(string $path): self {
		$yaml = @file_get_contents($path);
		if ($yaml === false) {
			throw new ConfigException(sprintf('cannot read policy file "%s"', $path));
		}
		try {
			return self::fromYaml($yaml);
		} catch (ConfigException $e) {
			throw new ConfigException(sprintf('policy file "%s": %s', $path, $e->getMessage()), 0, $e);
		}
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
		return self::fromArray(is_array($data) ? $data : []);
	}

	/**
	 * Builds the service from a decoded policy document.
	 *
	 * @param array<mixed> $data
	 * @throws ConfigException
	 */
	public static function fromArray(array $data): self {
		$list = $data['policies'] ?? null;
		if (!is_array($list) || !array_is_list($list)) {
			throw new ConfigException('"policies" must be a list');
		}

		$policies = [];
		foreach ($list as $index => $policy) {
			$path = sprintf('policies[%d]', $index);
			$name = is_array($policy) ? ($policy['name'] ?? null) : null;
			if (!is_string($name) || $name === '') {
				throw new ConfigException(sprintf('%s.name must be a non-empty string', $path));
			}
			if (isset($policies[$name])) {
				throw new ConfigException(sprintf('%s: duplicate policy name "%s"', $path, $name));
			}
			$scopes = $policy['scopes'] ?? [];
			if (!is_array($scopes) || ($scopes !== [] && array_is_list($scopes))) {
				throw new ConfigException(sprintf('%s.scopes must be a mapping of scope to algorithms', $path));
			}

			$policies[$name] = [];
			foreach ($scopes as $scope => $algorithms) {
				$algorithms ??= [];
				if (!is_array($algorithms)) {
					throw new ConfigException(sprintf('%s.scopes.%s must be a mapping', $path, $scope));
				}
				$policies[$name][(string)$scope] = new AllowedAlgorithmsResult(
					self::templates($algorithms['allowed_algorithms'] ?? [], sprintf('%s.scopes.%s.allowed_algorithms', $path, $scope)),
					self::templates($algorithms['legacy_algorithms'] ?? [], sprintf('%s.scopes.%s.legacy_algorithms', $path, $scope)),
				);
			}
		}
		return new self($policies);
	}

	#[\Override]
	public function listAllowedAlgorithms(string $policyName, Scope $scope): AllowedAlgorithmsResult {
		if (!isset($this->policies[$policyName])) {
			throw new PolicyNotFoundException(sprintf('policy "%s" not found', $policyName));
		}
		return $this->policies[$policyName][$scope->value] ?? new AllowedAlgorithmsResult();
	}

	/**
	 * @return list<string>
	 */
	private static function templates(mixed $templates, string $path): array {
		if (!is_array($templates) || !array_is_list($templates)) {
			throw new ConfigException(sprintf('%s must be a list of template IDs', $path));
		}
		foreach ($templates as $template) {
			if (!is_string($template)) {
				throw new ConfigException(sprintf('%s must contain only strings, got %s', $path, get_debug_type($template)));
			}
		}
		return $templates;
	}

	private static function validate(mixed $result, string $context): void {
		if (!$result instanceof AllowedAlgorithmsResult) {
			throw new ConfigException(sprintf('%s: expected %s, got %s', $context, AllowedAlgorithmsResult::class, get_debug_type($result)));
		}
		foreach (['allowed' => $result->allowedTemplates, 'legacy' => $result->legacyTemplates] as $kind => $templates) {
			foreach ($templates as $template) {
				if (!is_string($template) || trim($template) === '') {
					throw new ConfigException(sprintf('%s: %s templates must be non-empty strings', $context, $kind));
				}
			}
			if (count(array_unique($templates)) !== count($templates)) {
				throw new ConfigException(sprintf('%s: duplicate %s template', $context, $kind));
			}
		}
		$both = array_intersect($result->allowedTemplates, $result->legacyTemplates);
		if ($both !== []) {
			throw new ConfigException(sprintf('%s: templates both allowed and legacy: %s', $context, implode(', ', $both)));
		}
	}
}
