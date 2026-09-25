<?php

declare(strict_types=1);

namespace Citius\Client\Auth;

/**
 * Fixed bearer token (YAML auth.static_token).
 */
final class StaticToken implements TokenProvider {
	public function __construct(
		#[\SensitiveParameter]
		private readonly string $token,
	) {
	}

	#[\Override]
	public function token(): string {
		return $this->token;
	}
}
