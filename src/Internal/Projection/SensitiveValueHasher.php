<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection;

use InvalidArgumentException;
use LogicException;

final readonly class SensitiveValueHasher
{
    public function __construct(
        private ?string $hmacKey = null,
    ) {
        if ($hmacKey === '') {
            throw new InvalidArgumentException('Sensitive projection HMAC key must not be empty.');
        }
    }

    public function hash(mixed $value): string
    {
        if ($this->hmacKey === null) {
            throw new LogicException('Sensitive projection HMAC key is required for hash mode.');
        }

        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Sensitive projection hash value must be scalar or null.');
        }

        return 'hmac-sha256:' . hash_hmac('sha256', (string) $value, $this->hmacKey);
    }
}
