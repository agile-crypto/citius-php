<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Support;

use Citius\Grpc\Crypto\V1\ListAllowedAlgorithmsRequest;
use Citius\Grpc\Crypto\V1\ListAllowedAlgorithmsResponse;
use Citius\Grpc\Crypto\V1\SignatureScope;
use Grpc\MethodDescriptor;
use Grpc\ServerContext;
use Grpc\Status;

/**
 * CryptoPolicyService.ListAllowedAlgorithms for the local test server. Knows the policy "nextcloud-webauthn",
 * which allows algorithms for the signature_standard scope only.
 */
final class StubCryptoPolicyService {
	/**
	 * @return array<string, MethodDescriptor>
	 */
	public function getMethodDescriptors(): array {
		return [
			'/caas.crypto.v1.CryptoPolicyService/ListAllowedAlgorithms' => new MethodDescriptor($this, 'listAllowedAlgorithms', ListAllowedAlgorithmsRequest::class, MethodDescriptor::UNARY_CALL),
		];
	}

	public function listAllowedAlgorithms(ListAllowedAlgorithmsRequest $request, ServerContext $context): ?ListAllowedAlgorithmsResponse {
		if ($request->getPolicyName() !== 'nextcloud-webauthn') {
			$context->setStatus(Status::status(\Grpc\STATUS_NOT_FOUND, 'unknown policy'));
			return null;
		}
		$response = (new ListAllowedAlgorithmsResponse())->setPolicyName('nextcloud-webauthn')->setPolicyVersion(1);
		$filter = $request->getFilters();
		if ($filter?->getScopeSpec() === 'signature' && $filter->getSignature()->getScope() === SignatureScope::SIGNATURE_SCOPE_STANDARD) {
			$response->setAllowedTemplates(['ML-DSA-44', 'ECDSA-P-256-SHA-256'])->setLegacyTemplates(['RSA-PKCS1-1.5-SHA-256-2048']);
		}
		return $response;
	}
}
