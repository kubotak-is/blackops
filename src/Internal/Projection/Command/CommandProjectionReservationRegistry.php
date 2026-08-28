<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

final class CommandProjectionReservationRegistry
{
    /** @return list<string> */
    public static function names(): array
    {
        return [
            'list',
            'ls',
            'help',
            'completion',
            '_complete',
            'route:list',
            'route:ls',
            'schedule:list',
            'schedule:ls',
            'diagnostics:check',
            'queue:status',
            'about',
            'outbox:status',
        ];
    }
}
