<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// SPDX-License-Identifier: Apache-2.0
//
namespace Citius\Grpc\Crypto\V1;

/**
 * ============================================================================
 * Crypto Policy Service - Policy Lifecycle and Evaluation
 * ============================================================================
 *
 * This service manages cryptographic policies and provides policy evaluation:
 *   - CRUD operations on crypto policies (create, read, update, delete, list)
 *   - Policy evaluation (pre-flight checks, batch evaluation)
 *
 * Crypto policies govern which algorithms/templates are allowed for a given
 * scope, enforce security level requirements (e.g., 256-bit minimum),
 * and apply compliance constraints (e.g., FIPS-approved only).
 *
 * Note: This service manages the POLICY RESOURCE. To change which policy
 * is bound to a specific key, use KeyManagementService.UpdateKeyPolicy.
 *
 */
class CryptoPolicyServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\CreateCryptoPolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\CreateCryptoPolicyResponse>
     */
    public function CreateCryptoPolicy(\Citius\Grpc\Crypto\V1\CreateCryptoPolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/CreateCryptoPolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\CreateCryptoPolicyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\ReadCryptoPolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ReadCryptoPolicyResponse>
     */
    public function ReadCryptoPolicy(\Citius\Grpc\Crypto\V1\ReadCryptoPolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/ReadCryptoPolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ReadCryptoPolicyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\DeleteCryptoPolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\DeleteCryptoPolicyResponse>
     */
    public function DeleteCryptoPolicy(\Citius\Grpc\Crypto\V1\DeleteCryptoPolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/DeleteCryptoPolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\DeleteCryptoPolicyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\UpdateCryptoPolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\UpdateCryptoPolicyResponse>
     */
    public function UpdateCryptoPolicy(\Citius\Grpc\Crypto\V1\UpdateCryptoPolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/UpdateCryptoPolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\UpdateCryptoPolicyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\ListCryptoPoliciesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ListCryptoPoliciesResponse>
     */
    public function ListCryptoPolicies(\Citius\Grpc\Crypto\V1\ListCryptoPoliciesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/ListCryptoPolicies',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ListCryptoPoliciesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== Policy Evaluation ====================
     *
     * EvaluatePolicy checks if an operation would be allowed by policy.
     * This is a read-only operation that does not perform any cryptographic work.
     * Useful for:
     *   - Pre-flight authorization checks (can I encrypt with this key?)
     *   - UI enablement (should the "Sign" button be enabled?)
     *   - Compliance queries (what operations are allowed?)
     * @param \Citius\Grpc\Crypto\V1\EvaluatePolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\EvaluatePolicyResponse>
     */
    public function EvaluatePolicy(\Citius\Grpc\Crypto\V1\EvaluatePolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/EvaluatePolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\EvaluatePolicyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * BatchEvaluatePolicy evaluates multiple policy decisions in one call.
     * Useful for checking multiple operations/keys at once (e.g., UI initialization).
     * @param \Citius\Grpc\Crypto\V1\BatchEvaluatePolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\BatchEvaluatePolicyResponse>
     */
    public function BatchEvaluatePolicy(\Citius\Grpc\Crypto\V1\BatchEvaluatePolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoPolicyService/BatchEvaluatePolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\BatchEvaluatePolicyResponse', 'decode'],
        $metadata, $options);
    }

}
