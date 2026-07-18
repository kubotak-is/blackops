<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsFailureKind;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsPurgeAudit;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReader;
use BlackOps\Transport\PostgreSql\PostgreSqlDiagnosticsReadFailed;

final readonly class PostgreSqlDiagnosticsSourceReader implements DiagnosticsSourceReader
{
    public function __construct(
        private PostgreSqlDiagnosticsReader $reader,
    ) {}

    public function deferredState(OperationId $operationId): ?DiagnosticsDeferredState
    {
        try {
            $state = $this->reader->deferredState($operationId);
        } catch (PostgreSqlDiagnosticsReadFailed $exception) {
            throw $this->failure($exception);
        }
        if ($state === null) {
            return null;
        }

        return new DiagnosticsDeferredState(
            $state->operationId,
            $state->type,
            $state->schemaVersion,
            $state->state,
            $state->nextSequence,
            $state->payloadPurged,
            $state->attemptNumber,
            $state->currentAttemptId,
            $state->currentAttemptStartedAt,
        );
    }

    public function deadLetter(OperationId $operationId): ?DiagnosticsDeadLetter
    {
        try {
            $deadLetter = $this->reader->deadLetter($operationId);
        } catch (PostgreSqlDiagnosticsReadFailed $exception) {
            throw $this->failure($exception);
        }
        if ($deadLetter === null) {
            return null;
        }

        return new DiagnosticsDeadLetter(
            $deadLetter->operationId,
            $deadLetter->finalAttemptId,
            $deadLetter->finalAttemptNumber,
            $deadLetter->reasonType,
            $deadLetter->movedAt,
        );
    }

    public function purgeAudits(OperationId $operationId): array
    {
        try {
            $audits = $this->reader->purgeAudits($operationId);
        } catch (PostgreSqlDiagnosticsReadFailed $exception) {
            throw $this->failure($exception);
        }

        return array_values(array_map(
            static fn(PostgreSqlDiagnosticsPurgeAudit $audit): DiagnosticsPurgeAudit => new DiagnosticsPurgeAudit(
                $audit->target,
                $audit->affectedCount,
                $audit->purgedAt,
            ),
            $audits,
        ));
    }

    private function failure(PostgreSqlDiagnosticsReadFailed $exception): OperationDiagnosticsException
    {
        return $exception->kind === PostgreSqlDiagnosticsFailureKind::Storage
            ? OperationDiagnosticsException::storageFailed()
            : OperationDiagnosticsException::integrityFailed();
    }
}
