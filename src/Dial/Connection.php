<?php

declare(strict_types=1);

namespace Citius\Client\Dial;

use Grpc\BaseStub;
use Grpc\Channel;
use Grpc\Internal\InterceptorChannel;

/**
 * Constructor arguments of the generated gRPC clients, built by {@see Connector::connect()}:
 *
 *     $client = new CryptoServiceClient($connection->hostname, $connection->opts, $connection->channel);
 *
 * The channel carries the TLS credentials and the configured interceptors (authentication, timeout),
 * so the same connection can be shared by the clients of several services on the same endpoint.
 */
final class Connection {
	/**
	 * @param string $hostname gRPC target
	 * @param array<string, mixed> $opts channel options, including "credentials"
	 */
	public function __construct(
		public readonly string $hostname,
		public readonly array $opts,
		public readonly Channel|InterceptorChannel $channel,
	) {
	}

	/**
	 * @template T of BaseStub
	 * @param class-string<T> $clientClass generated client, e.g. CryptoServiceClient::class
	 * @return T
	 */
	public function createClient(string $clientClass): BaseStub {
		return new $clientClass($this->hostname, $this->opts, $this->channel);
	}
}
