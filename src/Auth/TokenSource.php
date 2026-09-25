<?php

declare(strict_types=1);

namespace Citius\Client\Auth;

use Citius\Client\Exception\ConfigException;
use Citius\Client\Exception\TokenException;

/**
 * Bearer token read from a file (YAML auth.token_source). The file is read on every request,
 * so a token rotated on disk is picked up without reconnecting.
 */
final class TokenSource implements TokenProvider {
	/**
	 * @throws ConfigException when the file does not exist
	 */
	public function __construct(
		private readonly string $path,
	) {
		if (!is_file($path)) {
			throw new ConfigException(sprintf('token source file does not exist: %s', $path));
		}
	}

	/**
	 * Surrounding whitespace, such as the trailing newline most editors add, is removed.
	 */
	#[\Override]
	public function token(): string {
		$token = @file_get_contents($this->path);
		if ($token === false) {
			throw new TokenException(sprintf('failed to read token from file %s', $this->path));
		}
		return trim($token);
	}
}
