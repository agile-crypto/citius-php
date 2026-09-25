<?php

declare(strict_types=1);

namespace Citius\Client\Tests\Unit\Auth;

use Citius\Client\Auth\AuthInterceptor;
use Citius\Client\Auth\StaticToken;
use Citius\Client\Auth\TokenSource;
use Citius\Client\Exception\ConfigException;
use Citius\Client\Exception\TokenException;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
	private string $tokenFile;

	#[\Override]
	protected function setUp(): void {
		$this->tokenFile = (string)tempnam(sys_get_temp_dir(), 'citius-token');
	}

	#[\Override]
	protected function tearDown(): void {
		@unlink($this->tokenFile);
	}

	public function testStaticToken(): void {
		$this->assertSame('secret', (new StaticToken('secret'))->token());
	}

	public function testTokenSourceTrimsWhitespace(): void {
		file_put_contents($this->tokenFile, "  from-file\n");

		$this->assertSame('from-file', (new TokenSource($this->tokenFile))->token());
	}

	public function testTokenSourceReadsOnEveryCall(): void {
		$source = new TokenSource($this->tokenFile);

		file_put_contents($this->tokenFile, 'first');
		$this->assertSame('first', $source->token());
		file_put_contents($this->tokenFile, 'rotated');
		$this->assertSame('rotated', $source->token());
	}

	public function testTokenSourceMissingFileFails(): void {
		$this->expectException(ConfigException::class);
		new TokenSource($this->tokenFile . '.missing');
	}

	public function testTokenSourceDeletedAfterConstructionFails(): void {
		$source = new TokenSource($this->tokenFile);
		unlink($this->tokenFile);

		$this->expectException(TokenException::class);
		$source->token();
	}

	public function testInterceptorAddsBearerTokenToUnaryCalls(): void {
		$interceptor = new AuthInterceptor(new StaticToken('secret'));

		$metadata = $interceptor->interceptUnaryUnary('/svc/Method', 'arg', null, self::captureMetadata(...), ['x-other' => ['1']], ['timeout' => 5]);

		$this->assertSame(['x-other' => ['1'], 'authorization' => ['Bearer secret']], $metadata);
	}

	public function testInterceptorAddsBearerTokenToStreamingCalls(): void {
		$interceptor = new AuthInterceptor(new StaticToken('secret'));
		$capture = static fn (string $method, mixed ...$args): array => $args[count($args) - 2];

		$expected = ['authorization' => ['Bearer secret']];
		$this->assertSame($expected, $interceptor->interceptStreamUnary('/m', null, $capture));
		$this->assertSame($expected, $interceptor->interceptUnaryStream('/m', 'arg', null, $capture));
		$this->assertSame($expected, $interceptor->interceptStreamStream('/m', null, $capture));
	}

	public function testInterceptorSkipsEmptyToken(): void {
		$interceptor = new AuthInterceptor(new StaticToken(''));

		$this->assertSame([], $interceptor->interceptUnaryUnary('/m', 'arg', null, self::captureMetadata(...)));
	}

	public function testInterceptorPropagatesTokenFailure(): void {
		$source = new TokenSource($this->tokenFile);
		unlink($this->tokenFile);
		$interceptor = new AuthInterceptor($source);

		$this->expectException(TokenException::class);
		$interceptor->interceptUnaryUnary('/m', 'arg', null, self::captureMetadata(...));
	}

	/**
	 * Continuation that returns the metadata it receives instead of performing a call.
	 *
	 * @return array<string, list<string>>
	 */
	private static function captureMetadata(string $method, mixed $argument, mixed $deserialize, array $metadata, array $options): array {
		return $metadata;
	}
}
