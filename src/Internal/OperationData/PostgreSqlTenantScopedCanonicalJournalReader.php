<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Journal\JournalRecord;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use Doctrine\DBAL\Connection;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlTenantScopedCanonicalJournalReader implements TenantScopedCanonicalJournalReader
{
    public function __construct(
        private Connection $connection,
        private BopdEnvelopeCodec $protection,
        string $schema = 'blackops',
        ?PostgreSqlJournalRecordCodec $codec = null,
    ) {
        $this->schema = new PostgreSqlJournalSchema($schema);
        $this->codec = $codec ?? new PostgreSqlJournalRecordCodec();
    }

    private PostgreSqlJournalSchema $schema;
    private PostgreSqlJournalRecordCodec $codec;

    /** @return iterable<JournalRecord> */
    public function recordsForTenant(OperationId $operationId, ?TenantRef $tenant): iterable
    {
        try {
            $rows = $this->connection->iterateAssociative(
                "SELECT record_id::text AS record_id, operation_id::text AS operation_id,
                    operation_type, schema_version, operation_schema_version,
                    tenant_type, tenant_id, origin_actor_type, origin_actor_id, encoded_record
                 FROM {$this->schema->journalTable()}
                 WHERE operation_id = :operation_id
                   AND tenant_type IS NOT DISTINCT FROM :tenant_type
                   AND tenant_id IS NOT DISTINCT FROM :tenant_id
                 ORDER BY sequence ASC",
                [
                    'operation_id' => $operationId->toString(),
                    'tenant_type' => $tenant?->type(),
                    'tenant_id' => $tenant?->id(),
                ],
            );
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }

        try {
            foreach ($rows as $row) {
                try {
                    $tenantRow = $this->tenant($row);
                    $payload = $this->protection->decrypt(
                        PostgreSqlBytea::string($row['encoded_record'] ?? null),
                        new StorageProtectionContext(
                            StoragePurpose::JournalRecord,
                            (string) $row['record_id'],
                            (string) $row['operation_id'],
                            (string) $row['operation_type'],
                            (int) $row['operation_schema_version'],
                            $tenantRow,
                        ),
                    );
                    $record = $this->codec->decode($payload);
                    if (
                        (string) $row['record_id'] !== $record->recordId->toString()
                        || (string) $row['operation_id'] !== $record->operation->id->toString()
                        || (string) $row['operation_type'] !== $record->operation->type
                        || (int) $row['schema_version'] !== $record->schemaVersion
                        || (int) $row['operation_schema_version'] !== $record->operation->schemaVersion
                        || ($row['tenant_type'] ?? null) !== $record->operation->tenant?->type()
                        || ($row['tenant_id'] ?? null) !== $record->operation->tenant?->id()
                        || ($row['origin_actor_type'] ?? null) !== $record->operation->actorContext?->origin()?->type()
                        || ($row['origin_actor_id'] ?? null) !== $record->operation->actorContext?->origin()?->id()
                    ) {
                        throw OperationJournalQueryException::integrityFailed();
                    }
                    yield $record;
                } catch (OperationJournalQueryException $exception) {
                    throw $exception;
                } catch (Throwable) {
                    throw OperationJournalQueryException::decodeFailed();
                }
            }
        } catch (OperationJournalQueryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw OperationJournalQueryException::storageFailed();
        }
    }

    /** @param array<string, mixed> $row */
    private function tenant(array $row): ?TenantRef
    {
        $type = $row['tenant_type'] ?? null;
        $id = $row['tenant_id'] ?? null;
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || $type === '' || !is_string($id) || $id === '') {
            throw OperationJournalQueryException::integrityFailed();
        }
        return new TenantRef($type, $id);
    }
}
