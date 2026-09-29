<?php

declare(strict_types=1);

namespace Citius\Client\Policy;

use Citius\Client\Config\ArrayReader;
use Citius\Client\Config\Config;
use Citius\Client\Config\Service;
use Citius\Client\Dial\Connector;
use Citius\Client\Exception\ConfigException;
use Citius\Grpc\Crypto\V1\CryptoPolicyServiceClient;

/**
 * Builds the policy service selected by the "crypto_policy" section of the client configuration:
 *
 *     crypto_policy:
 *       mode: local                          # required: local or remote
 *       policies: "/etc/citius/policies.yaml" # local: policy file, see LocalPolicyService
 *
 *     crypto_policy:
 *       mode: remote                         # connection settings as for any service, inherited from "default"
 *       endpoint: "policy.example.com:443"
 */
final class PolicyServiceFactory {
	public const MODE_LOCAL = 'local';
	public const MODE_REMOTE = 'remote';

	/**
	 * @throws ConfigException when the section is missing or invalid, or the policy file is invalid
	 */
	public static function fromConfig(Config $config): PolicyService {
		$section = $config->sections[Service::CryptoPolicy->value] ?? null;
		if ($section === null) {
			throw new ConfigException(sprintf('service "%s" is not configured', Service::CryptoPolicy->value));
		}
		$reader = new ArrayReader($section, Service::CryptoPolicy->value);

		return match ($mode = $reader->string('mode')) {
			self::MODE_LOCAL => self::local($reader),
			self::MODE_REMOTE => self::remote($config),
			'' => throw new ConfigException(sprintf('%s is required: "local" or "remote"', $reader->key('mode'))),
			default => throw new ConfigException(sprintf('%s: unknown mode "%s", expected "local" or "remote"', $reader->key('mode'), $mode)),
		};
	}

	/**
	 * @throws ConfigException
	 */
	public static function fromFile(string $path): PolicyService {
		return self::fromConfig(Config::load($path));
	}

	private static function local(ArrayReader $reader): LocalPolicyService {
		$policies = $reader->string('policies');
		if ($policies === '') {
			throw new ConfigException(sprintf('%s is required in local mode', $reader->key('policies')));
		}
		return LocalPolicyService::fromFile($policies);
	}

	private static function remote(Config $config): RemotePolicyAdapter {
		if (!extension_loaded('grpc')) {
			throw new ConfigException('remote crypto_policy requires the grpc PHP extension');
		}
		$connection = Connector::connect($config->resolve(Service::CryptoPolicy));
		return new RemotePolicyAdapter($connection->createClient(CryptoPolicyServiceClient::class));
	}
}
