<?php

declare(strict_types=1);

namespace Citius\Client\Exception;

/**
 * A policy could not be evaluated, e.g. the policy server is unreachable or rejected the request.
 */
class PolicyServiceException extends \RuntimeException {
}
