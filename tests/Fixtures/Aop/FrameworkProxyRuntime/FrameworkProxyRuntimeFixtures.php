<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyRuntime;

use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;

class FrameworkRuntimeService
{
    /** @var list<string> */
    public array $events = [];

    #[Transactional(connection: 'app')]
    public function run(string $value): string
    {
        $this->events[] = 'run:' . $value;

        return $value;
    }

    #[AfterCommit]
    public function notify(string $value): void
    {
        $this->events[] = 'notify:' . $value;
    }
}

class FrameworkRuntimeOperation implements Operation
{
    public int $calls = 0;

    #[Transactional(connection: 'app')]
    public function handle(): void
    {
        $this->calls++;
    }

    #[AfterCommit]
    public function callback(): void
    {
        $this->calls++;
    }
}
