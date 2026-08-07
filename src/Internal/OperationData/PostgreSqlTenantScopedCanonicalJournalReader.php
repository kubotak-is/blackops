<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Journal\JournalRecord;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlTenantScopedCanonicalJournalReader implements TenantScopedCanonicalJournalReader
{
    public function __construct(
        private Connection $connection,
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
                "SELECT encoded_record
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
                    yield $this->codec->decode(PostgreSqlBytea::string($row['encoded_record'] ?? null));
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
}
