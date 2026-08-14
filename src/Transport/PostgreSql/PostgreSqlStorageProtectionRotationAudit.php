<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Internal\StorageProtection\StorageProtectionRotationAuditCompletion;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationCounts;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class PostgreSqlStorageProtectionRotationAudit
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function begin(string $checkpoint, string $audit, StorageProtectionRotationScope $scope): string
    {
        $now = new DateTimeImmutable()->format('Y-m-d H:i:sP');
        $auditId = bin2hex(random_bytes(16));
        $this->connection->beginTransaction();
        try {
            $existing = $this->connection->fetchOne("SELECT scope_hash FROM {$checkpoint} WHERE checkpoint_id = ?", [
                $scope->checkpoint,
            ]);
            if ($existing !== false && (string) $existing !== $scope->scopeHash()) {
                throw new \InvalidArgumentException('Rotation checkpoint scope does not match.');
            }
            $updated = $this->connection->executeStatement(
                "INSERT INTO {$checkpoint} (checkpoint_id, scope_hash, state, cursor_value, failure_fingerprint, updated_at) VALUES (?, ?, 'running', NULL, NULL, ?) ON CONFLICT (checkpoint_id) DO UPDATE SET state='running', failure_fingerprint=NULL, updated_at=EXCLUDED.updated_at WHERE {$checkpoint}.scope_hash = EXCLUDED.scope_hash",
                [$scope->checkpoint, $scope->scopeHash(), $now],
            );
            if ($updated !== 1) {
                throw new \RuntimeException('Rotation checkpoint could not be claimed.');
            }
            $this->connection->executeStatement(
                "UPDATE {$audit} SET state='failed', failed_count=GREATEST(failed_count, 1), failure_fingerprint=?, finished_at=? WHERE checkpoint_id=? AND scope_hash=? AND state='started' AND finished_at IS NULL",
                [
                    'v1:' . hash('sha256', $scope->scopeHash() . ':interrupted'),
                    $now,
                    $scope->checkpoint,
                    $scope->scopeHash(),
                ],
            );
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
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        return $auditId;
    }

    public function advance(string $table, StorageProtectionRotationScope $scope, string $cursor): void
    {
        $updated = $this->connection->executeStatement(
            "UPDATE {$table} SET cursor_value = ?, updated_at = ? WHERE checkpoint_id = ? AND scope_hash = ?",
            [$cursor, new DateTimeImmutable()->format('Y-m-d H:i:sP'), $scope->checkpoint, $scope->scopeHash()],
        );
        if ($updated !== 1) {
            throw new \RuntimeException('Rotation checkpoint could not be advanced.');
        }
    }

    public function progress(string $audit, string $auditId, StorageProtectionRotationCounts $counts): void
    {
        $updated = $this->connection->executeStatement(
            "UPDATE {$audit} SET selected_count=?, rotated_count=?, skipped_count=?, failed_count=? WHERE audit_id=? AND state='started'",
            [$counts->selected, $counts->rotated, $counts->skipped, $counts->failed, $auditId],
        );
        if ($updated !== 1) {
            throw new \RuntimeException('Rotation audit progress could not be recorded.');
        }
    }

    public function finish(
        string $checkpoint,
        string $audit,
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
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
