<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use Doctrine\DBAL\Connection;

final readonly class PostgreSqlDeadLetterStore
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlDeferredOperationSchema $schema,
        private PostgreSqlDeferredOperationLifecycleSql $sql,
    ) {}

    public function insert(OperationClaim $claim, OperationDeadLetteredData $data): void
    {
        $deadLetters = $this->schema->deadLettersTable();
        $this->connection->executeStatement(
            "INSERT INTO {$deadLetters} (
                operation_id,
                final_attempt_id,
                final_attempt_number,
                reason_type,
                reason_message,
                moved_at
            ) VALUES (
                :operation_id,
                :final_attempt_id,
                :final_attempt_number,
                :reason_type,
                :reason_message,
                :moved_at
            )",
            [
                'operation_id' => $claim->message()->operationId()->toString(),
                'final_attempt_id' => $data->finalAttemptId?->toString(),
                'final_attempt_number' => $data->finalAttemptNumber,
                'reason_type' => $data->reasonType,
                'reason_message' => $data->reasonMessage,
                'moved_at' => $this->sql->formatTimestamp($data->movedAt),
            ],
        );
    }
}
