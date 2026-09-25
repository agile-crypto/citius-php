<?php

declare(strict_types=1);

namespace Citius\Client\Config;

use Citius\Client\Exception\ConfigException;

/**
 * Typed access to a decoded YAML mapping. Missing and null keys read as the type's zero value,
 * unknown keys are ignored.
 *
 * @internal
 */
final class ArrayReader {
	/**
	 * @param array<mixed> $data
	 * @param string $path location of $data in the document, for error messages
	 */
	public function __construct(
		private readonly array $data,
		private readonly string $path,
	) {
	}

	public function string(string $key): string {
		$value = $this->data[$key] ?? null;
		if ($value === null) {
			return '';
		}
		if (is_string($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return (string)$value;
		}
		throw new ConfigException(sprintf('%s: expected a string, got %s', $this->key($key), get_debug_type($value)));
	}

	public function bool(string $key): bool {
		$value = $this->data[$key] ?? null;
		if ($value === null) {
			return false;
		}
		if (is_bool($value)) {
			return $value;
		}
		throw new ConfigException(sprintf('%s: expected a boolean, got %s', $this->key($key), get_debug_type($value)));
	}

	/**
	 * @return array<mixed>|null null when the key is missing or null
	 */
	public function map(string $key): ?array {
		$value = $this->data[$key] ?? null;
		if ($value === null || is_array($value)) {
			return $value;
		}
		throw new ConfigException(sprintf('%s: expected a mapping, got %s', $this->key($key), get_debug_type($value)));
	}

	public function key(string $key): string {
		return $this->path === '' ? $key : $this->path . '.' . $key;
	}
}
