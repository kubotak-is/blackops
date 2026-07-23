<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime\FrankenPhp;

use BlackOps\Internal\Runtime\FrankenPhp\SapiResponseEmitter;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class SapiResponseEmitterTest extends TestCase
{
    public function testEmitsStatusMultipleHeaderValuesAndBody(): void
    {
        $statuses = [];
        $headers = [];
        $body = '';
        $emitter = new SapiResponseEmitter(
            static function (int $status) use (&$statuses): void {
                $statuses[] = $status;
            },
            static function (string $header) use (&$headers): void {
                $headers[] = $header;
            },
            static function (string $chunk) use (&$body): void {
                $body .= $chunk;
            },
        );

        $emitter->emit(
            new Response(
                201,
                ['Content-Type' => 'application/json', 'Set-Cookie' => ['first=1', 'second=2']],
                Stream::create('{"created":true}'),
            ),
            'POST',
        );

        self::assertSame([201], $statuses);
        self::assertSame(['Content-Type: application/json', 'Set-Cookie: first=1', 'Set-Cookie: second=2'], $headers);
        self::assertSame('{"created":true}', $body);
    }

    public function testHeadEmitsStatusAndHeadersWithoutBody(): void
    {
        $bodyCalls = 0;
        $emitter = new SapiResponseEmitter(
            static function (int $status): void {},
            static function (string $header): void {},
            static function (string $chunk) use (&$bodyCalls): void {
                ++$bodyCalls;
            },
        );

        $emitter->emit(new Response(200, ['Content-Type' => 'text/plain'], Stream::create('hidden')), 'HEAD');

        self::assertSame(0, $bodyCalls);
    }

    public function testEmitsLocationBeforeExplicitStatusAndBody(): void
    {
        $events = [];
        $emitter = new SapiResponseEmitter(
            static function (int $status) use (&$events): void {
                $events[] = 'status:' . $status;
            },
            static function (string $header) use (&$events): void {
                $events[] = 'header:' . $header;
            },
            static function (string $chunk) use (&$events): void {
                $events[] = 'body:' . $chunk;
            },
        );

        $emitter->emit(
            new Response(
                202,
                ['Location' => '/operations/019f0000-0000-7000-8000-000000000001'],
                Stream::create('{"status":"accepted"}'),
            ),
            'POST',
        );

        self::assertSame(
            [
                'header:Location: /operations/019f0000-0000-7000-8000-000000000001',
                'status:202',
                'body:{"status":"accepted"}',
            ],
            $events,
        );
    }

    public function testRejectsHeaderInjectionBeforeEmission(): void
    {
        $emissions = [];
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn(['X-Test' => ["safe\r\ninjected: true"]]);
        $emitter = new SapiResponseEmitter(
            static function (int $status) use (&$emissions): void {
                $emissions[] = 'status';
            },
            static function (string $header) use (&$emissions): void {
                $emissions[] = 'header';
            },
            static function (string $chunk) use (&$emissions): void {
                $emissions[] = 'body';
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid HTTP response header value');

        try {
            $emitter->emit($response, 'GET');
        } finally {
            self::assertSame([], $emissions);
        }
    }

    public function testPropagatesResponseEmissionFailure(): void
    {
        $emitter = new SapiResponseEmitter(static function (int $status): never {
            throw new RuntimeException('SAPI unavailable');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SAPI unavailable');

        $emitter->emit(new Response(), 'GET');
    }

    public function testRejectsNonStringHeaderNameWithoutPartialEmission(): void
    {
        $emissions = [];
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([0 => ['value']]);
        $emitter = new SapiResponseEmitter(
            static function (int $status) use (&$emissions): void {
                $emissions[] = 'status';
            },
            static function (string $header) use (&$emissions): void {
                $emissions[] = 'header';
            },
            static function (string $chunk) use (&$emissions): void {
                $emissions[] = 'body';
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-string HTTP response header name');

        try {
            $emitter->emit($response, 'GET');
        } finally {
            self::assertSame([], $emissions);
        }
    }

    public function testFailsWhenBodyStreamMakesNoProgress(): void
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willReturn('');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($stream);
        $emitter = new SapiResponseEmitter(
            static function (int $status): void {},
            static function (string $header): void {},
            static function (string $chunk): void {},
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('made no progress');

        $emitter->emit($response, 'GET');
    }
}
