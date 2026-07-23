<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use InvalidArgumentException;

final readonly class OperationFingerprint
{
    public function __construct(
        private int $version,
        private string $digest,
    ) {
        if ($version !== 1 || !preg_match('/\A[0-9a-f]{64}\z/', $digest)) {
            throw new InvalidArgumentException('Operation fingerprint is invalid.');
        }
    }

    public function version(): int
    {
        return $this->version;
    }

    public function digest(): string
    {
        return $this->digest;
    }

    public function equals(self $other): bool
    {
        return $this->version === $other->version && hash_equals($this->digest, $other->digest);
    }

    public function __toString(): string
    {
        return $this->digest;
    }
}
