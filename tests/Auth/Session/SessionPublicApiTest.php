<?php

declare(strict_types=1);

namespace BlackOps\Tests\Auth\Session;

use BlackOps\Auth\Session\BearerSessionAuthenticator;
use BlackOps\Auth\Session\CookieSessionAuthenticator;
use BlackOps\Auth\Session\InvalidSessionException;
use BlackOps\Auth\Session\IssuedSession;
use BlackOps\Auth\Session\RawSessionToken;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionCookieName;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Auth\Session\SessionServiceProvider;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SessionPublicApiTest extends TestCase
{
    /** @return iterable<class-string, array{class-string}> */
    public static function publicTypes(): iterable
    {
        foreach ([
            SessionIdentityProvider::class,
            SessionConfiguration::class,
            RawSessionToken::class,
            IssuedSession::class,
            SessionManager::class,
            InvalidSessionException::class,
            SessionCookieName::class,
            BearerSessionAuthenticator::class,
            CookieSessionAuthenticator::class,
            SessionServiceProvider::class,
        ] as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('publicTypes')]
    public function testSessionContractsAreMarkedAsPublicApi(string $type): void
    {
        self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
    }

    public function testConfigurationHasSecureDefaultsAndRejectsInvalidIntervals(): void
    {
        $configuration = new SessionConfiguration();

        self::assertSame(28_800, $configuration->ttlSeconds);
        self::assertSame(300, $configuration->touchIntervalSeconds);

        foreach ([[0, 1], [1, 0], [60, 61]] as [$ttl, $touch]) {
            try {
                new SessionConfiguration($ttl, $touch);
                self::fail('Expected invalid session configuration.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString((string) $ttl, $exception->getMessage());
            }
        }
    }

    public function testRawTokenUsesCanonicalBase64UrlAndMasksDebugOutput(): void
    {
        $token = RawSessionToken::fromRandomBytes(str_repeat(string: "\xFF", times: 32));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token->reveal());
        self::assertSame(['value' => '[sensitive]'], $token->__debugInfo());
        self::assertFalse(new ReflectionClass($token)->hasMethod('__toString'));
        self::assertFalse(is_a($token, \JsonSerializable::class));
        self::assertFalse(new ReflectionClass($token)->getConstructor()?->isPublic());
    }

    public function testRawTokenRejectsEntropyOfAnyOtherLengthWithoutEchoingIt(): void
    {
        $entropy = 'private-entropy';

        try {
            RawSessionToken::fromRandomBytes($entropy);
            self::fail('Expected invalid token entropy.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString($entropy, $exception->getMessage());
        }
    }

    #[DataProvider('invalidCookieNames')]
    public function testCookieNameRejectsInvalidValues(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SessionCookieName($name);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCookieNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['session name'];
        yield 'separator' => ['session;name'];
        yield 'control' => ["session\nname"];
        yield 'long' => [str_repeat(string: 'a', times: 129)];
    }

    public function testInvalidSessionExceptionHasOneStableSafeMessage(): void
    {
        self::assertSame('Session is invalid.', new InvalidSessionException()->getMessage());
    }
}
