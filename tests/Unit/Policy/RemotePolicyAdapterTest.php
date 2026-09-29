<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Policy;

use Citius\Client\Common\Scope;
use Citius\Client\Exception\PolicyNotFoundException;
use Citius\Client\Exception\PolicyServiceException;
use Citius\Client\Policy\RemotePolicyAdapter;
use Citius\Grpc\Crypto\V1\CryptoPolicyServiceClient;
use Citius\Grpc\Crypto\V1\ListAllowedAlgorithmsRequest;
use Citius\Grpc\Crypto\V1\ListAllowedAlgorithmsResponse;
use Citius\Grpc\Crypto\V1\SignatureScope;
use PHPUnit\Framework\TestCase;

/**
 * Request building and status handling, with a client that returns a canned result instead of calling a server.
 */
class RemotePolicyAdapterTest extends TestCase {
	public function testSendsPolicyAndScopeAndReturnsBothLists(): void {
		$response = (new ListAllowedAlgorithmsResponse())
			->setPolicyName('nextcloud-webauthn')
			->setAllowedTemplates(['ML-DSA-44', 'ECDSA-P-256-SHA-256'])
			->setLegacyTemplates(['RSA-PKCS1-1.5-SHA-256-2048']);
		$client = $this->client($response, \Grpc\STATUS_OK);

		$result = (new RemotePolicyAdapter($client))->listAllowedAlgorithms('nextcloud-webauthn', Scope::SignatureStandard);

		$this->assertSame(['ML-DSA-44', 'ECDSA-P-256-SHA-256'], $result->allowedTemplates);
		$this->assertSame(['RSA-PKCS1-1.5-SHA-256-2048'], $result->legacyTemplates);
		$this->assertSame('nextcloud-webauthn', $client->request->getPolicyName());
		$this->assertSame('signature', $client->request->getFilters()->getScopeSpec());
		$this->assertSame(SignatureScope::SIGNATURE_SCOPE_STANDARD, $client->request->getFilters()->getSignature()->getScope());
	}

	public function testNotFoundStatus(): void {
		$this->expectException(PolicyNotFoundException::class);
		$this->expectExceptionMessage('policy "missing" not found: no such policy');
		(new RemotePolicyAdapter($this->client(null, \Grpc\STATUS_NOT_FOUND, 'no such policy')))->listAllowedAlgorithms('missing', Scope::SignatureStandard);
	}

	public function testOtherErrorStatus(): void {
		$this->expectException(PolicyServiceException::class);
		$this->expectExceptionCode(\Grpc\STATUS_UNAVAILABLE);
		(new RemotePolicyAdapter($this->client(null, \Grpc\STATUS_UNAVAILABLE, 'connection refused')))->listAllowedAlgorithms('p', Scope::SignatureStandard);
	}

	private function client(?ListAllowedAlgorithmsResponse $response, int $code, string $details = ''): CryptoPolicyServiceClient {
		return new class($response, (object)['code' => $code, 'details' => $details]) extends CryptoPolicyServiceClient {
			public ?ListAllowedAlgorithmsRequest $request = null;

			public function __construct(
				private readonly ?ListAllowedAlgorithmsResponse $response,
				private readonly object $status,
			) {
				parent::__construct('localhost:1', ['credentials' => null]);
			}

			#[\Override]
			public function ListAllowedAlgorithms(ListAllowedAlgorithmsRequest $argument, $metadata = [], $options = []) {
				$this->request = $argument;
				return new class([$this->response, $this->status]) {
					public function __construct(
						private readonly array $result,
					) {
					}

					public function wait(): array {
						return $this->result;
					}
				};
			}
		};
	}
}
