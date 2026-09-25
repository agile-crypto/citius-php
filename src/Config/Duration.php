<?php

declare(strict_types=1);

namespace Citius\Client\Config;

use Citius\Client\Exception\ConfigException;

/**
 * Parser for Go duration strings (time.ParseDuration), used by the "timeout" setting.
 */
final class Duration {
	private const NANOSECONDS_PER_UNIT = [
		'ns' => 1,
		'us' => 1_000,
		'µs' => 1_000,
		'μs' => 1_000,
		'ms' => 1_000_000,
		's' => 1_000_000_000,
		'm' => 60_000_000_000,
		'h' => 3_600_000_000_000,
	];

	/**
	 * Converts a duration such as "30s", "1m30s", "1.5h" or "250ms" to microseconds, the unit of gRPC PHP call timeouts.
	 * Sub-microsecond remainders are rounded up, so a non-zero duration never becomes 0.
	 *
	 * @throws ConfigException on invalid syntax
	 */
	public static function toMicroseconds(string $duration): int {
		if ($duration === '0') {
			return 0;
		}
		$units = implode('|', array_map(static fn (string $u): string => preg_quote($u, '/'), array_keys(self::NANOSECONDS_PER_UNIT)));
		$component = '(\d+(?:\.\d*)?|\.\d+)(' . $units . ')';
		if (preg_match('/^([+-]?)((?:' . $component . ')+)$/u', $duration, $match) !== 1) {
			throw new ConfigException(sprintf('invalid duration "%s": expected e.g. "30s", "1m30s" or "500ms"', $duration));
		}
		preg_match_all('/' . $component . '/u', $match[2], $components, PREG_SET_ORDER);

		$nanoseconds = 0.0;
		foreach ($components as [, $value, $unit]) {
			$nanoseconds += (float)$value * self::NANOSECONDS_PER_UNIT[$unit];
		}
		$microseconds = (int)ceil($nanoseconds / 1_000);
		return $match[1] === '-' ? -$microseconds : $microseconds;
	}
}
