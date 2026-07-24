<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use InvalidArgumentException;

final class PostgreSqlObserverReplaySelectionQuery
{
    /** @return array{sql: string, params: array<array-key, mixed>} */
    public static function build(
        PostgreSqlObserverReplaySelector $selector,
        int $limit,
        ?string $cursor,
        string $table,
    ): array {
        /** @var array<string, mixed> $params */
        $params = [];
        $where = [];
        $order = self::selectorWhere($selector, $where, $params, $cursor);
        $sql =
            "SELECT convert_from(encoded_record, 'UTF8') AS encoded_record, sequence, occurred_at, record_id
            FROM {$table} WHERE "
            . implode(' AND ', $where)
            . " ORDER BY {$order} LIMIT "
            . ($limit + 1);
        return ['sql' => $sql, 'params' => $params];
    }

    /** @param list<string> $where @param array<string, mixed> $params */
    private static function selectorWhere(
        PostgreSqlObserverReplaySelector $selector,
        array &$where,
        array &$params,
        ?string $cursor,
    ): string {
        if ($selector->kind === PostgreSqlObserverReplaySelector::OPERATION) {
            if ($selector->operationId === null) {
                throw new InvalidArgumentException('Operation selector is incomplete.');
            }
            $where[] = 'operation_id = :operation_id';
            $params['operation_id'] = $selector->operationId->toString();
            if ($cursor !== null) {
                $where[] = '(sequence, record_id) > (:cursor_sequence, :cursor_record_id)';
                [$params['cursor_sequence'], $params['cursor_record_id']] =
                    PostgreSqlObserverReplayIdentity::cursorParts($cursor);
            }
            return 'sequence ASC, record_id ASC';
        }
        if ($selector->kind === PostgreSqlObserverReplaySelector::RECORD) {
            if ($selector->recordId === null) {
                throw new InvalidArgumentException('Record selector is incomplete.');
            }
            $where[] = 'record_id = :record_id';
            $params['record_id'] = $selector->recordId->toString();
            if ($cursor !== null) {
                $where[] = 'record_id > :cursor_record_id';
                $params['cursor_record_id'] = $cursor;
            }
            return 'record_id ASC';
        }
        if ($selector->from === null || $selector->to === null) {
            throw new InvalidArgumentException('Time selector is incomplete.');
        }
        $where[] = 'occurred_at >= :from_at AND occurred_at < :to_at';
        $params['from_at'] = $selector->from->format('Y-m-d H:i:s.uP');
        $params['to_at'] = $selector->to->format('Y-m-d H:i:s.uP');
        if ($cursor !== null) {
            $where[] = '(occurred_at, record_id) > (:cursor_at, :cursor_record_id)';
            [$params['cursor_at'], $params['cursor_record_id']] =
                PostgreSqlObserverReplayIdentity::cursorParts($cursor);
        }
        return 'occurred_at ASC, record_id ASC';
    }
}
