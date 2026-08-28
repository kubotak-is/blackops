<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry;

final readonly class FrameworkCommandNames
{
    /** @return list<string> */
    public static function all(): array
    {
        return CommandProjectionTargetRegistry::frameworkNames();
    }
}
