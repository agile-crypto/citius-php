<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// SPDX-License-Identifier: Apache-2.0
//
namespace Citius\Grpc\Crypto\V1;

/**
 * ============================================================================
 * Crypto Service - Single-Shot Cryptographic Operations
 * ============================================================================
 *
 * All operations are stateless, single request/response
 *
 * This is part of the service family:
 *   - KeyManagementService:     Key lifecycle (CRUD, rotate, transform, migrate)
 *   - CryptoPolicyService:      Policy lifecycle and evaluation
 *   - CryptoService:            Single-shot crypto operations (this service)
 *   - StreamingCryptoService:   Multi-part and message-based stateful operations
 *   - KeyEstablishmentService:  Key-to-key operations (wrap, derive, KEM, agreement)
 *   - AlgorithmDiscoveryService: Template and scope discovery
 *   - ProviderService:          Provider catalog and instance management
 *
 */
class CryptoServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\EncryptRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\EncryptResponse>
     */
    public function Encrypt(\Citius\Grpc\Crypto\V1\EncryptRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/Encrypt',
        $argument,
        ['\Citius\Grpc\Crypto\V1\EncryptResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\DecryptRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\DecryptResponse>
     */
    public function Decrypt(\Citius\Grpc\Crypto\V1\DecryptRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/Decrypt',
        $argument,
        ['\Citius\Grpc\Crypto\V1\DecryptResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== Signing ====================
     *
     * @param \Citius\Grpc\Crypto\V1\SignRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\SignResponse>
     */
    public function Sign(\Citius\Grpc\Crypto\V1\SignRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/Sign',
        $argument,
        ['\Citius\Grpc\Crypto\V1\SignResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\VerifyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\VerifyResponse>
     */
    public function Verify(\Citius\Grpc\Crypto\V1\VerifyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/Verify',
        $argument,
        ['\Citius\Grpc\Crypto\V1\VerifyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Sign/verify pre-hashed data (for large files, external hashing)
     * @param \Citius\Grpc\Crypto\V1\DigestSignRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\DigestSignResponse>
     */
    public function DigestSign(\Citius\Grpc\Crypto\V1\DigestSignRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/DigestSign',
        $argument,
        ['\Citius\Grpc\Crypto\V1\DigestSignResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\DigestVerifyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\DigestVerifyResponse>
     */
    public function DigestVerify(\Citius\Grpc\Crypto\V1\DigestVerifyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/DigestVerify',
        $argument,
        ['\Citius\Grpc\Crypto\V1\DigestVerifyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== MAC Operations ====================
     *
     * @param \Citius\Grpc\Crypto\V1\GenerateMACRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\GenerateMACResponse>
     */
    public function GenerateMAC(\Citius\Grpc\Crypto\V1\GenerateMACRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/GenerateMAC',
        $argument,
        ['\Citius\Grpc\Crypto\V1\GenerateMACResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\VerifyMACRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\VerifyMACResponse>
     */
    public function VerifyMAC(\Citius\Grpc\Crypto\V1\VerifyMACRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/VerifyMAC',
        $argument,
        ['\Citius\Grpc\Crypto\V1\VerifyMACResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== Digest Operations ====================
     *
     * @param \Citius\Grpc\Crypto\V1\DigestRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\DigestResponse>
     */
    public function Digest(\Citius\Grpc\Crypto\V1\DigestRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/Digest',
        $argument,
        ['\Citius\Grpc\Crypto\V1\DigestResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== XOF (Extendable Output Function) ====================
     * XOFs produce variable-length output (SHAKE128, SHAKE256, BLAKE3, etc.)
     * Separated from digest because they are a distinct primitive (CRYPTO_PRIMITIVE_XOF)
     * with caller-specified output length.
     *
     * @param \Citius\Grpc\Crypto\V1\XofRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\XofResponse>
     */
    public function Xof(\Citius\Grpc\Crypto\V1\XofRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/Xof',
        $argument,
        ['\Citius\Grpc\Crypto\V1\XofResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== Random Number Generation ====================
     *
     * @param \Citius\Grpc\Crypto\V1\GenerateRandomRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\GenerateRandomResponse>
     */
    public function GenerateRandom(\Citius\Grpc\Crypto\V1\GenerateRandomRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/GenerateRandom',
        $argument,
        ['\Citius\Grpc\Crypto\V1\GenerateRandomResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\SeedRandomRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\SeedRandomResponse>
     */
    public function SeedRandom(\Citius\Grpc\Crypto\V1\SeedRandomRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.CryptoService/SeedRandom',
        $argument,
        ['\Citius\Grpc\Crypto\V1\SeedRandomResponse', 'decode'],
        $metadata, $options);
    }

}
