<?php

declare(strict_types=1);

namespace Citius\Client\Dial;

use Grpc\Interceptor;

/**
 * Applies a default deadline to unary calls that do not set their own "timeout" call option.
 */
final class TimeoutInterceptor extends Interceptor {
	/**
	 * @param int $timeout in microseconds
	 */
	public function __construct(
		private readonly int $timeout,
	) {
	}

	#[\Override]
	public function interceptUnaryUnary($method, $argument, $deserialize, $continuation, array $metadata = [], array $options = []) {
		$options['timeout'] ??= $this->timeout;
		return $continuation($method, $argument, $deserialize, $metadata, $options);
	}
}
