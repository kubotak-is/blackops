<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationAuditCompletion;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationMode;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationResult;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlStorageProtectionRotationBatch
{
    private PostgreSqlStorageProtectionRotationRowExecutor $executor;

    public function __construct(
        #[\SensitiveParameter]
        Connection $connection,
        #[\SensitiveParameter]
        BopdEnvelopeCodec $codec,
        #[\SensitiveParameter]
        private PostgreSqlStorageProtectionRotationQuery $query,
        #[\SensitiveParameter]
        private PostgreSqlStorageProtectionRotationAudit $audit,
        #[\SensitiveParameter]
        private string $schema = 'blackops',
        #[\SensitiveParameter]
        ?PostgreSqlStorageProtectionRotationPhaseHook $phaseHook = null,
    ) {
        $this->executor = new PostgreSqlStorageProtectionRotationRowExecutor(
            $connection,
            $codec,
            $query,
            $audit,
            $phaseHook,
        );
    }

    public function run(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        StorageProtectionRotationMode $mode,
    ): StorageProtectionRotationResult {
        $target = $this->query->target($scope->purpose);
        $tables = $this->tables($target);
        $cursor = $this->cursor($tables->checkpoint, $scope, $mode);
        $rows = $this->query->rows($tables->table, $target, $scope, $cursor, $mode);
        $auditId = $this->beginAudit($tables, $scope, $mode);
        $counts = new StorageProtectionRotationCounts(0, 0, 0, 0);
        $storage = new PostgreSqlStorageProtectionRotationRowStorage($target, $tables->table);
        foreach ($rows as $row) {
            $result = $this->executor->execute(
                new PostgreSqlStorageProtectionRotationRowRequest(
                    $scope,
                    $mode,
                    $storage,
                    new PostgreSqlStorageProtectionRotationRowAudit(
                        $tables->checkpoint,
                        $tables->audit,
                        $auditId,
                        $counts,
                    ),
                    $row,
                ),
            );
            $counts = $this->add($counts, $result);
            if ($result->failedRow) {
                break;
            }
        }
        return $this->complete(
            $scope,
            $tables,
            new PostgreSqlStorageProtectionRotationBatchExecution($target, $mode, $auditId, $rows, $counts),
        );
    }

    private function tables(PostgreSqlStorageProtectionRotationTarget $target): PostgreSqlStorageProtectionRotationBatchTables
    {
        $schema = PostgreSqlIdentifier::schema($this->schema);
        return new PostgreSqlStorageProtectionRotationBatchTables(
            $schema->qualify($target->table),
            $schema->qualify('storage_protection_rotation_checkpoints'),
            $schema->qualify('storage_protection_rotation_audits'),
        );
    }

    private function cursor(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        StorageProtectionRotationMode $mode,
    ): ?string {
        if (!$mode->writes()) {
            return null;
        }
        return $this->query->cursor($checkpoint, $scope);
    }

    private function beginAudit(
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationBatchTables $tables,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        StorageProtectionRotationMode $mode,
    ): ?string {
        if (!$mode->writes()) {
            return null;
        }
        return $this->audit->begin($tables->checkpoint, $tables->audit, $scope);
    }

    private function add(
        #[\SensitiveParameter]
        StorageProtectionRotationCounts $counts,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationRowResult $result,
    ): StorageProtectionRotationCounts {
        return new StorageProtectionRotationCounts(
            $counts->selected + $result->selected,
            $counts->rotated + $result->rotated,
            $counts->skipped + $result->skipped,
            $counts->failed + $result->failed,
        );
    }

    private function complete(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationBatchTables $tables,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationBatchExecution $execution,
    ): StorageProtectionRotationResult {
        try {
            $remaining = $this->query->remaining($tables->table, $execution->target, $scope);
        } catch (Throwable $exception) {
            $this->finishFailure($scope, $tables, $execution);
            throw $exception;
        }
        $state = $this->state($execution->counts, count($execution->rows), $scope->batchSize);
        $fingerprint = $this->fingerprint($scope, $execution->counts->failed);
        if ($execution->mode->writes()) {
            $this->audit->finish(
                $tables->checkpoint,
                $tables->audit,
                new StorageProtectionRotationAuditCompletion(
                    $scope,
                    $execution->counts,
                    $state,
                    $execution->auditId,
                    $fingerprint,
                ),
            );
        }
        return new StorageProtectionRotationResult(
            $scope->purpose->value,
            $scope->oldKeyId,
            $scope->newKeyId,
            $scope->checkpoint,
            $execution->counts,
            $remaining,
            $state,
        );
    }

    private function state(
        #[\SensitiveParameter]
        StorageProtectionRotationCounts $counts,
        int $rows,
        int $batchSize,
    ): string {
        if ($counts->failed > 0) {
            return 'failed';
        }
        if ($rows < $batchSize) {
            return 'complete';
        }
        return 'running';
    }

    private function fingerprint(#[\SensitiveParameter] StorageProtectionRotationScope $scope, int $failed): ?string
    {
        if ($failed === 0) {
            return null;
        }
        return 'v1:' . hash('sha256', $scope->scopeHash() . ':' . $failed);
    }

    private function finishFailure(
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationBatchTables $tables,
        #[\SensitiveParameter]
        PostgreSqlStorageProtectionRotationBatchExecution $execution,
    ): void {
        if (!$execution->mode->writes() || $execution->auditId === null) {
            return;
        }
        try {
            $this->audit->finish(
                $tables->checkpoint,
                $tables->audit,
                new StorageProtectionRotationAuditCompletion(
                    $scope,
                    new StorageProtectionRotationCounts(
                        $execution->counts->selected,
                        $execution->counts->rotated,
                        $execution->counts->skipped,
                        max(1, $execution->counts->failed),
                    ),
                    'failed',
                    $execution->auditId,
                    'v1:' . hash('sha256', $scope->scopeHash() . ':storage'),
                ),
            );

            // Best-effort audit reconciliation must not mask the original storage failure.
            // @mago-expect lint:no-empty-catch-clause
        } catch (Throwable) {
        }
    }
}
