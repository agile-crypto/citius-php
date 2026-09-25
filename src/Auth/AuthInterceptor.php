<?php

declare(strict_types=1);

namespace Citius\Client\Auth;

use Grpc\Interceptor;

/**
 * Adds "authorization: Bearer <token>" to the metadata of every call, with the token obtained from a {@see TokenProvider}.
 */
final class AuthInterceptor extends Interceptor {
	public function __construct(
		private readonly TokenProvider $tokenProvider,
	) {
	}

	#[\Override]
	public function interceptUnaryUnary($method, $argument, $deserialize, $continuation, array $metadata = [], array $options = []) {
		return $continuation($method, $argument, $deserialize, $this->withToken($metadata), $options);
	}

	#[\Override]
	public function interceptStreamUnary($method, $deserialize, $continuation, array $metadata = [], array $options = []) {
		return $continuation($method, $deserialize, $this->withToken($metadata), $options);
	}

	#[\Override]
	public function interceptUnaryStream($method, $argument, $deserialize, $continuation, array $metadata = [], array $options = []) {
		return $continuation($method, $argument, $deserialize, $this->withToken($metadata), $options);
	}

	#[\Override]
	public function interceptStreamStream($method, $deserialize, $continuation, array $metadata = [], array $options = []) {
		return $continuation($method, $deserialize, $this->withToken($metadata), $options);
	}

	/**
	 * @param array<string, list<string>> $metadata
	 * @return array<string, list<string>>
	 */
	private function withToken(array $metadata): array {
		$token = $this->tokenProvider->token();
		if ($token !== '') {
			$metadata['authorization'][] = 'Bearer ' . $token;
		}
		return $metadata;
	}
}
