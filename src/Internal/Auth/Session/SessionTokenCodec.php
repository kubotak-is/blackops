<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use SensitiveParameter;

final readonly class SessionTokenCodec
{
    public function hash(#[SensitiveParameter] string $rawToken): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/D', $rawToken) !== 1) {
            return null;
        }

        $padding = str_repeat('=', (4 - (strlen($rawToken) % 4)) % 4);
        $bytes = base64_decode(string: strtr(string: $rawToken, from: '-_', to: '+/') . $padding, strict: true);

        if (!is_string($bytes) || strlen($bytes) !== 32) {
            return null;
        }

        $canonical = rtrim(string: strtr(string: base64_encode($bytes), from: '+/', to: '-_'), characters: '=');

        if (!hash_equals($canonical, $rawToken)) {
            return null;
        }

        return hash('sha256', $rawToken);
    }
}
