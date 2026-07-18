<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics\Viewer;

use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRequestException;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRequestParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationViewerRequestParserTest extends TestCase
{
    public function testParsesOnlyTheSmallHttpRequestSurface(): void
    {
        $request = new OperationViewerRequestParser()->parse($this->stream(
            "GET /operations/id HTTP/1.1\r\nHost: 127.0.0.1:8082\r\nCookie: session=value\r\n\r\n",
        ));

        self::assertSame('GET', $request->method);
        self::assertSame('/operations/id', $request->target);
        self::assertSame('127.0.0.1:8082', $request->headers['host']);
    }

    #[DataProvider('invalidRequestProvider')]
    public function testRejectsMalformedBodiesUpgradesAndOversizedInputs(string $request): void
    {
        $this->expectException(OperationViewerRequestException::class);
        new OperationViewerRequestParser()->parse($this->stream($request));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRequestProvider(): iterable
    {
        yield 'lf only' => ["GET / HTTP/1.1\nHost: 127.0.0.1:8082\n\n"];
        yield 'missing host' => ["GET / HTTP/1.1\r\n\r\n"];
        yield 'body' => ["GET / HTTP/1.1\r\nHost: 127.0.0.1:8082\r\nContent-Length: 1\r\n\r\nx"];
        yield 'chunked' => ["GET / HTTP/1.1\r\nHost: 127.0.0.1:8082\r\nTransfer-Encoding: chunked\r\n\r\n"];
        yield 'upgrade' => ["GET / HTTP/1.1\r\nHost: 127.0.0.1:8082\r\nUpgrade: websocket\r\n\r\n"];
        yield 'long line' => ['GET /' . str_repeat('a', 2100) . " HTTP/1.1\r\n\r\n"];
        yield 'large headers' => ["GET / HTTP/1.1\r\nHost: " . str_repeat('a', 8200) . "\r\n\r\n"];
    }

    /** @return resource */
    private function stream(string $request): mixed
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $request);
        rewind($stream);

        return $stream;
    }
}
