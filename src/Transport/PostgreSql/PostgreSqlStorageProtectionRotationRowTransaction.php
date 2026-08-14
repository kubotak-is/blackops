<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlStorageProtectionRotationRowTransaction
{
    public function __construct(
        private Connection $connection,
        private PostgreSqlStorageProtectionRotationAudit $audit,
        private PostgreSqlStorageProtectionRotationColumnExecutor $columns,
    ) {}

    public function execute(PostgreSqlStorageProtectionRotationRowRequest $request): PostgreSqlStorageProtectionRotationRowResult
    {
        if ($request->mode->writes()) {
            $this->connection->beginTransaction();
        }
        $result = $this->columns($request);
        if (!$request->mode->writes()) {
            return $result;
        }
        return $this->transaction($request, $result);
    }

    private function transaction(
        PostgreSqlStorageProtectionRotationRowRequest $request,
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
        try {
            $this->advance($request, $result);
            $this->connection->commit();
            return $result;
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
    }

    private function columns(PostgreSqlStorageProtectionRotationRowRequest $request): PostgreSqlStorageProtectionRotationRowResult
    {
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
        PostgreSqlStorageProtectionRotationRowRequest $request,
        PostgreSqlStorageProtectionRotationRowResult $result,
    ): void {
        $this->audit->advance($request->audit->checkpoint, $request->scope, $this->cursor($request));
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
    }

    private function cursor(PostgreSqlStorageProtectionRotationRowRequest $request): string
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
