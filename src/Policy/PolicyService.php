<?php

declare(strict_types=1);

namespace Citius\Client\Policy;

use Citius\Client\Common\Scope;
use Citius\Client\Exception\PolicyNotFoundException;
use Citius\Client\Exception\PolicyServiceException;

/**
 * Crypto policy evaluation, performed locally ({@see LocalPolicyService}) or by a Citius server ({@see RemotePolicyAdapter}).
 */
interface PolicyService {
	/**
	 * Algorithms the policy allows for $scope.
	 *
	 * @throws PolicyNotFoundException when no policy is named $policyName
	 * @throws PolicyServiceException when the policy cannot be evaluated
	 */
	public function listAllowedAlgorithms(string $policyName, Scope $scope): AllowedAlgorithmsResult;
}
