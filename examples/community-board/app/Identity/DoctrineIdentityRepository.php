<?php

declare(strict_types=1);

namespace App\Identity;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineIdentityRepository implements IdentityRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function transactional(Closure $operation): mixed
    {
        return $this->connection->transactional(static fn(Connection $_connection): mixed => $operation());
    }

    public function findByCanonicalEmail(string $emailCanonical): ?StoredUser
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT id, email_display, display_name, password_hash
                FROM public.board_users
                WHERE email_canonical = :email_canonical
                SQL,
            ['email_canonical' => $emailCanonical],
        );

        if ($row === false) {
            return null;
        }

        return new StoredUser(
            id: (string) $row['id'],
            email: (string) $row['email_display'],
            displayName: (string) $row['display_name'],
            passwordHash: (string) $row['password_hash'],
        );
    }

    public function findById(string $id): ?User
    {
        $row = $this->connection->fetchAssociative('SELECT id, email_display, display_name FROM public.board_users WHERE id = :id', [
            'id' => $id,
        ]);

        return $row === false ? null : $this->user($row);
    }

    public function findByActiveTokenHash(string $tokenHash, DateTimeImmutable $now): ?User
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT users.id, users.email_display, users.display_name
                FROM public.board_sessions sessions
                INNER JOIN public.board_users users ON users.id = sessions.user_id
                WHERE sessions.token_hash = :token_hash
                  AND sessions.revoked_at IS NULL
                  AND sessions.expires_at > :now
                SQL,
            [
                'token_hash' => $tokenHash,
                'now' => $this->timestamp($now),
            ],
        );

        return $row === false ? null : $this->user($row);
    }

    public function createUser(
        string $id,
        string $emailCanonical,
        string $emailDisplay,
        string $displayName,
        string $passwordHash,
        DateTimeImmutable $now,
    ): void {
        try {
            $this->connection->insert('public.board_users', [
                'id' => $id,
                'email_canonical' => $emailCanonical,
                'email_display' => $emailDisplay,
                'display_name' => $displayName,
                'password_hash' => $passwordHash,
                'created_at' => $this->timestamp($now),
                'updated_at' => $this->timestamp($now),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new EmailUnavailable('Email address is unavailable.', previous: $exception);
        }
    }

    public function updatePasswordHash(string $userId, string $passwordHash, DateTimeImmutable $now): void
    {
        $this->connection->update(
            'public.board_users',
            [
                'password_hash' => $passwordHash,
                'updated_at' => $this->timestamp($now),
            ],
            ['id' => $userId],
        );
    }

    public function createSession(
        string $id,
        string $userId,
        string $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): void {
        $this->connection->insert('public.board_sessions', [
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'issued_at' => $this->timestamp($issuedAt),
            'expires_at' => $this->timestamp($expiresAt),
            'revoked_at' => null,
        ]);
    }

    public function revokeSession(string $tokenHash, DateTimeImmutable $revokedAt): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE public.board_sessions
                SET revoked_at = :revoked_at
                WHERE token_hash = :token_hash
                  AND revoked_at IS NULL
                SQL,
            [
                'token_hash' => $tokenHash,
                'revoked_at' => $this->timestamp($revokedAt),
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private function user(array $row): User
    {
        return new User(
            id: (string) $row['id'],
            email: (string) $row['email_display'],
            displayName: (string) $row['display_name'],
        );
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}
