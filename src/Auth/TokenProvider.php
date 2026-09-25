<?php

declare(strict_types=1);

namespace Citius\Client\Auth;

use Citius\Client\Exception\TokenException;

/**
 * Source of the bearer token attached to requests. Implement it for dynamic acquisition (OAuth2, workload identity, ...).
 */
interface TokenProvider {
	/**
	 * Called before every request. An empty string sends the request without a token.
	 *
	 * @throws TokenException
	 */
	public function token(): string;
}
