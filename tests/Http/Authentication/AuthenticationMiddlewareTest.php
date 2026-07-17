<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Http\Authentication\AuthenticationMiddleware;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Internal\Http\HttpActorRequestAttribute;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class AuthenticationMiddlewareTest extends TestCase
{
    public function testAnonymousPassesUnchangedRequestWithoutActor(): void
    {
        $request = new Psr17Factory()->createServerRequest('GET', '/');
        $handler = new CapturingAuthenticationHandler();
        $middleware = new AuthenticationMiddleware(new FixedHttpAuthenticator(AuthenticationResult::anonymous()));

        $response = $middleware->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame($request, $handler->request);
        self::assertNull(HttpActorRequestAttribute::actor($handler->request));
    }

    public function testAuthenticatedPassesOnlyActorInReservedAttribute(): void
    {
        $actor = new ActorRef('user-123', 'user');
        $request = new Psr17Factory()
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer credential-that-must-stay-in-request');
        $handler = new CapturingAuthenticationHandler();
        $middleware = new AuthenticationMiddleware(new FixedHttpAuthenticator(AuthenticationResult::authenticated(
            $actor,
        )));

        $middleware->process($request, $handler);

        self::assertNotSame($request, $handler->request);
        self::assertSame($actor, HttpActorRequestAttribute::actor($handler->request));
        self::assertSame(
            ['Bearer credential-that-must-stay-in-request'],
            $handler->request?->getHeader('Authorization'),
        );
    }

    public function testInvalidReturnsSafeJson401WithoutCallingDownstream(): void
    {
        $credential = 'credential-that-must-not-appear';
        $request = new Psr17Factory()
            ->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'Bearer ' . $credential);
        $handler = new CapturingAuthenticationHandler();
        $middleware = new AuthenticationMiddleware(new FixedHttpAuthenticator(AuthenticationResult::invalid(
            'authentication.invalid',
        )));

        $response = $middleware->process($request, $handler);
        $body = (string) $response->getBody();

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['status' => 'error', 'category' => 'unauthorized', 'code' => 'authentication.invalid'],
            json_decode($body, true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString($credential, $body);
        self::assertStringNotContainsString('operation_id', $body);
        self::assertNull($handler->request);
    }

    public function testAuthenticatorExceptionPropagates(): void
    {
        $middleware = new AuthenticationMiddleware(new ThrowingHttpAuthenticator());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('authentication backend unavailable');

        $middleware->process(new Psr17Factory()->createServerRequest('GET', '/'), new CapturingAuthenticationHandler());
    }
}

final readonly class FixedHttpAuthenticator implements HttpAuthenticator
{
    public function __construct(
        private AuthenticationResult $result,
    ) {}

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        return $this->result;
    }
}

final readonly class ThrowingHttpAuthenticator implements HttpAuthenticator
{
    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        throw new RuntimeException('authentication backend unavailable');
    }
}

final class CapturingAuthenticationHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Psr17Factory()->createResponse(204);
    }
}
