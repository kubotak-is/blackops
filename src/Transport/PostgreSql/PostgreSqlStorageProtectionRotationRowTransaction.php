<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlStorageProtectionRotationRowTransaction
{
    public function __construct(
        #[\SensitiveParameter]
        private Connection $connection,
        #[\SensitiveParameter]
        private PostgreSqlStorageProtectionRotationAudit $audit,
        #[\SensitiveParameter]
        private PostgreSqlStorageProtectionRotationColumnExecutor $columns,
        #[\SensitiveParameter]
        private ?PostgreSqlStorageProtectionRotationPhaseHook $phaseHook = null,
    ) {}

    public function execute(
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
    ): PostgreSqlStorageProtectionRotationRowResult {
        if ($request->mode->writes()) {
            $this->connection->beginTransaction();
            if ($this->phaseHook !== null) {
                try {
                    $this->phaseHook->assertRow($this->connection, $request);
                } catch (Throwable $exception) {
                    $this->rollBack();
                    throw $exception;
                }
            }
        }
        $result = $this->columns($request);
        if (!$request->mode->writes()) {
            return $result;
        }
        return $this->transaction($request, $result);
    }

    private function transaction(
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowResult $result,
    ): PostgreSqlStorageProtectionRotationRowResult {
        if ($result->failedRow) {
            $this->rollBack();
            return new PostgreSqlStorageProtectionRotationRowResult(
                $result->selected,
                0,
                $result->skipped,
                $result->failed,
                true,
            );
        }
        $cursor = null;
        try {
            $cursor = $this->advance($request, $result);
        } catch (Throwable) {
            $this->rollBack();
            return new PostgreSqlStorageProtectionRotationRowResult(
                $result->selected,
                0,
                $result->skipped,
                $result->failed + 1,
                true,
            );
        }
        if ($this->phaseHook !== null) {
            try {
                $this->phaseHook->progressed($this->connection, $request, $cursor);
            } catch (Throwable $exception) {
                $this->rollBack();
                throw $exception;
            }
        }
        try {
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
        return $result;
    }

    private function columns(
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
    ): PostgreSqlStorageProtectionRotationRowResult {
        $selected = 0;
        $rotated = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($request->storage->target->columns as $column) {
            try {
                $result = $this->columns->execute($request, $column);
                $selected += $result->selected;
                $rotated += $result->rotated;
                $skipped += $result->skipped;
                $failed += (int) $result->failed;
            } catch (Throwable) {
                $failed++;
            }
        }
        return new PostgreSqlStorageProtectionRotationRowResult($selected, $rotated, $skipped, $failed, $failed > 0);
    }

    private function advance(
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowRequest $request,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowResult $result,
    ): string {
        $cursor = $this->cursor($request);
        $this->audit->advance($request->audit->checkpoint, $request->scope, $cursor);
        if ($request->audit->auditId !== null) {
            $this->audit->progress(
                $request->audit->audit,
                $request->audit->auditId,
                new StorageProtectionRotationCounts(
                    $request->audit->totals->selected + $result->selected,
                    $request->audit->totals->rotated + $result->rotated,
                    $request->audit->totals->skipped + $result->skipped,
                    $request->audit->totals->failed + $result->failed,
                ),
            );
        }

        return $cursor;
    }

    private function cursor(#[\SensitiveParameter] PostgreSqlStorageProtectionRotationRowRequest $request): string
    {
        $target = $request->storage->target;
        if ($target->hasCompositeIdentity()) {
            return (string) $request->row['scope_version'] . ':' . (string) $request->row['scope_hash'];
        }
        return (string) $request->row[$target->identity];
    }

    private function rollBack(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }
}
