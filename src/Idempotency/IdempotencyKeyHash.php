<?php

declare(strict_types=1);

namespace BlackOps\Idempotency;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class IdempotencyKeyHash
{
    public const int ALGORITHM_VERSION = 1;
    public const string ALGORITHM = 'sha256';
    public const string DOMAIN_SEPARATOR = 'blackops/idempotency-key/v1';

    private int $version;
    private string $digest;

    public function __construct(int $version, string $digest)
    {
        if ($version !== self::ALGORITHM_VERSION || !preg_match('/\A[0-9a-f]{64}\z/', $digest)) {
            throw new InvalidArgumentException('Idempotency key hash is invalid.');
        }

        $this->version = $version;
        $this->digest = strtolower($digest);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function algorithm(): string
    {
        return self::ALGORITHM;
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
