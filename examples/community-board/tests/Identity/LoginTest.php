<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Domain\Identity\EnabledRegistrationPolicy;
use App\Domain\Identity\Exception\DuplicateEmail;
use App\Domain\Identity\IdentityClock;
use App\Domain\Identity\IdentityIdentifier;
use App\Domain\Identity\IdentityService;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use App\Feature\Identity\Login\Login;
use App\Feature\Identity\Login\LoginValue;
use BlackOps\Auth\Session\IssuedSession;
use BlackOps\Auth\Session\RawSessionToken;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\ActorRef;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

final class LoginTest extends TestCase
{
    /** @return iterable<string, array{?ActorRef, list<string>, list<string>, list<string>}> */
    public static function currentSessionCases(): iterable
    {
        yield 'same user rotates the current session' => [
            new ActorRef('user-2', 'user'),
            [],
            ['current-session-token'],
            [],
        ];
        yield 'different user revokes before issuing the new session' => [
            new ActorRef('user-1', 'user'),
            ['user-2'],
            [],
            ['current-session-token'],
        ];
        yield 'invalid or expired session only issues the new session' => [
            null,
            ['user-2'],
            [],
            [],
        ];
    }

    /**
     * @param list<string> $issued
     * @param list<string> $rotated
     * @param list<string> $revoked
     */
    #[DataProvider('currentSessionCases')]
    public function testLoginPreservesSessionRotationAndAccountSwitchRevocation(
        ?ActorRef $currentActor,
        array $issued,
        array $rotated,
        array $revoked,
    ): void {
        $repository = new LoginUserRepository();
        $passwords = new PasswordHasher();
        $now = new DateTimeImmutable('2026-07-22T00:00:00+00:00');
        $repository->save(
            new User(
                'user-2',
                'second@example.com',
                'second@example.com',
                'Second User',
                $passwords->hash('a sufficiently long password'),
                $now,
                $now,
            ),
        );
        $sessions = new LoginSessionManager($currentActor, $now);
        $login = new Login(
            new IdentityService(
                $repository,
                $passwords,
                new EnabledRegistrationPolicy(),
                new LoginIdentityIdentifier(),
                new LoginIdentityClock($now),
            ),
            $sessions,
        );

        $outcome = $login->handle(new LoginValue(
            'second@example.com',
            'a sufficiently long password',
            'current-session-token',
        ));

        self::assertSame(43, strlen($outcome->token));
        self::assertSame($issued, $sessions->issued);
        self::assertSame($rotated, $sessions->rotated);
        self::assertSame($revoked, $sessions->revoked);
    }
}

final class LoginSessionManager implements SessionManager
{
    /** @var list<string> */
    public array $issued = [];

    /** @var list<string> */
    public array $rotated = [];

    /** @var list<string> */
    public array $revoked = [];

    public function __construct(
        private readonly ?ActorRef $currentActor,
        private readonly DateTimeImmutable $now,
    ) {}

    public function issue(string $identityId): IssuedSession
    {
        $this->issued[] = $identityId;

        return $this->session('issued-session-token');
    }

    public function authenticate(#[SensitiveParameter] string $rawToken): ?ActorRef
    {
        return $this->currentActor;
    }

    public function rotate(#[SensitiveParameter] string $rawToken): IssuedSession
    {
        $this->rotated[] = $rawToken;

        return $this->session('rotated-session-token');
    }

    public function revoke(#[SensitiveParameter] ?string $rawToken): void
    {
        if ($rawToken !== null) {
            $this->revoked[] = $rawToken;
        }
    }

    public function cleanup(DateTimeImmutable $retentionCutoff): int
    {
        return 0;
    }

    private function session(string $seed): IssuedSession
    {
        return new IssuedSession(
            RawSessionToken::fromRandomBytes(hash('sha256', $seed, true)),
            $this->now,
            $this->now->modify('+8 hours'),
        );
    }
}

final class LoginUserRepository implements UserRepository
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

final readonly class LoginIdentityIdentifier implements IdentityIdentifier
{
    public function generate(): string
    {
        return 'unused-login-identifier';
    }
}

final readonly class LoginIdentityClock implements IdentityClock
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
