<?php

declare(strict_types=1);

namespace App\Identity;

use RuntimeException;

final readonly class PasswordHasher
{
    private string $dummyHash;

    public function __construct()
    {
        if (!defined('PASSWORD_ARGON2ID') || !in_array(PASSWORD_ARGON2ID, password_algos(), strict: true)) {
            throw new RuntimeException('Argon2id password hashing is required.');
        }

        $this->dummyHash = $this->hash(base64_encode(random_bytes(32)));
    }

    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function verifyCredential(string $password, ?string $knownHash): bool
    {
        $verified = password_verify($password, $knownHash ?? $this->dummyHash);

        return $knownHash !== null && $verified;
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
