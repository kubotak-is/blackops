<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

final class CommandProjectionMarkdownCellEncoder
{
    public static function encode(string $value): string
    {
        $encoded = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            if ($byte === "\r") {
                if (($index + 1) < $length && $value[$index + 1] === "\n") {
                    $index++;
                }
                $encoded .= '\\n';
                continue;
            }

            if ($byte === "\n") {
                $encoded .= '\\n';
                continue;
            }

            if ($byte === '\\') {
                $encoded .= '\\\\';
                continue;
            }

            if ($byte === '|') {
                $encoded .= '\\|';
                continue;
            }

            $encoded .= $byte;
        }

        return $encoded;
    }
}
