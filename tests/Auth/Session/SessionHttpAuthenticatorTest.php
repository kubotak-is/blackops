<?php

declare(strict_types=1);

namespace BlackOps\Tests\Auth\Session;

use BlackOps\Auth\Session\BearerSessionAuthenticator;
use BlackOps\Auth\Session\CookieSessionAuthenticator;
use BlackOps\Auth\Session\IssuedSession;
use BlackOps\Auth\Session\SessionCookieName;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\ActorRef;
use BlackOps\Http\Authentication\AuthenticationResult;
use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;

final class SessionHttpAuthenticatorTest extends TestCase
{
    public function testBearerWithoutAuthorizationHeaderIsAnonymousWithoutLookup(): void
    {
        $sessions = new HttpSessionManager();
        $result = $this->bearer($sessions)->authenticate(new ServerRequest('GET', '/'));

        self::assertTrue($result->isAnonymous());
        self::assertSame(0, $sessions->calls);
    }

    public function testBearerRejectsMultipleHeadersWrongSchemeAndMalformedTokenUniformly(): void
    {
        $sessions = new HttpSessionManager();
        $authenticator = $this->bearer($sessions);
        $requests = [
            new ServerRequest('GET', '/')->withHeader('Authorization', [
                'Bearer ' . str_repeat(string: 'A', times: 43),
                'x',
            ]),
            new ServerRequest('GET', '/')->withHeader('Authorization', 'Basic credential'),
            new ServerRequest('GET', '/')->withHeader('Authorization', 'Bearer malformed'),
        ];

        foreach ($requests as $request) {
            $result = $authenticator->authenticate($request);
            self::assertInvalid($result);
        }

        self::assertSame(1, $sessions->calls);
    }

    public function testBearerReturnsActorOnlyWhenSessionAndCurrentIdentityResolve(): void
    {
        $sessions = new HttpSessionManager(new ActorRef('actor-1', 'user'));
        $result = $this->bearer($sessions)->authenticate(new ServerRequest('GET', '/')->withHeader(
            'Authorization',
            'Bearer ' . str_repeat(string: 'A', times: 43),
        ));

        self::assertTrue($result->isAuthenticated());
        self::assertSame('actor-1', $result->actor()?->id());
        self::assertSame('user', $result->actor()?->type());
    }

    public function testUnknownExpiredRevokedRotatedAndMissingIdentityShareOneSurface(): void
    {
        $request = new ServerRequest('GET', '/')->withHeader(
            'Authorization',
            'Bearer ' . str_repeat(string: 'A', times: 43),
        );

        self::assertInvalid($this->bearer(new HttpSessionManager())->authenticate($request));
        self::assertInvalid($this->bearer(new HttpSessionManager())->authenticate($request));
    }

    public function testInfrastructureAndIdentityProviderFailuresPropagate(): void
    {
        $request = new ServerRequest('GET', '/')->withHeader(
            'Authorization',
            'Bearer ' . str_repeat(string: 'A', times: 43),
        );

        try {
            $this->bearer(new HttpSessionManager(failure: true))->authenticate($request);
            self::fail('Expected session infrastructure failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('database unavailable', $exception->getMessage());
        }

        $this->expectExceptionMessage('identity provider unavailable');
        $this->bearer(new HttpSessionManager(identityFailure: true))->authenticate($request);
    }

    public function testCookieWithoutConfiguredCookieIsAnonymousAndDoesNotReadOtherCookies(): void
    {
        $sessions = new HttpSessionManager();
        $result = $this->cookie($sessions)->authenticate(new ServerRequest(
            'GET',
            '/',
        )->withCookieParams(['other' => str_repeat(string: 'A', times: 43)]));

        self::assertTrue($result->isAnonymous());
        self::assertSame(0, $sessions->calls);
    }

    public function testCookieRejectsMalformedAndAuthenticatesCanonicalToken(): void
    {
        $sessions = new HttpSessionManager(new ActorRef('actor-1', 'user'));
        $authenticator = $this->cookie($sessions);

        self::assertInvalid($authenticator->authenticate(new ServerRequest('GET', '/')->withCookieParams([
            'blackops_session' => 'malformed',
        ])));
        self::assertInvalid($authenticator->authenticate(new ServerRequest(
            'GET',
            '/',
        )->withCookieParams(['blackops_session' => ['not-a-string']])));

        $result = $authenticator->authenticate(new ServerRequest('GET', '/')->withCookieParams([
            'blackops_session' => str_repeat(string: 'A', times: 43),
        ]));

        self::assertTrue($result->isAuthenticated());
        self::assertSame('actor-1', $result->actor()?->id());
    }

    private function bearer(HttpSessionManager $sessions): BearerSessionAuthenticator
    {
        return new BearerSessionAuthenticator($sessions);
    }

    private function cookie(HttpSessionManager $sessions): CookieSessionAuthenticator
    {
        return new CookieSessionAuthenticator($sessions, new SessionCookieName('blackops_session'));
    }

    private static function assertInvalid(AuthenticationResult $result): void
    {
        self::assertTrue($result->isInvalid());
        self::assertSame('authentication.invalid_session', $result->code());
        self::assertNull($result->actor());
    }
}

/** @mago-expect lint:single-class-per-file */
final class HttpSessionManager implements SessionManager
{
    public int $calls = 0;

    public function __construct(
        private readonly ?ActorRef $actor = null,
        private readonly bool $failure = false,
        private readonly bool $identityFailure = false,
    ) {}

    public function authenticate(#[SensitiveParameter] string $rawToken): ?ActorRef
    {
        $this->calls++;

        if ($this->failure) {
            throw new RuntimeException('database unavailable');
        }

        if ($this->identityFailure) {
            throw new RuntimeException('identity provider unavailable');
        }

        return preg_match('/^[A-Za-z0-9_-]{43}$/D', $rawToken) === 1 ? $this->actor : null;
    }

    public function issue(string $identityId): IssuedSession
    {
        throw new RuntimeException('not used');
    }

    public function rotate(#[SensitiveParameter] string $rawToken): IssuedSession
    {
        throw new RuntimeException('not used');
    }

    public function revoke(#[SensitiveParameter] ?string $rawToken): void {}

    public function cleanup(DateTimeImmutable $retentionCutoff): int
    {
        return 0;
    }
}
