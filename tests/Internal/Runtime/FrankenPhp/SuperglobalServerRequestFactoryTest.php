<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime\FrankenPhp;

use BlackOps\Internal\Runtime\FrankenPhp\SuperglobalServerRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class SuperglobalServerRequestFactoryTest extends TestCase
{
    public function testCreatesGetRequestWithUriQueryCookiesHeadersAndProtocol(): void
    {
        $factory = new Psr17Factory();
        $request = new SuperglobalServerRequestFactory($factory, $factory)->create(
            [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.test:8080',
                'REQUEST_URI' => '/reports?state=ready',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => 'request-1',
            ],
            ['state' => 'ready'],
            ['session' => 'cookie-value'],
            '',
        );

        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://example.test:8080/reports?state=ready', (string) $request->getUri());
        self::assertSame(['state' => 'ready'], $request->getQueryParams());
        self::assertSame(['session' => 'cookie-value'], $request->getCookieParams());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('request-1', $request->getHeaderLine('X-Request-Id'));
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('', (string) $request->getBody());
    }

    public function testCreatesHttpsPostRequestWithJsonBodyAndContentHeaders(): void
    {
        $factory = new Psr17Factory();
        $body = '{"report":"weekly"}';
        $request = new SuperglobalServerRequestFactory($factory, $factory)->create(
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'api.example.test',
                'REQUEST_URI' => '/reports',
                'HTTPS' => 'on',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => (string) strlen($body),
            ],
            [],
            [],
            $body,
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https', $request->getUri()->getScheme());
        self::assertSame('api.example.test', $request->getUri()->getHost());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($body), $request->getHeaderLine('Content-Length'));
        self::assertSame($body, (string) $request->getBody());
    }

    public function testInfersHttpsFromRequestSchemeAndPort(): void
    {
        $factory = new Psr17Factory();
        $adapter = new SuperglobalServerRequestFactory($factory, $factory);

        $schemeRequest = $adapter->create(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REQUEST_SCHEME' => 'https'],
            [],
            [],
            '',
        );
        $portRequest = $adapter->create(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SERVER_PORT' => '443'],
            [],
            [],
            '',
        );

        self::assertSame('https', $schemeRequest->getUri()->getScheme());
        self::assertSame('https', $portRequest->getUri()->getScheme());
    }

    public function testRejectsMissingRequestMethod(): void
    {
        $factory = new Psr17Factory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('method is missing');

        new SuperglobalServerRequestFactory($factory, $factory)->create([], [], [], '');
    }

    public function testPreservesRawServerParamsAndSkipsNonStringHeaderValue(): void
    {
        $factory = new Psr17Factory();
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/healthz',
            'HTTP_HOST' => 'example.test',
            'APP_OPTIONS' => ['x'],
            'HTTP_X_INVALID' => ['not-a-header-value'],
        ];

        $request = new SuperglobalServerRequestFactory($factory, $factory)->create($server, [], [], '');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://example.test/healthz', (string) $request->getUri());
        self::assertSame(['x'], $request->getServerParams()['APP_OPTIONS']);
        self::assertSame(['not-a-header-value'], $request->getServerParams()['HTTP_X_INVALID']);
        self::assertFalse($request->hasHeader('X-Invalid'));
    }
}
