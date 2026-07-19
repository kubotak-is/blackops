<?php

declare(strict_types=1);

namespace App\Identity;

final readonly class SessionToken
{
    private const string FORMAT = '/\\A[A-Za-z0-9_-]{43}\\z/D';

    public function issue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function isValid(string $rawToken): bool
    {
        return preg_match(self::FORMAT, $rawToken) === 1;
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
