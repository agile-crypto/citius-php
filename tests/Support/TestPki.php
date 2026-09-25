<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Support;

/**
 * Throwaway PKI for tests, written to a temporary directory: a server CA and a server certificate for
 * "*.test.example.com", a separate client CA and client certificate, and an unrelated key.
 */
final class TestPki {
	public const SERVER_NAME = 'citius.test.example.com';

	public readonly string $dir;

	public function __construct() {
		$this->dir = sys_get_temp_dir() . '/citius-pki-' . bin2hex(random_bytes(4));
		mkdir($this->dir);
		$config = $this->writeOpensslConfig();

		[$caCert, $caKey] = $this->selfSigned('/CN=test-server-ca', $config);
		[$clientCaCert, $clientCaKey] = $this->selfSigned('/CN=test-client-ca', $config);
		[$serverCert, $serverKey] = $this->issued('/CN=test-server', $caCert, $caKey, $config, 'server_cert');
		[$clientCert, $clientKey] = $this->issued('/CN=test-client', $clientCaCert, $clientCaKey, $config, 'client_cert');

		$this->write('ca_cert.pem', $caCert);
		$this->write('client_ca_cert.pem', $clientCaCert);
		$this->write('server_cert.pem', $serverCert);
		$this->write('server_key.pem', $serverKey);
		$this->write('client_cert.pem', $clientCert);
		$this->write('client_key.pem', $clientKey);
		$this->write('other_key.pem', $this->exportKey($this->newKey()));
		$this->write('not_a_cert.pem', "-----BEGIN CERTIFICATE-----\nbm90IGEgY2VydGlmaWNhdGU=\n-----END CERTIFICATE-----\n");
	}

	public function path(string $file): string {
		return $this->dir . '/' . $file;
	}

	public function read(string $file): string {
		return (string)file_get_contents($this->path($file));
	}

	public function remove(): void {
		array_map('unlink', glob($this->dir . '/*') ?: []);
		@rmdir($this->dir);
	}

	/**
	 * @return array{string, \OpenSSLAsymmetricKey}
	 */
	private function selfSigned(string $subject, string $config): array {
		$key = $this->newKey();
		$csr = openssl_csr_new($this->dn($subject), $key, ['config' => $config, 'digest_alg' => 'sha256']);
		$cert = openssl_csr_sign($csr, null, $key, 1, ['config' => $config, 'x509_extensions' => 'ca_cert', 'digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX));
		openssl_x509_export($cert, $pem);
		return [$pem, $key];
	}

	/**
	 * @return array{string, string}
	 */
	private function issued(string $subject, string $caCert, \OpenSSLAsymmetricKey $caKey, string $config, string $extensions): array {
		$key = $this->newKey();
		$csr = openssl_csr_new($this->dn($subject), $key, ['config' => $config, 'digest_alg' => 'sha256']);
		$cert = openssl_csr_sign($csr, $caCert, $caKey, 1, ['config' => $config, 'x509_extensions' => $extensions, 'digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX));
		openssl_x509_export($cert, $pem);
		return [$pem, $this->exportKey($key)];
	}

	private function newKey(): \OpenSSLAsymmetricKey {
		return openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
	}

	private function exportKey(\OpenSSLAsymmetricKey $key): string {
		openssl_pkey_export($key, $pem);
		return $pem;
	}

	/**
	 * @return array<string, string>
	 */
	private function dn(string $subject): array {
		return ['commonName' => substr($subject, 4)];
	}

	private function writeOpensslConfig(): string {
		$path = $this->path('openssl.cnf');
		file_put_contents($path, <<<CNF
			[req]
			distinguished_name = dn
			[dn]
			[ca_cert]
			basicConstraints = critical,CA:TRUE
			keyUsage = critical,keyCertSign
			subjectKeyIdentifier = hash
			[server_cert]
			basicConstraints = critical,CA:FALSE
			keyUsage = critical,digitalSignature,keyAgreement
			extendedKeyUsage = serverAuth
			subjectAltName = DNS:*.test.example.com
			[client_cert]
			basicConstraints = critical,CA:FALSE
			keyUsage = critical,digitalSignature
			extendedKeyUsage = clientAuth
			CNF);
		return $path;
	}

	private function write(string $file, string $content): void {
		file_put_contents($this->path($file), $content);
	}
}
