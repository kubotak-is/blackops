<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Journal\JournalRecord;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;

final class PostgreSqlTestStorageProtection
{
    public static function codec(): BopdEnvelopeCodec
    {
        return new BopdEnvelopeCodec(new PostgreSqlTestStorageKeyProvider());
    }

    public static function journalEnvelope(
        string $plaintext,
        string $recordId,
        string $operationId,
        string $operationType = 'operation.replay',
        int $schemaVersion = 1,
        ?TenantRef $tenant = null,
    ): string {
        return self::codec()
            ->encrypt(
                $plaintext,
                new StorageProtectionContext(
                    StoragePurpose::JournalRecord,
                    $recordId,
                    $operationId,
                    $operationType,
                    $schemaVersion,
                    $tenant,
                ),
            );
    }

    public static function journalRecordEnvelope(JournalRecord $record): string
    {
        return self::journalEnvelope(
            new \BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec()->encode($record),
            $record->recordId->toString(),
            $record->operation->id->toString(),
            $record->operation->type,
            $record->operation->schemaVersion,
            $record->operation->tenant,
        );
    }

    public static function outcomeEnvelope(
        string $plaintext,
        string $operationId,
        string $operationType,
        int $schemaVersion = 1,
        ?TenantRef $tenant = null,
    ): string {
        return self::codec()
            ->encrypt(
                $plaintext,
                new StorageProtectionContext(
                    StoragePurpose::OutcomePayload,
                    $operationId,
                    $operationId,
                    $operationType,
                    $schemaVersion,
                    $tenant,
                ),
            );
    }

    public static function deferredPayloadEnvelope(
        string $plaintext,
        string $operationId,
        string $operationType,
        int $schemaVersion = 1,
        ?TenantRef $tenant = null,
    ): string {
        return self::codec()
            ->encrypt(
                $plaintext,
                new StorageProtectionContext(
                    StoragePurpose::DeferredPayload,
                    $operationId . ':payload',
                    $operationId,
                    $operationType,
                    $schemaVersion,
                    $tenant,
                ),
            );
    }

    public static function deferredContextEnvelope(
        string $plaintext,
        string $operationId,
        string $operationType,
        int $schemaVersion = 1,
        ?TenantRef $tenant = null,
    ): string {
        return self::codec()
            ->encrypt(
                $plaintext,
                new StorageProtectionContext(
                    StoragePurpose::DeferredContext,
                    $operationId . ':context',
                    $operationId,
                    $operationType,
                    $schemaVersion,
                    $tenant,
                ),
            );
    }
}

final readonly class PostgreSqlTestStorageKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return $this->key('test-key', $tenant, $purpose);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        if ($keyId !== 'test-key') {
            throw new \InvalidArgumentException('Unknown storage key identifier.');
        }

        return new StorageKey($keyId, str_repeat('k', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }
}
