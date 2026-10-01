<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationAuditCompletion;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class PostgreSqlStorageProtectionRotationAudit
{
    public function __construct(
        #[\SensitiveParameter]
        private Connection $connection,
        #[\SensitiveParameter]
        private ?PostgreSqlStorageProtectionRotationPhaseHook $phaseHook = null,
    ) {}

    public function begin(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $audit,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): string {
        $now = new DateTimeImmutable()->format('Y-m-d H:i:sP');
        $auditId = bin2hex(random_bytes(16));
        $this->connection->beginTransaction();
        try {
            $this->assertCheckpointScope($checkpoint, $scope);
            $this->claimCheckpoint($checkpoint, $scope, $now);
            $this->recoverInterrupted($audit, $scope, $now);
            $this->insertAudit($audit, $auditId, $scope, $now);
            if ($this->phaseHook !== null) {
                $this->phaseHook->started($this->connection, $auditId, $scope);
            }
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        return $auditId;
    }

    private function assertCheckpointScope(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
    ): void {
        $existing = $this->connection->fetchOne("SELECT scope_hash FROM {$checkpoint} WHERE checkpoint_id = ?", [
            $scope->checkpoint,
        ]);
        if ($existing !== false && (string) $existing !== $scope->scopeHash()) {
            throw new \InvalidArgumentException('Rotation checkpoint scope does not match.');
        }
    }

    private function claimCheckpoint(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        string $now,
    ): void {
        $updated = $this->connection->executeStatement(
            "INSERT INTO {$checkpoint} (checkpoint_id, scope_hash, state, cursor_value, failure_fingerprint, updated_at) VALUES (?, ?, 'running', NULL, NULL, ?) ON CONFLICT (checkpoint_id) DO UPDATE SET state='running', failure_fingerprint=NULL, updated_at=EXCLUDED.updated_at WHERE {$checkpoint}.scope_hash = EXCLUDED.scope_hash",
            [$scope->checkpoint, $scope->scopeHash(), $now],
        );
        if ($updated !== 1) {
            throw new \RuntimeException('Rotation checkpoint could not be claimed.');
        }
    }

    private function recoverInterrupted(
        #[\SensitiveParameter]
        string $audit,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        string $now,
    ): void {
        $phaseHook = $this->phaseHook;
        if ($phaseHook === null) {
            $this->connection->executeStatement(
                "UPDATE {$audit} SET state='failed', failed_count=GREATEST(failed_count, 1), failure_fingerprint=?, finished_at=? WHERE checkpoint_id=? AND scope_hash=? AND state='started' AND finished_at IS NULL",
                [
                    'v1:' . hash('sha256', $scope->scopeHash() . ':interrupted'),
                    $now,
                    $scope->checkpoint,
                    $scope->scopeHash(),
                ],
            );

            return;
        }
        $interrupted = $this->connection->fetchFirstColumn(
            "SELECT audit_id FROM {$audit} WHERE checkpoint_id=? AND scope_hash=? AND state='started' AND finished_at IS NULL ORDER BY audit_id LIMIT 101 FOR UPDATE",
            [$scope->checkpoint, $scope->scopeHash()],
        );
        if (count($interrupted) > 100) {
            throw new \RuntimeException('Rotation interruption recovery is too large.');
        }
        $auditIds = $this->interruptedIds($interrupted);
        foreach ($auditIds as $interruptedId) {
            $updated = $this->connection->executeStatement(
                "UPDATE {$audit} SET state='failed', failed_count=GREATEST(failed_count, 1), failure_fingerprint=?, finished_at=? WHERE audit_id=? AND checkpoint_id=? AND scope_hash=? AND state='started' AND finished_at IS NULL",
                [
                    'v1:' . hash('sha256', $scope->scopeHash() . ':interrupted'),
                    $now,
                    $interruptedId,
                    $scope->checkpoint,
                    $scope->scopeHash(),
                ],
            );
            if ($updated !== 1) {
                throw new \RuntimeException('Rotation interruption audit could not be finalized.');
            }
            $phaseHook->interrupted($this->connection, $interruptedId, $scope);
        }
    }

    /**
     * @param list<mixed> $interrupted
     * @return list<string>
     */
    private function interruptedIds(#[\SensitiveParameter] array $interrupted): array
    {
        $auditIds = [];
        foreach ($interrupted as $interruptedId) {
            if (!is_string($interruptedId) || preg_match('/\A[a-f0-9]{32}\z/D', $interruptedId) !== 1) {
                throw new \RuntimeException('Rotation interruption audit identifier is invalid.');
            }
            $auditIds[] = $interruptedId;
        }

        return $auditIds;
    }

    private function insertAudit(
        #[\SensitiveParameter]
        string $audit,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        string $now,
    ): void {
        $auditUpdated = $this->connection->executeStatement(
            "INSERT INTO {$audit} (audit_id, checkpoint_id, scope_hash, purpose, old_key_id, new_key_id, actor, reason, state, selected_count, rotated_count, skipped_count, failed_count, started_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'started', 0, 0, 0, 0, ?)",
            [
                $auditId,
                $scope->checkpoint,
                $scope->scopeHash(),
                $scope->purpose->value,
                $scope->oldKeyId,
                $scope->newKeyId,
                $scope->actor,
                $scope->reason,
                $now,
            ],
        );
        if ($auditUpdated !== 1) {
            throw new \RuntimeException('Rotation audit could not be recorded.');
        }
    }

    public function advance(
        #[\SensitiveParameter]
        string $table,
        #[\SensitiveParameter]
        StorageProtectionRotationScope $scope,
        #[\SensitiveParameter]
        string $cursor,
    ): void {
        $updated = $this->connection->executeStatement(
            "UPDATE {$table} SET cursor_value = ?, updated_at = ? WHERE checkpoint_id = ? AND scope_hash = ?",
            [$cursor, new DateTimeImmutable()->format('Y-m-d H:i:sP'), $scope->checkpoint, $scope->scopeHash()],
        );
        if ($updated !== 1) {
            throw new \RuntimeException('Rotation checkpoint could not be advanced.');
        }
    }

    public function progress(
        #[\SensitiveParameter]
        string $audit,
        #[\SensitiveParameter]
        string $auditId,
        #[\SensitiveParameter]
        StorageProtectionRotationCounts $counts,
    ): void {
        $updated = $this->connection->executeStatement(
            "UPDATE {$audit} SET selected_count=?, rotated_count=?, skipped_count=?, failed_count=? WHERE audit_id=? AND state='started'",
            [$counts->selected, $counts->rotated, $counts->skipped, $counts->failed, $auditId],
        );
        if ($updated !== 1) {
            throw new \RuntimeException('Rotation audit progress could not be recorded.');
        }
    }

    public function finish(
        #[\SensitiveParameter]
        string $checkpoint,
        #[\SensitiveParameter]
        string $audit,
        #[\SensitiveParameter]
        StorageProtectionRotationAuditCompletion $completion,
    ): void {
        $scope = $completion->scope;
        $counts = $completion->counts;
        $state = $completion->state;
        $auditId = $completion->auditId;
        $failureFingerprint = $completion->failureFingerprint;
        $now = new DateTimeImmutable()->format('Y-m-d H:i:sP');
        $auditState = $state === 'failed' ? 'failed' : 'complete';
        $finishedAt = $now;
        $this->connection->beginTransaction();
        try {
            $checkpointUpdated = $this->connection->executeStatement(
                "UPDATE {$checkpoint} SET state = ?, failure_fingerprint = ?, updated_at = ? WHERE checkpoint_id = ? AND scope_hash = ?",
                [$state, $failureFingerprint, $now, $scope->checkpoint, $scope->scopeHash()],
            );
            if ($checkpointUpdated !== 1) {
                throw new \RuntimeException('Rotation checkpoint could not be finalized.');
            }
            $auditUpdated = $this->connection->executeStatement(
                "UPDATE {$audit} SET state = ?, selected_count = ?, rotated_count = ?, skipped_count = ?, failed_count = ?, failure_fingerprint = ?, finished_at = ? WHERE audit_id = ? AND checkpoint_id = ? AND scope_hash = ?",
                [
                    $auditState,
                    $counts->selected,
                    $counts->rotated,
                    $counts->skipped,
                    $counts->failed,
                    $failureFingerprint,
                    $finishedAt,
                    $auditId,
                    $scope->checkpoint,
                    $scope->scopeHash(),
                ],
            );
            if ($auditUpdated !== 1) {
                throw new \RuntimeException('Rotation audit could not be finalized.');
            }
            if ($this->phaseHook !== null) {
                $this->phaseHook->finished($this->connection, $completion);
            }
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
