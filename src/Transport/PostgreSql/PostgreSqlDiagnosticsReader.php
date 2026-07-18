<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class PostgreSqlDiagnosticsReader
{
    private PostgreSqlDeferredOperationSchema $schema;

    public function __construct(
        private Connection $connection,
        string $schema = 'blackops',
        private TimeCodec $time = new TimeCodec(),
    ) {
        $this->schema = new PostgreSqlDeferredOperationSchema($schema);
    }

    public function deferredState(OperationId $operationId): ?PostgreSqlDiagnosticsDeferredState
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
                    current_attempt_started_at::text AS current_attempt_started_at
                FROM {$this->schema->operationsTable()}
                WHERE operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlDiagnosticsReadFailed::storage();
        }

        if ($row === false) {
            return null;
        }

        try {
            return new PostgreSqlDiagnosticsDeferredState(
                $this->requiredString($row, 'operation_id'),
                $this->requiredString($row, 'operation_type'),
                $this->positiveInteger($row, 'schema_version'),
                LifecycleState::from($this->requiredString($row, 'state')),
                $this->positiveInteger($row, 'next_sequence'),
                $this->boolean($row, 'payload_purged'),
                $this->nonNegativeInteger($row, 'attempt_number'),
                $this->nullableString($row, 'current_attempt_id'),
                $this->nullableTime($row, 'current_attempt_started_at'),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof PostgreSqlDiagnosticsReadFailed) {
                throw $exception;
            }

            throw PostgreSqlDiagnosticsReadFailed::integrity();
        }
    }

    public function deadLetter(OperationId $operationId): ?PostgreSqlDiagnosticsDeadLetter
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT
                    operation_id::text AS operation_id,
                    final_attempt_id::text AS final_attempt_id,
                    final_attempt_number,
                    reason_type,
                    moved_at::text AS moved_at
                FROM {$this->schema->deadLettersTable()}
                WHERE operation_id = :operation_id",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlDiagnosticsReadFailed::storage();
        }

        if ($row === false) {
            return null;
        }

        try {
            $number = $this->nullableInteger($row, 'final_attempt_number');
            if ($number !== null && $number < 1) {
                throw PostgreSqlDiagnosticsReadFailed::integrity();
            }

            return new PostgreSqlDiagnosticsDeadLetter(
                $this->requiredString($row, 'operation_id'),
                $this->nullableString($row, 'final_attempt_id'),
                $number,
                $this->requiredString($row, 'reason_type'),
                $this->requiredTime($row, 'moved_at'),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof PostgreSqlDiagnosticsReadFailed) {
                throw $exception;
            }

            throw PostgreSqlDiagnosticsReadFailed::integrity();
        }
    }

    /** @return list<PostgreSqlDiagnosticsPurgeAudit> */
    public function purgeAudits(OperationId $operationId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT
                    target,
                    affected_count,
                    purged_at::text AS purged_at
                FROM {$this->schema->retentionPurgeAuditsTable()}
                WHERE operation_id = :operation_id
                ORDER BY purged_at ASC, audit_id ASC",
                ['operation_id' => $operationId->toString()],
            );
        } catch (Throwable) {
            throw PostgreSqlDiagnosticsReadFailed::storage();
        }

        try {
            return array_map(
                fn(array $row): PostgreSqlDiagnosticsPurgeAudit => new PostgreSqlDiagnosticsPurgeAudit(
                    RetentionPurgeTarget::from($this->requiredString($row, 'target')),
                    $this->positiveInteger($row, 'affected_count'),
                    $this->requiredTime($row, 'purged_at'),
                ),
                $rows,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof PostgreSqlDiagnosticsReadFailed) {
                throw $exception;
            }

            throw PostgreSqlDiagnosticsReadFailed::integrity();
        }
    }

    /** @param array<string, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        /** @var mixed $value */
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw PostgreSqlDiagnosticsReadFailed::integrity();
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
            throw PostgreSqlDiagnosticsReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function positiveInteger(array $row, string $key): int
    {
        $value = $this->integer($row, $key);
        if ($value < 1) {
            throw PostgreSqlDiagnosticsReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nonNegativeInteger(array $row, string $key): int
    {
        $value = $this->integer($row, $key);
        if ($value < 0) {
            throw PostgreSqlDiagnosticsReadFailed::integrity();
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableInteger(array $row, string $key): ?int
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return $this->integer($row, $key);
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $key): int
    {
        /** @var mixed $value */
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw PostgreSqlDiagnosticsReadFailed::integrity();
    }

    /** @param array<string, mixed> $row */
    private function boolean(array $row, string $key): bool
    {
        return match ($row[$key] ?? null) {
            true, 1, '1', 't', 'true' => true,
            false, 0, '0', 'f', 'false' => false,
            default => throw PostgreSqlDiagnosticsReadFailed::integrity(),
        };
    }

    /** @param array<string, mixed> $row */
    private function requiredTime(array $row, string $key): string
    {
        return $this->formatTime($this->requiredString($row, $key));
    }

    /** @param array<string, mixed> $row */
    private function nullableTime(array $row, string $key): ?string
    {
        $value = $this->nullableString($row, $key);

        return $value === null ? null : $this->formatTime($value);
    }

    private function formatTime(string $value): string
    {
        return $this->time->format(new DateTimeImmutable($value));
    }
}
