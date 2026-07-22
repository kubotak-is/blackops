<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Domain\Identity\EnabledRegistrationPolicy;
use App\Domain\Identity\Exception\DuplicateEmail;
use App\Domain\Identity\Exception\InvalidCredentials;
use App\Domain\Identity\IdentityClock;
use App\Domain\Identity\IdentityIdentifier;
use App\Domain\Identity\IdentityService;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IdentityServiceTest extends TestCase
{
    public function testRegistrationNormalizesIdentityAndKeepsDomainVendorNeutral(): void
    {
        [$service, $repository] = $this->service();

        $user = $service->register('  Person@Example.COM  ', ' Person ', 'a sufficiently long password');

        self::assertSame('019b1111-1111-7111-8111-111111111111', $user->id);
        self::assertSame('Person@Example.COM', $user->email);
        self::assertSame('person@example.com', $user->canonicalEmail);
        self::assertSame('Person', $user->displayName);
        self::assertTrue(password_verify('a sufficiently long password', $user->passwordHash));
        self::assertSame($user, $repository->findByEmail('person@example.com'));
        self::assertSame('2026-07-20T00:00:00+00:00', $user->createdAt->format(DATE_ATOM));
    }

    public function testDuplicateAndInvalidCredentialsUseDomainFailures(): void
    {
        [$service] = $this->service();
        $service->register('person@example.com', 'Person', 'a sufficiently long password');

        try {
            $service->register('PERSON@example.com', 'Someone else', 'another long password value');
            self::fail('Duplicate canonical email must be rejected.');
        } catch (DuplicateEmail) {
        }

        self::assertSame(
            '019b1111-1111-7111-8111-111111111111',
            $service->authenticate('PERSON@example.com', 'a sufficiently long password')->id,
        );

        foreach ([
            ['person@example.com',  'wrong password value'],
            ['missing@example.com', 'wrong password value'],
        ] as [$email, $password]) {
            try {
                $service->authenticate($email, $password);
                self::fail('Invalid credentials must be rejected.');
            } catch (InvalidCredentials) {
            }
        }
    }

    /** @return array{IdentityService, MemoryUserRepository} */
    private function service(): array
    {
        $repository = new MemoryUserRepository();

        return [
            new IdentityService(
                $repository,
                new PasswordHasher(),
                new EnabledRegistrationPolicy(),
                new FixedIdentityIdentifier('019b1111-1111-7111-8111-111111111111'),
                new FixedIdentityClock(new DateTimeImmutable('2026-07-20T00:00:00+00:00')),
            ),
            $repository,
        ];
    }
}

final class MemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function findByEmail(string $canonicalEmail): ?User
    {
        foreach ($this->users as $user) {
            if ($user->canonicalEmail === $canonicalEmail) {
                return $user;
            }
        }

        return null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function save(User $user): void
    {
        if ($this->findByEmail($user->canonicalEmail) !== null) {
            throw new DuplicateEmail();
        }

        $this->users[$user->id] = $user;
    }

    public function updatePasswordHash(string $id, string $passwordHash): void
    {
        $user = $this->users[$id];
        $this->users[$id] = new User(
            $user->id,
            $user->email,
            $user->canonicalEmail,
            $user->displayName,
            $passwordHash,
            $user->createdAt,
            $user->updatedAt,
        );
    }
}

final readonly class FixedIdentityIdentifier implements IdentityIdentifier
{
    public function __construct(
        private string $id,
    ) {}

    public function generate(): string
    {
        return $this->id;
    }
}

final readonly class FixedIdentityClock implements IdentityClock
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
