<?php

declare(strict_types=1);

namespace Citius\Client\Policy;

use Citius\Client\Common\Scope;
use Citius\Client\Exception\PolicyNotFoundException;
use Citius\Client\Exception\PolicyServiceException;
use Citius\Grpc\Crypto\V1\CryptoPolicyServiceClient;
use Citius\Grpc\Crypto\V1\ListAllowedAlgorithmsRequest;
use Citius\Grpc\Crypto\V1\ListAllowedAlgorithmsResponse;

/**
 * Policy evaluation by the CryptoPolicyService of a Citius server. Build it with
 * {@see PolicyServiceFactory}, or from a client created with {@see \Citius\Client\Dial\Connection::createClient()}.
 */
final class RemotePolicyAdapter implements PolicyService {
	public function __construct(
		private readonly CryptoPolicyServiceClient $client,
	) {
	}

	#[\Override]
	public function listAllowedAlgorithms(string $policyName, Scope $scope): AllowedAlgorithmsResult {
		$request = (new ListAllowedAlgorithmsRequest())
			->setPolicyName($policyName)
			->setFilters($scope->toScopeSpecification());

		/** @var ListAllowedAlgorithmsResponse|null $response */
		[$response, $status] = $this->client->ListAllowedAlgorithms($request)->wait();
		if ($status->code === \Grpc\STATUS_NOT_FOUND) {
			throw new PolicyNotFoundException(sprintf('policy "%s" not found: %s', $policyName, $status->details));
		}
		if ($status->code !== \Grpc\STATUS_OK || $response === null) {
			throw new PolicyServiceException(sprintf('ListAllowedAlgorithms for policy "%s" failed with status %d: %s', $policyName, $status->code, $status->details), $status->code);
		}

		return new AllowedAlgorithmsResult(
			iterator_to_array($response->getAllowedTemplates(), false),
			iterator_to_array($response->getLegacyTemplates(), false),
		);
	}
}
