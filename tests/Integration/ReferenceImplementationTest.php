<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Integration;

use Citius\Client\Config\AuthConfig;
use Citius\Client\Config\ServiceConfig;
use Citius\Client\Config\TlsConfig;
use Citius\Client\Dial\Connector;
use Citius\Grpc\Crypto\V1\CreateCryptoPolicyRequest;
use Citius\Grpc\Crypto\V1\CreateKeyRequest;
use Citius\Grpc\Crypto\V1\CryptoPolicyServiceClient;
use Citius\Grpc\Crypto\V1\CryptoServiceClient;
use Citius\Grpc\Crypto\V1\KeyManagementServiceClient;
use Citius\Grpc\Crypto\V1\NoParams;
use Citius\Grpc\Crypto\V1\ScopeSpecification;
use Citius\Grpc\Crypto\V1\SignatureScope;
use Citius\Grpc\Crypto\V1\SignatureScopeSpec;
use Citius\Grpc\Crypto\V1\SignRequest;
use Citius\Grpc\Crypto\V1\VerifyRequest;
use Grpc\UnaryCall;
use PHPUnit\Framework\TestCase;

/**
 * Equivalent of citius-go-sdk/examples/reference_implementation/client, run against the Citius server
 * reference implementation: create a policy, create a signature key, sign, verify.
 *
 * Same environment variables as the Go example (see citius-zitadel.env of the server); its flags become:
 *   CITIUS_ADDR               server address (required, the test is skipped without it)
 *   CITIUS_TLS=1              use TLS (Go: -tls), with CITIUS_TLS_CA and optionally CITIUS_TLS_SERVER_NAME
 *   CITIUS_AUTH=1             use authentication (Go: -auth)
 *   CITIUS_ROLE               ADMIN (default), NOPERM, READONLY or TESTER (Go: -role)
 *   CITIUS_AUTH_TOKEN_FILE    token file (Go: -auth-token-file), default $SVC_<ROLE>_TOKEN_FILE
 *
 * Unlike the Go example, policy and key names get a random suffix so the test can run repeatedly.
 */
class ReferenceImplementationTest extends TestCase {
	private const POLICY_DOCUMENT = <<<'JSON'
		{
			"version": "1",
			"allowed_templates": ["ecdsa-p256-sha256-der"],
			"allowed_operations": {
				"key_operations": ["create_key", "read_key", "sign", "verify"]
			}
		}
		JSON;

	public function testPolicyKeySignVerify(): void {
		$connection = Connector::connect($this->serviceConfig());
		$policyClient = $connection->createClient(CryptoPolicyServiceClient::class);
		$kmClient = $connection->createClient(KeyManagementServiceClient::class);
		$cryptoClient = $connection->createClient(CryptoServiceClient::class);

		$suffix = bin2hex(random_bytes(4));
		$policyName = 'example-policy-' . $suffix;
		$keyName = 'test-key-name-' . $suffix;
		$plaintext = 'example plaintext';

		// 1. Create policy
		$policy = $this->call('CreateCryptoPolicy', $policyClient->CreateCryptoPolicy(
			(new CreateCryptoPolicyRequest())->setName($policyName)->setPolicyDocument(self::POLICY_DOCUMENT),
		));
		$this->assertTrue($policy->getSuccess(), 'create policy: ' . $policy->getMessage());

		// 2. Create a key for standard signatures
		$scopeSpec = (new ScopeSpecification())->setSignature(
			(new SignatureScopeSpec())->setScope(SignatureScope::SIGNATURE_SCOPE_STANDARD)->setNonMalleable(false)->setDeterministic(false),
		);
		$key = $this->call('CreateKey', $kmClient->CreateKey(
			(new CreateKeyRequest())->setName($keyName)->setPolicy($policyName)->setScopeSpec($scopeSpec),
		));
		$this->assertTrue($key->getSuccess(), 'create key: ' . $key->getMessage());

		// 3. Sign
		$signed = $this->call('Sign', $cryptoClient->Sign(
			(new SignRequest())->setKeyName($keyName)->setInput($plaintext)->setNoContext(new NoParams()),
		));
		$this->assertNotSame('', $signed->getSignature());

		// 4. Verify with the metadata returned by Sign
		$verified = $this->call('Verify', $cryptoClient->Verify(
			(new VerifyRequest())
				->setKeyName($keyName)
				->setInput($plaintext)
				->setSignature($signed->getSignature())
				->setNoContext(new NoParams())
				->setMetadata($signed->getMetadata()),
		));
		$this->assertTrue($verified->getValid());
	}

	/**
	 * Same configuration as the Go example's main(): plaintext without auth unless enabled.
	 */
	private function serviceConfig(): ServiceConfig {
		$address = (string)getenv('CITIUS_ADDR');
		if ($address === '') {
			$this->markTestSkipped('CITIUS_ADDR is not set');
		}

		$tls = new TlsConfig(insecure: true);
		if (getenv('CITIUS_TLS') === '1') {
			$caCert = (string)getenv('CITIUS_TLS_CA');
			$this->assertNotSame('', $caCert, 'TLS is enabled but CITIUS_TLS_CA is not set');
			$serverName = (string)getenv('CITIUS_TLS_SERVER_NAME');
			if ($serverName === '') {
				// Host part of the address, as resolveTLSServerName() in the Go example
				$serverName = (string)(parse_url('//' . $address, PHP_URL_HOST) ?? $address);
				$serverName = trim($serverName, '[]');
			}
			$tls = new TlsConfig(caCert: $caCert, serverName: $serverName, minVersion: TlsConfig::MIN_VERSION_1_2);
		}

		$auth = new AuthConfig();
		if (getenv('CITIUS_AUTH') === '1') {
			$role = (string)(getenv('CITIUS_ROLE') ?: 'ADMIN');
			$tokenFile = (string)(getenv('CITIUS_AUTH_TOKEN_FILE') ?: getenv("SVC_{$role}_TOKEN_FILE"));
			$this->assertNotSame('', $tokenFile, "Authentication is enabled but neither CITIUS_AUTH_TOKEN_FILE nor SVC_{$role}_TOKEN_FILE is set");
			$auth = new AuthConfig(enabled: true, tokenSource: $tokenFile);
		}

		return new ServiceConfig($address, $tls, $auth, '30s');
	}

	/**
	 * Waits for a unary call and fails the test on a non-OK status.
	 */
	private function call(string $step, UnaryCall $call): mixed {
		[$response, $status] = $call->wait();
		$this->assertSame(\Grpc\STATUS_OK, $status->code, sprintf('%s failed: %s', $step, $status->details));
		return $response;
	}
}
