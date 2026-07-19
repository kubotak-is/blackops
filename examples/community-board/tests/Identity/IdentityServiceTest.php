<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\IdentityService;
use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use App\Identity\SessionToken;
use App\Tests\Support\FrozenIdentityClock;
use App\Tests\Support\InMemoryIdentityRepository;
use App\Tests\Support\SequenceUuidGenerator;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class IdentityServiceTest extends TestCase
{
    public function testRegistrationNormalizesEmailAndStoresOnlyArgonAndTokenHashes(): void
    {
        [$service, $repository] = $this->service();

        $session = $service->register('  Person@Example.COM  ', ' Person ', 'a sufficiently long password');
        $stored = $repository->users[$session->user->id];
        $tokenHash = hash('sha256', $session->rawToken);

        self::assertSame('person@example.com', $stored['canonical']);
        self::assertSame('Person@Example.COM', $stored['stored']->email);
        self::assertSame('Person', $stored['stored']->displayName);
        self::assertTrue(password_verify('a sufficiently long password', $stored['stored']->passwordHash));
        self::assertSame('argon2id', password_get_info($stored['stored']->passwordHash)['algoName']);
        self::assertMatchesRegularExpression('/\\A[A-Za-z0-9_-]{43}\\z/D', $session->rawToken);
        self::assertArrayHasKey($tokenHash, $repository->sessions);
        self::assertArrayNotHasKey($session->rawToken, $repository->sessions);
    }

    public function testExpiryRevocationAndLoginRotationInvalidateOldSessions(): void
    {
        [$service, $repository, $clock] = $this->service(ttl: 60);
        $registered = $service->register('person@example.com', 'Person', 'a sufficiently long password');

        self::assertNotNull($service->authenticate($registered->rawToken));

        $rotated = $service->login('PERSON@example.com', 'a sufficiently long password', $registered->rawToken);
        self::assertNull($service->authenticate($registered->rawToken));
        self::assertNotNull($service->authenticate($rotated->rawToken));

        $service->logout($rotated->rawToken);
        self::assertNull($service->authenticate($rotated->rawToken));
        $service->logout($rotated->rawToken);

        $fresh = $service->login('person@example.com', 'a sufficiently long password', null);
        $clock->current = $clock->current->add(new DateInterval('PT61S'));
        self::assertNull($service->authenticate($fresh->rawToken));
        self::assertCount(3, $repository->sessions);
    }

    public function testSuccessfulLoginRehashesAnOutdatedArgon2idHash(): void
    {
        [$service, $repository, $clock] = $this->service();
        $password = 'a sufficiently long password';
        $outdatedHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 8,
            'time_cost' => 1,
            'threads' => 1,
        ]);
        self::assertIsString($outdatedHash);

        $repository->createUser(
            '019b1111-1111-7111-8111-111111111111',
            'person@example.com',
            'person@example.com',
            'Person',
            $outdatedHash,
            $clock->now(),
        );
        $service->login('person@example.com', $password, null);

        $updated = $repository->findByCanonicalEmail('person@example.com');
        self::assertNotNull($updated);
        self::assertNotSame($outdatedHash, $updated->passwordHash);
        self::assertFalse(password_needs_rehash($updated->passwordHash, PASSWORD_ARGON2ID));
    }

    /** @return array{IdentityService, InMemoryIdentityRepository, FrozenIdentityClock} */
    private function service(int $ttl = 28_800): array
    {
        $repository = new InMemoryIdentityRepository();
        $clock = new FrozenIdentityClock(new DateTimeImmutable('2026-07-20T00:00:00+00:00', new DateTimeZone('UTC')));
        $identifiers = new SequenceUuidGenerator([
            '019b1111-1111-7111-8111-111111111111',
            '019b1111-1111-7111-8111-111111111112',
            '019b1111-1111-7111-8111-111111111113',
            '019b1111-1111-7111-8111-111111111114',
            '019b1111-1111-7111-8111-111111111115',
            '019b1111-1111-7111-8111-111111111116',
        ]);

        return [
            new IdentityService(
                $repository,
                new PasswordHasher(),
                new SessionToken(),
                $clock,
                $identifiers,
                new SessionSettings($ttl),
            ),
            $repository,
            $clock,
        ];
    }
}
