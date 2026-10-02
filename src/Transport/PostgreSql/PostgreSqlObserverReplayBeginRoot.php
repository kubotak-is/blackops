<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PostgreSqlObserverReplayBeginRoot
{
    private function __construct() {}

    /**
     * @param array{
     *     connection:Connection,
     *     schema:PostgreSqlJournalSchema,
     *     checkpointWriter:PostgreSqlObserverReplayCheckpointWriter,
     *     auditWriter:PostgreSqlObserverReplayAuditWriter,
     *     hook:?PostgreSqlObserverReplayPhaseHook,
     *     request:PostgreSqlObserverReplayBeginRequest
     * } $input
     */
    public static function run(#[\SensitiveParameter] array $input): PostgreSqlObserverReplayBinding
    {
        if (
            array_keys($input) !== [
                'connection',
                'schema',
                'checkpointWriter',
                'auditWriter',
                'hook',
                'request',
            ]
        ) {
            throw new RuntimeException('Replay native input is invalid.');
        }
        $connection = $input['connection'];
        $schema = $input['schema'];
        $request = $input['request'];
        if (!$connection->isAutoCommit() || $connection->getTransactionNestingLevel() !== 0) {
            throw new RuntimeException('Replay mutation requires an idle auto-commit connection.');
        }
        PostgreSqlObserverReplayIdentity::assertCheckpoint($request->checkpoint);
        $input['hook']?->assertEntry($connection, $request->checkpoint);
        $now = $request->now ?? new DateTimeImmutable('now');
        $selectorHash = hash('sha256', PostgreSqlObserverReplayIdentity::selectorKey($request->selector));
        $targets = $request->targets;
        sort($targets);
        $targetHash = hash('sha256', json_encode(array_values($targets), JSON_THROW_ON_ERROR));
        $table = $schema->observerReplayCheckpointTable();
        $locked = $connection->fetchOne('SELECT pg_try_advisory_lock(hashtext(:checkpoint))', [
            'checkpoint' => $request->checkpoint,
        ]);
        if (!in_array($locked, [true, 't', 1, '1'], strict: true)) {
            throw new InvalidArgumentException('Replay checkpoint is already running.');
        }
        try {
            $connection->beginTransaction();
            return PostgreSqlObserverReplayBeginTransaction::run([
                'connection' => $connection,
                'checkpointWriter' => $input['checkpointWriter'],
                'auditWriter' => $input['auditWriter'],
                'hook' => $input['hook'],
                'request' => $request,
                'selectorHash' => $selectorHash,
                'targetHash' => $targetHash,
                'table' => $table,
                'now' => $now,
            ]);
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackException) {
                    $exception = new RuntimeException(
                        'Replay transaction rollback failed.',
                        previous: $rollbackException,
                    );
                }
            }
            try {
                $unlocked = $connection->fetchOne('SELECT pg_advisory_unlock(hashtext(:checkpoint))', [
                    'checkpoint' => $request->checkpoint,
                ]);
                if (!in_array($unlocked, [true, 't', 1, '1'], strict: true)) {
                    throw new RuntimeException('Replay checkpoint advisory lock was not owned.');
                }
            } catch (Throwable $unlockException) {
                $exception = new RuntimeException('Replay checkpoint unlock failed.', previous: $unlockException);
            }
            throw $exception;
        }
    }
}
