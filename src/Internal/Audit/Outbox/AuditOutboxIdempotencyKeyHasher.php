<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

use BlackOps\Core\Audit\AuditValidationException;

final readonly class AuditOutboxIdempotencyKeyHasher
{
    public function hash(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $idempotencyKey,
    ): string {
        try {
            if (preg_match('/\A(?:system:global|tenant:[a-f0-9]{64})\z/D', $partitionId) !== 1) {
                throw new \RuntimeException();
            }
            AuditValidationException::assertSafeString($idempotencyKey, 1, 256);
            $preimage =
                $this->encodeString('blackops.audit.outbox.idempotency.v1')
                . $this->encodeString($partitionId)
                . $this->encodeString($idempotencyKey);

            return hash('sha256', $preimage);
        } catch (\Throwable) {
            throw new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
        }
    }

    private function encodeString(#[\SensitiveParameter] string $value): string
    {
        AuditValidationException::assertUtf8($value);
        return 's:' . strlen($value) . ':' . $value;
    }
}
