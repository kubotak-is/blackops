<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application\Fixture\CommandDiscovery;

final readonly class ConfiguredCommandGreeting implements CommandGreeting
{
    public function message(): string
    {
        return 'container dependency ready';
    }
}
