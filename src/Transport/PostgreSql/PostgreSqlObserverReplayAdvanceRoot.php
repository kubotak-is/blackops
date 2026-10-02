<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Throwable;

final class PostgreSqlObserverReplayAdvanceRoot
{
    private function __construct() {}

    /**
     * @param array{
     *     connection:Connection,
     *     schema:PostgreSqlJournalSchema,
     *     hook:?PostgreSqlObserverReplayPhaseHook,
     *     checkpoint:string,
     *     cursor:?string,
     *     delivered:int,
     *     auditId:string,
     *     now:?DateTimeImmutable,
     *     source:?JournalRecord
     * } $input
     */
    public static function run(#[\SensitiveParameter] array $input): void
    {
        if (
            array_keys($input) !== [
                'connection',
                'schema',
                'hook',
                'checkpoint',
                'cursor',
                'delivered',
                'auditId',
                'now',
                'source',
            ]
        ) {
            throw new RuntimeException('Replay native input is invalid.');
        }
        $connection = $input['connection'];
        $schema = $input['schema'];
        $hook = $input['hook'];
        $checkpoint = $input['checkpoint'];
        $cursor = $input['cursor'];
        $delivered = $input['delivered'];
        $auditId = $input['auditId'];
        $source = $input['source'];
        if (!$connection->isAutoCommit() || $connection->getTransactionNestingLevel() !== 0) {
            throw new RuntimeException('Replay mutation requires an idle auto-commit connection.');
        }
        $hook?->assertEntry($connection, $checkpoint);
        if ($hook !== null && $source === null) {
            throw new RuntimeException('Replay audit progress requires a selected record.');
        }
        $now = $input['now'] ?? new DateTimeImmutable('now');
        $table = $schema->observerReplayCheckpointTable();
        $recordId = PostgreSqlObserverReplayIdentity::recordIdFromCursor($cursor);
        try {
            $connection->beginTransaction();
            $affected = $connection->executeStatement(
                "UPDATE {$table} SET cursor_record_id=:cursor, selected_count=selected_count+:count, delivered_count=delivered_count+:count, first_record_id=COALESCE(first_record_id, :record_id), last_record_id=:record_id, state=:state, updated_at=:at WHERE checkpoint_id=:id AND state='running'",
                [
                    'cursor' => $cursor,
                    'count' => $delivered,
                    'record_id' => $recordId,
                    'state' => 'running',
                    'at' => $now->format('Y-m-d H:i:s.uP'),
                    'id' => $checkpoint,
                ],
            );
            if ($affected !== 1) {
                throw new RuntimeException('Replay checkpoint ownership was lost.');
            }
            $auditAffected = $connection->executeStatement(
                "UPDATE {$schema->observerReplayAuditTable()} SET selected_count=selected_count+:count, delivered_count=delivered_count+:count, first_record_id=COALESCE(first_record_id, :record_id), last_record_id=:record_id WHERE audit_id=:audit AND checkpoint_id=:id AND state='started'",
                [
                    'count' => $delivered,
                    'record_id' => $recordId,
                    'audit' => $auditId,
                    'id' => $checkpoint,
                ],
            );
            if ($auditAffected !== 1) {
                throw new RuntimeException('Replay audit ownership was lost.');
            }
            if ($source !== null) {
                $hook?->progressed($connection, $checkpoint, $auditId, $cursor ?? '', $source);
            }
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackException) {
                    $exception = new RuntimeException('Replay advance rollback failed.', previous: $rollbackException);
                }
            }
            throw $exception;
        }
    }
}
