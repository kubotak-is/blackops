<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Outcome\Exception\OutcomeStoreException;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Outcome\OutcomeStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlOutcomeStore implements OutcomeStore
{
    private PostgreSqlOutcomeSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        private PostgreSqlOutcomeCodec $codec = new PostgreSqlOutcomeCodec(),
    ) {
        $this->schema = new PostgreSqlOutcomeSchema($schema);
    }

    public function migrate(): void
    {
        try {
            foreach ($this->schema->statements() as $statement) {
                $this->connection->executeStatement($statement);
            }
        } catch (Throwable $exception) {
            throw new OutcomeStoreException('Failed to migrate PostgreSQL outcome schema.', previous: $exception);
        }
    }

    public function save(OutcomeRecord $record): void
    {
        $encoded = $this->codec->encode($record->outcome());
        $table = $this->schema->table();

        try {
            $inserted = $this->connection->executeStatement(
                "INSERT INTO {$table} (
                    operation_id,
                    outcome_type,
                    schema_version,
                    encoded_payload,
                    completed_at
                ) SELECT
                    :operation_id,
                    :outcome_type,
                    :schema_version,
                    convert_to(:encoded_payload, 'UTF8'),
                    :completed_at
                FROM {$this->schema->operationsTable()} o
                WHERE o.operation_id = :operation_id
                    AND o.state = 'completed'",
                [
                    'operation_id' => $record->operationId()->toString(),
                    'outcome_type' => $encoded->type,
                    'schema_version' => $encoded->schemaVersion,
                    'encoded_payload' => $encoded->payload,
                    'completed_at' => $record->completedAt()->format('Y-m-d H:i:s.uP'),
                ],
            );

            if ((int) $inserted !== 1) {
                throw new OutcomeStoreException('PostgreSQL outcome requires a completed operation.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof OutcomeStoreException) {
                throw $exception;
            }

            throw new OutcomeStoreException('Failed to save PostgreSQL outcome.', previous: $exception);
        }
    }

    public function find(OperationId $operationId): ?OutcomeRecord
    {
        $table = $this->schema->table();

        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    outcome_type,
                    schema_version,
                    convert_from(encoded_payload, 'UTF8') AS encoded_payload,
                    completed_at::text AS completed_at
                FROM {$table}
                WHERE operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );

            if ($row === false) {
                return null;
            }

            return new OutcomeRecord(
                $operationId,
                $this->codec->decode(
                    $this->string($row, 'outcome_type'),
                    $this->integer($row, 'schema_version'),
                    $this->string($row, 'encoded_payload'),
                ),
                new DateTimeImmutable($this->string($row, 'completed_at')),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof OutcomeStoreException) {
                throw $exception;
            }

            throw new OutcomeStoreException('Failed to find PostgreSQL outcome.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || !is_string($row[$key]) || $row[$key] === '') {
            throw new OutcomeStoreException('PostgreSQL outcome row contains an invalid string field.');
        }

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || !is_int($row[$key])) {
            throw new OutcomeStoreException('PostgreSQL outcome row contains an invalid integer field.');
        }

        return $row[$key];
    }
}
