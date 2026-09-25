<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Support;

use Citius\Grpc\Crypto\V1\SignRequest;
use Citius\Grpc\Crypto\V1\SignResponse;
use Citius\Grpc\Crypto\V1\VerifyRequest;
use Citius\Grpc\Crypto\V1\VerifyResponse;
use Grpc\MethodDescriptor;
use Grpc\ServerContext;
use Grpc\Status;

/**
 * CryptoService Sign and Verify for the local test server. The "signature" is "sig:" . input.
 * When a token is expected, calls without "authorization: Bearer <token>" fail with UNAUTHENTICATED,
 * like the Go SDK example server. Signing the input "slow" takes two seconds.
 */
final class StubCryptoService {
	public function __construct(
		private readonly string $expectedToken,
	) {
	}

	/**
	 * @return array<string, MethodDescriptor>
	 */
	public function getMethodDescriptors(): array {
		return [
			'/caas.crypto.v1.CryptoService/Sign' => new MethodDescriptor($this, 'sign', SignRequest::class, MethodDescriptor::UNARY_CALL),
			'/caas.crypto.v1.CryptoService/Verify' => new MethodDescriptor($this, 'verify', VerifyRequest::class, MethodDescriptor::UNARY_CALL),
		];
	}

	public function sign(SignRequest $request, ServerContext $context): ?SignResponse {
		if (!$this->authorize($context)) {
			return null;
		}
		if ($request->getInput() === 'slow') {
			sleep(2);
		}
		return (new SignResponse())->setSignature('sig:' . $request->getInput());
	}

	public function verify(VerifyRequest $request, ServerContext $context): ?VerifyResponse {
		if (!$this->authorize($context)) {
			return null;
		}
		return (new VerifyResponse())->setValid($request->getSignature() === 'sig:' . $request->getInput());
	}

	private function authorize(ServerContext $context): bool {
		if ($this->expectedToken === '') {
			return true;
		}
		$values = $context->clientMetadata()['authorization'] ?? [];
		if ($values === []) {
			$context->setStatus(Status::status(\Grpc\STATUS_UNAUTHENTICATED, 'missing authorization header'));
			return false;
		}
		if ($values[0] !== 'Bearer ' . $this->expectedToken) {
			$context->setStatus(Status::status(\Grpc\STATUS_UNAUTHENTICATED, 'invalid token'));
			return false;
		}
		return true;
	}
}
