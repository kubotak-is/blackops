<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\StorageProtection\StoragePurpose;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class PostgreSqlDeadLetterStore
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSchema $schema,
        private PostgreSqlDeferredOperationLifecycleSql $sql,
        private BopdEnvelopeCodec $protection,
    ) {}

    public function insert(OperationClaim $claim, OperationDeadLetteredData $data): void
    {
        $deadLetters = $this->schema->deadLettersTable();
        $this->connection->executeStatement(
            "INSERT INTO {$deadLetters} (
                operation_id,
                final_attempt_id,
                final_attempt_number,
                tenant_type,
                tenant_id,
                encoded_reason,
                moved_at
            ) SELECT
                :operation_id,
                :final_attempt_id,
                :final_attempt_number,
                o.tenant_type,
                o.tenant_id,
                :encoded_reason,
                :moved_at
            FROM {$this->schema->operationsTable()} o
            WHERE o.operation_id = :operation_id
            ",
            [
                'operation_id' => $claim->message()->operationId()->toString(),
                'final_attempt_id' => $data->finalAttemptId?->toString(),
                'final_attempt_number' => $data->finalAttemptNumber,
                'encoded_reason' => $this->protection->encrypt(
                    json_encode([
                        'version' => 1,
                        'reasonType' => $data->reasonType,
                        'reasonMessage' => $data->reasonMessage,
                    ], JSON_THROW_ON_ERROR),
                    new StorageProtectionContext(
                        StoragePurpose::DeadLetterReason,
                        $claim->message()->operationId()->toString(),
                        $claim->message()->operationId()->toString(),
                        $claim->message()->operationType(),
                        $claim->message()->schemaVersion(),
                        $claim->message()->tenant(),
                    ),
                ),
                'moved_at' => $this->sql->formatTimestamp($data->movedAt),
            ],
            ['encoded_reason' => ParameterType::BINARY],
        );
    }
}
