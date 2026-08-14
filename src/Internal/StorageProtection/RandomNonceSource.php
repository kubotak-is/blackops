<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use SodiumException;

final readonly class RandomNonceSource implements NonceSource
{
    private const int NONCE_LENGTH = 24;

    public function generate(): string
    {
        try {
            return random_bytes(self::NONCE_LENGTH);
        } catch (SodiumException|\Exception $exception) {
            throw new \RuntimeException('Storage protection nonce generation failed.', previous: $exception);
        }
    }
}
