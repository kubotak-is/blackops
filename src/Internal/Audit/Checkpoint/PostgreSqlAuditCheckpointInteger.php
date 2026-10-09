<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

final class PostgreSqlAuditCheckpointInteger
{
    private function __construct() {}

    public static function read(#[\SensitiveParameter] mixed $value, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            if ($value < $minimum || $value > $maximum) {
                throw new \RuntimeException();
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw new \RuntimeException();
        }

        $maximumText = (string) $maximum;
        if (
            strlen($value) > strlen($maximumText)
            || strlen($value) === strlen($maximumText) && strcmp($value, $maximumText) > 0
        ) {
            throw new \RuntimeException();
        }

        $result = (int) $value;
        if ($result < $minimum) {
            throw new \RuntimeException();
        }

        return $result;
    }
}
