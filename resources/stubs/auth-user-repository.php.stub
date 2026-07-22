<?php

declare(strict_types=1);

namespace App\Domain\Identity;

interface UserRepository
{
    public function findByEmail(string $canonicalEmail): ?User;

    public function findById(string $id): ?User;

    public function save(User $user): void;

    public function updatePasswordHash(string $id, string $passwordHash): void;
}
