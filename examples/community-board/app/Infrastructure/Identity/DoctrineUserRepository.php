<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\Exception\DuplicateEmail;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use BlackOps\Database\DatabaseManager;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private DatabaseManager $databases,
    ) {}

    public function findByEmail(string $canonicalEmail): ?User
    {
        $row = $this->databases->connection()->fetchAssociative(
            'SELECT id, email_canonical, email_display, display_name, password_hash, created_at, updated_at FROM public.board_users WHERE email_canonical = :email',
            ['email' => $canonicalEmail],
        );

        return $row === false ? null : $this->user($row);
    }

    public function findById(string $id): ?User
    {
        $row = $this->databases->connection()->fetchAssociative(
            'SELECT id, email_canonical, email_display, display_name, password_hash, created_at, updated_at FROM public.board_users WHERE id = :id',
            ['id' => $id],
        );

        return $row === false ? null : $this->user($row);
    }

    public function save(User $user): void
    {
        try {
            $this->databases->connection()->insert('public.board_users', [
                'id' => $user->id,
                'email_canonical' => $user->canonicalEmail,
                'email_display' => $user->email,
                'display_name' => $user->displayName,
                'password_hash' => $user->passwordHash,
                'created_at' => $user->createdAt->format('Y-m-d H:i:s.uP'),
                'updated_at' => $user->updatedAt->format('Y-m-d H:i:s.uP'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateEmail();
        }
    }

    public function updatePasswordHash(string $id, string $passwordHash): void
    {
        $this->databases->connection()->update(
            'public.board_users',
            ['password_hash' => $passwordHash, 'updated_at' => new DateTimeImmutable()->format('Y-m-d H:i:s.uP')],
            ['id' => $id],
        );
    }

    /** @param array<string, mixed> $row */
    private function user(array $row): User
    {
        return new User(
            (string) $row['id'],
            (string) $row['email_display'],
            (string) $row['email_canonical'],
            (string) $row['display_name'],
            (string) $row['password_hash'],
            new DateTimeImmutable((string) ($row['created_at'] ?? 'now')),
            new DateTimeImmutable((string) ($row['updated_at'] ?? 'now')),
        );
    }
}
