<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use InvalidArgumentException;
use RuntimeException;

final class PostgreSqlObserverReplayIdentity
{
    public static function selectorKey(PostgreSqlObserverReplaySelector $selector): string
    {
        return match ($selector->kind) {
            PostgreSqlObserverReplaySelector::OPERATION => 'operation:' . self::operation($selector),
            PostgreSqlObserverReplaySelector::RECORD => 'record:' . self::record($selector),
            PostgreSqlObserverReplaySelector::TIME => 'time:' . self::time($selector),
            default => throw new InvalidArgumentException('Replay selector kind is invalid.'),
        };
    }

    /** @return array{string,string} */
    public static function cursorParts(string $cursor): array
    {
        $parts = explode('|', $cursor, limit: 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException('Replay cursor is invalid.');
        }
        return [$parts[0], $parts[1]];
    }

    public static function assertCheckpoint(string $checkpoint): void
    {
        if (strlen($checkpoint) > 128 || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $checkpoint) !== 1) {
            throw new InvalidArgumentException('Replay checkpoint must be a lowercase stable identifier.');
        }
    }

    /** @return list<string> */
    public static function decodeTargets(mixed $value): array
    {
        if (is_string($value)) {
            return self::mapTargets(json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR));
        }
        return self::mapTargets($value);
    }

    /** @return list<string> */
    private static function mapTargets(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('Replay checkpoint target names are invalid.');
        }
        return array_values(array_map(self::targetName(...), $value));
    }

    public static function recordIdFromCursor(?string $cursor): ?string
    {
        if ($cursor === null) {
            return null;
        }
        if (!str_contains($cursor, '|')) {
            return $cursor;
        }
        [, $recordId] = self::cursorParts($cursor);
        return $recordId;
    }

    private static function operation(PostgreSqlObserverReplaySelector $selector): string
    {
        if ($selector->operationId === null) {
            throw new InvalidArgumentException('Operation selector is incomplete.');
        }
        return $selector->operationId->toString();
    }

    private static function record(PostgreSqlObserverReplaySelector $selector): string
    {
        if ($selector->recordId === null) {
            throw new InvalidArgumentException('Record selector is incomplete.');
        }
        return $selector->recordId->toString();
    }

    private static function time(PostgreSqlObserverReplaySelector $selector): string
    {
        if ($selector->from === null || $selector->to === null) {
            throw new InvalidArgumentException('Time selector is incomplete.');
        }
        return $selector->from->format('Y-m-d\\TH:i:s.uP') . ':' . $selector->to->format('Y-m-d\\TH:i:s.uP');
    }

    private static function targetName(mixed $target): string
    {
        if (!is_string($target) || trim($target) === '') {
            throw new RuntimeException('Replay checkpoint target names are invalid.');
        }
        return $target;
    }
}
