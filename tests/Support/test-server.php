<?php

declare(strict_types=1);

/**
 * Local gRPC server for the integration tests.
 *
 * Usage: php test-server.php <insecure|tls|mtls> <port> <pki dir> [expected token]
 *   tls:  server certificate from <pki dir>
 *   mtls: additionally requires a client certificate issued by <pki dir>/client_ca_cert.pem
 */

use Citius\Client\Tests\Support\StubCryptoPolicyService;
use Citius\Client\Tests\Support\StubCryptoService;
use Grpc\RpcServer;
use Grpc\ServerCredentials;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[, $mode, $port, $pkiDir] = $argv;
$token = $argv[4] ?? '';
$address = '127.0.0.1:' . $port;

$server = new RpcServer();
$read = static fn (string $file): string => (string)file_get_contents($pkiDir . '/' . $file);
match ($mode) {
	'insecure' => $server->addHttp2Port($address),
	'tls' => $server->addSecureHttp2Port($address, ServerCredentials::createSsl(null, $read('server_key.pem'), $read('server_cert.pem'))),
	'mtls' => $server->addSecureHttp2Port($address, ServerCredentials::createSsl($read('client_ca_cert.pem'), $read('server_key.pem'), $read('server_cert.pem'))),
};
$server->handle(new StubCryptoService($token));
$server->handle(new StubCryptoPolicyService());
$server->run();
