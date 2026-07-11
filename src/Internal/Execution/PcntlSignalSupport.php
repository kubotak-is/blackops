<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use RuntimeException;

final readonly class PcntlSignalSupport
{
    private function __construct() {}

    public static function available(): bool
    {
        return (
            extension_loaded('pcntl')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_signal_get_handler')
            && function_exists('pcntl_alarm')
        );
    }

    /** @return callable|int */
    public static function handler(int $signal): callable|int
    {
        return self::normalizeHandler(pcntl_signal_get_handler($signal));
    }

    /** @return callable|int */
    private static function normalizeHandler(mixed $handler): callable|int
    {
        if (!is_int($handler) && !is_callable($handler)) {
            throw new RuntimeException('Unable to preserve the current PCNTL signal handler.');
        }

        return $handler;
    }
}
