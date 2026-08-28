<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

enum AuditSourceRuntime: string
{
    case Http = 'http';
    case Cli = 'cli';
    case Worker = 'worker';
    case Scheduler = 'scheduler';
    case Relay = 'relay';
    case Replay = 'replay';
}
