<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use Closure;

final class ApplicationProcessCache
{
    /** @var array<string, object> */
    private array $processes = [];

    /** @param Closure(): object $factory */
    public function remember(string $name, Closure $factory): object
    {
        return $this->processes[$name] ??= $factory();
    }
}
