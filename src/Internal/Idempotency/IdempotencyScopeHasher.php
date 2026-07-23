<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\ActorRef;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Idempotency\IdempotencyKeyHash;

final readonly class IdempotencyScopeHasher
{
    private const string DOMAIN = 'blackops/idempotency-scope/v1';

    public function hash(
        string $operationTypeId,
        ActorRef $authorization,
        IdempotencyKey|IdempotencyKeyHash $key,
    ): IdempotencyScopeHash {
        $keyHash = $key instanceof IdempotencyKey ? $key->hash() : $key;
        $stream = hash_init('sha256');
        hash_update($stream, self::DOMAIN);
        $this->field($stream, $operationTypeId);
        $this->field($stream, $authorization->type());
        $this->field($stream, $authorization->id());
        $this->field($stream, $keyHash->digest());

        return new IdempotencyScopeHash(IdempotencyScopeHash::VERSION, hash_final($stream));
    }

    /** @param resource $stream */
    private function field($stream, string $value): void
    {
        hash_update($stream, pack('N', strlen($value)));
        hash_update($stream, $value);
    }
}
