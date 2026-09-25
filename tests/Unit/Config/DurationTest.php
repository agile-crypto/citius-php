<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Config;

use Citius\Client\Config\Duration;
use Citius\Client\Exception\ConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DurationTest extends TestCase {
	/**
	 * @return array<string, array{string, int}>
	 */
	public static function validDurations(): array {
		return [
			'zero' => ['0', 0],
			'seconds' => ['30s', 30_000_000],
			'minutes and seconds' => ['1m30s', 90_000_000],
			'fractional hours' => ['1.5h', 5_400_000_000],
			'milliseconds' => ['250ms', 250_000],
			'microseconds' => ['10us', 10],
			'micro sign' => ['10µs', 10],
			'nanoseconds round up' => ['1ns', 1],
			'leading dot' => ['.5s', 500_000],
			'plus sign' => ['+2s', 2_000_000],
			'negative' => ['-2s', -2_000_000],
			'zero with unit' => ['0s', 0],
		];
	}

	#[DataProvider('validDurations')]
	public function testToMicroseconds(string $duration, int $expected): void {
		$this->assertSame($expected, Duration::toMicroseconds($duration));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidDurations(): array {
		return [
			'no unit' => ['30'],
			'unknown unit' => ['30d'],
			'empty' => [''],
			'space' => ['30 s'],
			'unit only' => ['s'],
		];
	}

	#[DataProvider('invalidDurations')]
	public function testInvalidDurationFails(string $duration): void {
		$this->expectException(ConfigException::class);
		Duration::toMicroseconds($duration);
	}
}
