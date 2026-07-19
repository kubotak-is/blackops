<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\EmailUnavailable;
use App\Identity\IdentityRepository;
use App\Identity\StoredUser;
use App\Identity\User;
use Closure;
use DateTimeImmutable;

final class InMemoryIdentityRepository implements IdentityRepository
{
    /** @var array<string, array{canonical: string, stored: StoredUser}> */
    public array $users = [];

    /** @var array<string, array{id: string, userId: string, issuedAt: DateTimeImmutable, expiresAt: DateTimeImmutable, revokedAt: ?DateTimeImmutable}> */
    public array $sessions = [];

    public function transactional(Closure $operation): mixed
    {
        return $operation();
    }

    public function findByCanonicalEmail(string $emailCanonical): ?StoredUser
    {
        foreach ($this->users as $entry) {
            if ($entry['canonical'] === $emailCanonical) {
                return $entry['stored'];
            }
        }

        return null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id]['stored']->user() ?? null;
    }

    public function findByActiveTokenHash(string $tokenHash, DateTimeImmutable $now): ?User
    {
        $session = $this->sessions[$tokenHash] ?? null;
        if ($session === null || $session['revokedAt'] !== null || $session['expiresAt'] <= $now) {
            return null;
        }

        return $this->findById($session['userId']);
    }

    public function createUser(
        string $id,
        string $emailCanonical,
        string $emailDisplay,
        string $displayName,
        string $passwordHash,
        DateTimeImmutable $now,
    ): void {
        if ($this->findByCanonicalEmail($emailCanonical) !== null) {
            throw new EmailUnavailable();
        }

        $this->users[$id] = [
            'canonical' => $emailCanonical,
            'stored' => new StoredUser($id, $emailDisplay, $displayName, $passwordHash),
        ];
    }

    public function updatePasswordHash(string $userId, string $passwordHash, DateTimeImmutable $now): void
    {
        $entry = $this->users[$userId];
        $stored = $entry['stored'];
        $this->users[$userId] = [
            'canonical' => $entry['canonical'],
            'stored' => new StoredUser($stored->id, $stored->email, $stored->displayName, $passwordHash),
        ];
    }

    public function createSession(
        string $id,
        string $userId,
        string $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): void {
        $this->sessions[$tokenHash] = [
            'id' => $id,
            'userId' => $userId,
            'issuedAt' => $issuedAt,
            'expiresAt' => $expiresAt,
            'revokedAt' => null,
        ];
    }

    public function revokeSession(string $tokenHash, DateTimeImmutable $revokedAt): void
    {
        if (!isset($this->sessions[$tokenHash])) {
            return;
        }

        $this->sessions[$tokenHash]['revokedAt'] = $revokedAt;
    }
}
