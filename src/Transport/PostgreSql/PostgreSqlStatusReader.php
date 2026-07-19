<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class PostgreSqlStatusReader
{
    private PostgreSqlDeferredOperationSchema $deferred;
    private PostgreSqlJournalSchema $journal;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
    ) {
        $this->deferred = new PostgreSqlDeferredOperationSchema($schema);
        $this->journal = new PostgreSqlJournalSchema($schema);
    }

    public function findSubject(OperationId $operationId): ?PostgreSqlStatusSubject
    {
        $transport = $this->transportSubject($operationId);
        $journal = $this->journalSubject($operationId);
        if ($transport === null && $journal === null) {
            return null;
        }

        if (
            $transport !== null
            && $journal !== null
            && (
                $transport['operation_id'] !== $journal['operation_id']
                || $transport['operation_type'] !== $journal['operation_type']
            )
        ) {
            throw PostgreSqlStatusReadFailed::integrity();
        }

        $identity = $transport ?? $journal;
        $actorId = $journal['origin_actor_id'] ?? null;
        $actorType = $journal['origin_actor_type'] ?? null;
        if (($actorId === null) !== ($actorType === null)) {
            throw PostgreSqlStatusReadFailed::integrity();
        }

        return new PostgreSqlStatusSubject(
            $identity['operation_id'],
            $identity['operation_type'],
            $actorId,
            $actorType,
        );
    }

    public function deferredState(OperationId $operationId): ?PostgreSqlStatusDeferredState
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    operation_id::text AS operation_id,
                    operation_type,
                    schema_version,
                    state,
                    next_sequence,
                    payload_purged_at IS NOT NULL AS payload_purged,
                    attempt_number,
                    current_attempt_id::text AS current_attempt_id,
                    current_attempt_started_at::text AS current_attempt_started_at,
                    available_at::text AS available_at
                FROM {$this->deferred->operationsTable()}
                WHERE operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlStatusReadFailed::storage();
        }
        if ($row === false) {
            return null;
        }

        try {
            return new PostgreSqlStatusDeferredState(
                $this->requiredString($row, 'operation_id'),
                $this->requiredString($row, 'operation_type'),
                $this->positiveInteger($row, 'schema_version'),
                LifecycleState::from($this->requiredString($row, 'state')),
                $this->positiveInteger($row, 'next_sequence'),
                $this->boolean($row, 'payload_purged'),
                $this->nonNegativeInteger($row, 'attempt_number'),
                $this->nullableString($row, 'current_attempt_id'),
                $this->nullableTime($row, 'current_attempt_started_at'),
                $this->requiredTime($row, 'available_at'),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof PostgreSqlStatusReadFailed) {
                throw $exception;
            }

            throw PostgreSqlStatusReadFailed::integrity();
        }
    }

    public function outcomeExists(OperationId $operationId): bool
    {
        return $this->rowExists($this->deferred->outcomesTable(), $operationId);
    }

    public function deadLetterExists(OperationId $operationId): bool
    {
        return $this->rowExists($this->deferred->deadLettersTable(), $operationId);
    }

    /** @return list<RetentionPurgeTarget> */
    public function purgeTargets(OperationId $operationId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT target
                FROM {$this->deferred->retentionPurgeAuditsTable()}
                WHERE operation_id = :operation_id
                ORDER BY purged_at ASC, audit_id ASC",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlStatusReadFailed::storage();
        }

        try {
            return array_map(fn(array $row): RetentionPurgeTarget => RetentionPurgeTarget::from($this->requiredString(
                $row,
                'target',
            )), $rows);
        } catch (Throwable $exception) {
            if ($exception instanceof PostgreSqlStatusReadFailed) {
                throw $exception;
            }

            throw PostgreSqlStatusReadFailed::integrity();
        }
    }

    /** @return array{operation_id: string, operation_type: string}|null */
    private function transportSubject(OperationId $operationId): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    operation_id::text AS operation_id,
                    operation_type
                FROM {$this->deferred->operationsTable()}
                WHERE operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlStatusReadFailed::storage();
        }
        if ($row === false) {
            return null;
        }

        return [
            'operation_id' => $this->requiredString($row, 'operation_id'),
            'operation_type' => $this->requiredString($row, 'operation_type'),
        ];
    }

    /**
     * @return array{
     *     operation_id: string,
     *     operation_type: string,
     *     origin_actor_id: string|null,
     *     origin_actor_type: string|null
     * }|null
     */
    private function journalSubject(OperationId $operationId): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    operation_id::text AS operation_id,
                    convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,type}' AS operation_type,
                    convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,origin,id}' AS origin_actor_id,
                    convert_from(encoded_record, 'UTF8')::jsonb #>> '{operation,actors,origin,type}' AS origin_actor_type
                FROM {$this->journal->journalTable()}
                WHERE operation_id = :operation_id
                ORDER BY sequence ASC
                LIMIT 1",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlStatusReadFailed::storage();
        }
        if ($row === false) {
            return null;
        }

        return [
            'operation_id' => $this->requiredString($row, 'operation_id'),
            'operation_type' => $this->requiredString($row, 'operation_type'),
            'origin_actor_id' => $this->nullableString($row, 'origin_actor_id'),
            'origin_actor_type' => $this->nullableString($row, 'origin_actor_type'),
        ];
    }

    private function rowExists(string $table, OperationId $operationId): bool
    {
        try {
            return $this->connection->fetchOne(
                "SELECT EXISTS (
                    SELECT 1
                    FROM {$table}
                    WHERE operation_id = :operation_id
                )",
                ['operation_id' => $operationId->toString()],
            ) === true;
        } catch (Throwable) {
            throw PostgreSqlStatusReadFailed::storage();
        }
    }

    /** @param array<string, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        /** @var mixed $value */
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw PostgreSqlStatusReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableString(array $row, string $key): ?string
    {
        /** @var mixed $value */
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw PostgreSqlStatusReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function positiveInteger(array $row, string $key): int
    {
        $value = $this->integer($row, $key);
        if ($value < 1) {
            throw PostgreSqlStatusReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nonNegativeInteger(array $row, string $key): int
    {
        $value = $this->integer($row, $key);
        if ($value < 0) {
            throw PostgreSqlStatusReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        /** @var mixed $value */
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw PostgreSqlStatusReadFailed::integrity();
    }

    /** @param array<string, mixed> $row */
    private function boolean(array $row, string $key): bool
    {
        return match ($row[$key] ?? null) {
            true, 1, '1', 't', 'true' => true,
            false, 0, '0', 'f', 'false' => false,
            default => throw PostgreSqlStatusReadFailed::integrity(),
        };
    }

    /** @param array<string, mixed> $row */
    private function requiredTime(array $row, string $key): DateTimeImmutable
    {
        return $this->toUtc($this->requiredString($row, $key));
    }

    /** @param array<string, mixed> $row */
    private function nullableTime(array $row, string $key): ?DateTimeImmutable
    {
        $value = $this->nullableString($row, $key);

        return $value === null ? null : $this->toUtc($value);
    }

    private function toUtc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value)->setTimezone(new DateTimeZone('UTC'));
    }
}
