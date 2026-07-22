<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use RuntimeException;
use SensitiveParameter;

final readonly class PasswordHasher
{
    private string $dummyHash;

    public function __construct()
    {
        $this->dummyHash = $this->hash(base64_encode(random_bytes(32)));
    }

    public function hash(#[SensitiveParameter] string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public function verifyCredential(#[SensitiveParameter] string $password, ?string $knownHash): bool
    {
        $verified = password_verify($password, $knownHash ?? $this->dummyHash);

        return $knownHash !== null && $verified;
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
