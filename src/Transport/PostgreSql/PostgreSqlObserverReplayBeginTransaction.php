<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class PostgreSqlObserverReplayBeginTransaction
{
    private function __construct() {}

    /**
     * @param array{
     *     connection:Connection,
     *     checkpointWriter:PostgreSqlObserverReplayCheckpointWriter,
     *     auditWriter:PostgreSqlObserverReplayAuditWriter,
     *     hook:?PostgreSqlObserverReplayPhaseHook,
     *     request:PostgreSqlObserverReplayBeginRequest,
     *     selectorHash:string,
     *     targetHash:string,
     *     table:string,
     *     now:DateTimeImmutable
     * } $input
     */
    public static function run(#[\SensitiveParameter] array $input): PostgreSqlObserverReplayBinding
    {
        if (
            array_keys($input) !== [
                'connection',
                'checkpointWriter',
                'auditWriter',
                'hook',
                'request',
                'selectorHash',
                'targetHash',
                'table',
                'now',
            ]
        ) {
            throw new \RuntimeException('Replay native input is invalid.');
        }
        $row = $input['checkpointWriter']->row($input['table'], $input['request']->checkpoint);
        if (
            $row !== false
            && [$row['selector_hash'], $row['target_hash']] !== [$input['selectorHash'], $input['targetHash']]
        ) {
            throw new \InvalidArgumentException('Replay checkpoint is bound to a different selector or target set.');
        }
        $auditId = bin2hex(random_bytes(16));
        if ($row === false) {
            $input['checkpointWriter']->insert(
                $input['table'],
                $input['request'],
                $input['selectorHash'],
                $input['targetHash'],
                $input['now'],
            );
        }
        if ($row !== false && $row['state'] !== 'running') {
            $input['checkpointWriter']->resume($input['table'], $input['request']->checkpoint, $input['now']);
        }
        $input['auditWriter']->insert(
            $input['request'],
            $input['selectorHash'],
            $input['targetHash'],
            $auditId,
            $input['now'],
        );
        $input['hook']?->started($input['connection'], $input['request']->checkpoint, $auditId);
        $input['connection']->commit();

        return new PostgreSqlObserverReplayBinding(
            is_string($row['cursor_record_id'] ?? null) ? $row['cursor_record_id'] : null,
            $auditId,
        );
    }
}
