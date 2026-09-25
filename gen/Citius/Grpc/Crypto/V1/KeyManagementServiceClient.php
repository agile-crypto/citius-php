<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// SPDX-License-Identifier: Apache-2.0
//
namespace Citius\Grpc\Crypto\V1;

/**
 * ============================================================================
 * Key Management Service - Key Lifecycle Operations
 * ============================================================================
 *
 * This service manages the lifecycle of cryptographic keys:
 *   - CRUD operations (create, read, list, delete)
 *   - Lifecycle operations (rotate, transform, migrate)
 *   - Import/export for interoperability
 *   - Policy binding (UpdateKeyPolicy)
 *   - Pre-flight validation (ValidateKeyOperation)
 *
 * Resource: Keys (/v1/keys/*)
 * Persona:  Key administrators
 * IAM scope: keys.create, keys.read, keys.delete, keys.rotate,
 *            keys.transform, keys.migrate, keys.export, keys.import
 *
 * All key creation supports two selection modes:
 *   - Scope-based (policy determines template) — recommended
 *   - Explicit template_id (discovered via AlgorithmDiscoveryService)
 *
 *
 */
class KeyManagementServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\CreateKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\CreateKeyResponse>
     */
    public function CreateKey(\Citius\Grpc\Crypto\V1\CreateKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/CreateKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\CreateKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\ReadKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ReadKeyResponse>
     */
    public function ReadKey(\Citius\Grpc\Crypto\V1\ReadKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/ReadKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ReadKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\ListKeysRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ListKeysResponse>
     */
    public function ListKeys(\Citius\Grpc\Crypto\V1\ListKeysRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/ListKeys',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ListKeysResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\DeleteKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\DeleteKeyResponse>
     */
    public function DeleteKey(\Citius\Grpc\Crypto\V1\DeleteKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/DeleteKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\DeleteKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== Key Lifecycle ====================
     *
     * @param \Citius\Grpc\Crypto\V1\RotateKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\RotateKeyResponse>
     */
    public function RotateKey(\Citius\Grpc\Crypto\V1\RotateKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/RotateKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\RotateKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * TransformKey changes a key's algorithm while preserving its identity.
     * The key enabler for cryptographic agility.
     *   retain_key_bytes=true:  Same material, different operation properties
     *   retain_key_bytes=false: Regenerate material (required for algorithm family change)
     * @param \Citius\Grpc\Crypto\V1\TransformKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\TransformKeyResponse>
     */
    public function TransformKey(\Citius\Grpc\Crypto\V1\TransformKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/TransformKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\TransformKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * UpdateKeyState performs an explicit, auditable lifecycle state transition
     * (activate, suspend, reactivate, deactivate, mark compromised) per
     * NIST SP 800-57 Part 1 §7. Destruction goes through DeleteKey.
     * @param \Citius\Grpc\Crypto\V1\UpdateKeyStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\UpdateKeyStateResponse>
     */
    public function UpdateKeyState(\Citius\Grpc\Crypto\V1\UpdateKeyStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/UpdateKeyState',
        $argument,
        ['\Citius\Grpc\Crypto\V1\UpdateKeyStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * UpdateKeyPolicy modifies the crypto policy binding for a key.
     * This is a sub-resource operation on the key, not on the policy resource.
     * @param \Citius\Grpc\Crypto\V1\UpdateKeyPolicyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\UpdateKeyPolicyResponse>
     */
    public function UpdateKeyPolicy(\Citius\Grpc\Crypto\V1\UpdateKeyPolicyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/UpdateKeyPolicy',
        $argument,
        ['\Citius\Grpc\Crypto\V1\UpdateKeyPolicyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * MigrateKey - Move key to different provider with explicit strategy
     *
     * Moves a key to a different provider with explicit migration strategy.
     * User must specify how key material is handled during migration.
     *
     * Strategies:
     *   PROVIDER_SWITCH: SW→SW metadata-only change
     *   EXTRACT_AND_IMPORT: Key crosses security boundary
     *   WRAPPED_TRANSFER: HSM→HSM with maximum security
     *   REKEY_AND_ARCHIVE: New key, archive old (always works)
     *   REKEY_AND_DESTROY: New key, destroy old (careful!)
     *
     * See docs/TRANSFORM_KEY_PROVIDER_DESIGN.md for decision tree.
     * @param \Citius\Grpc\Crypto\V1\MigrateKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\MigrateKeyResponse>
     */
    public function MigrateKey(\Citius\Grpc\Crypto\V1\MigrateKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/MigrateKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\MigrateKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ValidateKeyOperation - Pre-flight check for key operations
     *
     * Returns which strategies are feasible for a given key and target.
     * Use before MigrateKey to show users available options.
     * @param \Citius\Grpc\Crypto\V1\ValidateKeyOperationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ValidateKeyOperationResponse>
     */
    public function ValidateKeyOperation(\Citius\Grpc\Crypto\V1\ValidateKeyOperationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/ValidateKeyOperation',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ValidateKeyOperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ==================== Key Import/Export ====================
     *
     * @param \Citius\Grpc\Crypto\V1\ExportKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ExportKeyResponse>
     */
    public function ExportKey(\Citius\Grpc\Crypto\V1\ExportKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/ExportKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ExportKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Citius\Grpc\Crypto\V1\ImportKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Citius\Grpc\Crypto\V1\ImportKeyResponse>
     */
    public function ImportKey(\Citius\Grpc\Crypto\V1\ImportKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/caas.crypto.v1.KeyManagementService/ImportKey',
        $argument,
        ['\Citius\Grpc\Crypto\V1\ImportKeyResponse', 'decode'],
        $metadata, $options);
    }

}
