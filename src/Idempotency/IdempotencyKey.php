<?php

declare(strict_types=1);

namespace BlackOps\Idempotency;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * A caller supplied idempotency key. The value is intentionally write-only;
 * callers can use it to derive an opaque hash but cannot read it back.
 */
#[PublicApi]
final readonly class IdempotencyKey
{
    private string $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        $length = strlen($value);

        if ($length < 1 || $length > 255 || preg_match('/[^\x21-\x7e]/', $value) === 1) {
            throw new InvalidArgumentException('Idempotency key has an invalid shape.');
        }

        $this->value = $value;
    }

    public function hash(): IdempotencyKeyHash
    {
        $digest = hash_init('sha256');
        hash_update($digest, IdempotencyKeyHash::DOMAIN_SEPARATOR);
        hash_update($digest, pack('N', strlen($this->value)));
        hash_update($digest, $this->value);

        return new IdempotencyKeyHash(IdempotencyKeyHash::ALGORITHM_VERSION, hash_final($digest));
    }

    /** @return array<string, never> */
    public function __debugInfo(): array
    {
        return [];
    }
}
