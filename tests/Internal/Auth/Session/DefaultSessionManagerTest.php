<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Auth\Session;

use BlackOps\Auth\Session\InvalidSessionException;
use BlackOps\Auth\Session\RawSessionToken;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Core\ActorRef;
use BlackOps\Internal\Auth\Session\DefaultSessionManager;
use BlackOps\Internal\Auth\Session\NewSessionRecord;
use BlackOps\Internal\Auth\Session\SessionClock;
use BlackOps\Internal\Auth\Session\SessionIdentifierGenerator;
use BlackOps\Internal\Auth\Session\SessionStore;
use BlackOps\Internal\Auth\Session\SessionTokenGenerator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DefaultSessionManagerTest extends TestCase
{
    #[DataProvider('invalidIdentityIds')]
    public function testIssueRejectsInvalidIdentityBeforeWriting(string $identityId): void
    {
        $store = new RecordingSessionStore();

        try {
            $this->manager($store)->issue($identityId);
            self::fail('Expected invalid identity.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Session identity is invalid.', $exception->getMessage());
        }

        self::assertSame([], $store->inserted);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIdentityIds(): iterable
    {
        yield 'empty' => [''];
        yield 'control' => ["identity\nvalue"];
        yield 'too long' => [str_repeat(string: 'a', times: 256)];
    }

    public function testIssueStoresOnlyHashAndReturnsOneRawTokenWithAbsoluteExpiry(): void
    {
        $store = new RecordingSessionStore();
        $manager = $this->manager($store);

        $issued = $manager->issue('identity-1');

        self::assertSame('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', $issued->token()->reveal());
        self::assertSame('2026-07-22T00:00:00+00:00', $issued->issuedAt()->format(DATE_ATOM));
        self::assertSame('2026-07-22T08:00:00+00:00', $issued->expiresAt()->format(DATE_ATOM));
        self::assertCount(1, $store->inserted);
        self::assertSame('identity-1', $store->inserted[0]->identityId);
        self::assertSame(hash('sha256', $issued->token()->reveal()), $store->inserted[0]->tokenHash);
        self::assertNotSame($issued->token()->reveal(), $store->inserted[0]->tokenHash);
    }

    public function testAuthenticationUsesHashAndConditionalTouchThreshold(): void
    {
        $store = new RecordingSessionStore();
        $store->authenticatedIdentity = 'identity-1';
        $manager = $this->manager($store);

        self::assertSame('identity-1', $manager->authenticate(str_repeat(string: 'A', times: 43))?->id());
        self::assertSame(hash('sha256', str_repeat(string: 'A', times: 43)), $store->authenticatedHash);
        self::assertSame('2026-07-21T23:55:00+00:00', $store->touchThreshold?->format(DATE_ATOM));
        self::assertNull($manager->authenticate('malformed'));
        self::assertSame(1, $store->authenticationCalls);
    }

    public function testMissingIdentityIsInvalidAndProviderFailurePropagates(): void
    {
        $store = new RecordingSessionStore();
        $store->authenticatedIdentity = 'missing';

        self::assertNull($this->manager($store, new MissingIdentityProvider())->authenticate(str_repeat(
            string: 'A',
            times: 43,
        )));

        $this->expectExceptionMessage('identity provider unavailable');
        $this->manager($store, new FailingIdentityProvider())->authenticate(str_repeat(string: 'A', times: 43));
    }

    public function testRotateUsesOneSafeExceptionForEveryInvalidState(): void
    {
        $store = new RecordingSessionStore();
        $manager = $this->manager($store);

        foreach (['malformed', str_repeat(string: 'A', times: 43)] as $token) {
            try {
                $manager->rotate($token);
                self::fail('Expected invalid session.');
            } catch (InvalidSessionException $exception) {
                self::assertSame('Session is invalid.', $exception->getMessage());
                self::assertStringNotContainsString($token, $exception->getMessage());
            }
        }
    }

    public function testRotateReturnsSuccessorAndNeverExposesIdentity(): void
    {
        $store = new RecordingSessionStore();
        $store->rotatedIdentity = 'identity-1';
        $manager = $this->manager($store);

        $successor = $manager->rotate(str_repeat(string: 'A', times: 43));

        self::assertSame('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', $successor->token()->reveal());
        self::assertFalse(property_exists($successor, 'identityId'));
        self::assertSame(hash('sha256', str_repeat(string: 'A', times: 43)), $store->rotatedHash);
    }

    public function testRevokeIsIdempotentForNullMalformedAndUnknownTokens(): void
    {
        $store = new RecordingSessionStore();
        $manager = $this->manager($store);

        $manager->revoke(null);
        $manager->revoke('malformed');
        $manager->revoke(str_repeat(string: 'A', times: 43));
        $manager->revoke(str_repeat(string: 'A', times: 43));

        self::assertSame(2, $store->revocationCalls);
        self::assertSame(hash('sha256', str_repeat(string: 'A', times: 43)), $store->revokedHash);
    }

    public function testCleanupDelegatesCutoffAndReturnsDeletedCount(): void
    {
        $store = new RecordingSessionStore();
        $store->cleanupCount = 3;
        $manager = $this->manager($store);
        $cutoff = new DateTimeImmutable('2026-07-20T00:00:00Z');

        self::assertSame(3, $manager->cleanup($cutoff));
        self::assertSame($cutoff, $store->cleanupCutoff);
    }

    private function manager(
        RecordingSessionStore $store,
        SessionIdentityProvider $identities = new FixedIdentityProvider(),
    ): DefaultSessionManager {
        return new DefaultSessionManager(
            new SessionConfiguration(),
            $identities,
            $store,
            new FixedTokenGenerator(),
            new FixedIdentifierGenerator(),
            new FixedSessionClock(),
        );
    }
}

/**
 * @mago-expect lint:single-class-per-file
 * @mago-expect lint:too-many-properties
 */
final class RecordingSessionStore implements SessionStore
{
    /** @var list<NewSessionRecord> */
    public array $inserted = [];
    public ?string $authenticatedIdentity = null;
    public ?string $authenticatedHash = null;
    public ?DateTimeImmutable $touchThreshold = null;
    public int $authenticationCalls = 0;
    public ?string $rotatedIdentity = null;
    public ?string $rotatedHash = null;
    public int $revocationCalls = 0;
    public ?string $revokedHash = null;
    public int $cleanupCount = 0;
    public ?DateTimeImmutable $cleanupCutoff = null;

    public function insert(NewSessionRecord $session): void
    {
        $this->inserted[] = $session;
    }

    public function authenticate(string $tokenHash, DateTimeImmutable $now, DateTimeImmutable $touchThreshold): ?string
    {
        $this->authenticationCalls++;
        $this->authenticatedHash = $tokenHash;
        $this->touchThreshold = $touchThreshold;

        return $this->authenticatedIdentity;
    }

    public function rotate(
        string $tokenHash,
        string $successorId,
        string $successorTokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): ?string {
        $this->rotatedHash = $tokenHash;

        return $this->rotatedIdentity;
    }

    public function revoke(string $tokenHash, DateTimeImmutable $revokedAt): void
    {
        $this->revocationCalls++;
        $this->revokedHash = $tokenHash;
    }

    public function cleanup(DateTimeImmutable $retentionCutoff, DateTimeImmutable $now): int
    {
        $this->cleanupCutoff = $retentionCutoff;

        return $this->cleanupCount;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FixedTokenGenerator implements SessionTokenGenerator
{
    public function generate(): RawSessionToken
    {
        return RawSessionToken::fromRandomBytes(str_repeat(string: "\0", times: 32));
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FixedIdentifierGenerator implements SessionIdentifierGenerator
{
    public function generate(DateTimeImmutable $time): string
    {
        return '019f3bde-4c00-7000-8000-000000000001';
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FixedSessionClock implements SessionClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-22T00:00:00Z');
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FixedIdentityProvider implements SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef
    {
        return new ActorRef($identityId, 'user');
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class MissingIdentityProvider implements SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef
    {
        return null;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FailingIdentityProvider implements SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef
    {
        throw new RuntimeException('identity provider unavailable');
    }
}
