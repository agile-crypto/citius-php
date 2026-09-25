<?php

declare(strict_types=1);

namespace Citius\Client\Config;

/**
 * Citius services, identified by their key in the YAML configuration.
 */
enum Service: string {
	case KeyManagement = 'key_management';
	case Crypto = 'crypto';
	case CryptoPolicy = 'crypto_policy';
	case Discovery = 'discovery';
	case KeyEstablishment = 'key_establishment';
	case Provider = 'provider';
	case StreamingCrypto = 'streaming_crypto';
}
