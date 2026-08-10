<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;

#[Transactional(connection: 'app')]
class CompatibilityService
{
    public function __construct(
        public readonly CompatibilityDependency $dependency,
    ) {}

    /** @var list<string> */
    public array $events = [];

    #[Transactional]
    public function value(string $value): string
    {
        $this->events[] = 'value:' . $value;
        $this->record('queued:' . $value);
        $this->nested();
        if ($value === 'rollback') {
            throw new \RuntimeException('compatibility rollback');
        }

        return $value;
    }

    #[Transactional]
    public function nested(): void
    {
        $this->events[] = 'nested';
    }

    #[Transactional]
    public function typed(string $prefix, int ...$values): string
    {
        return $prefix . implode(',', $values);
    }

    #[Transactional]
    public function queue(bool $failure = false): void
    {
        $this->record('first');
        if ($failure) {
            $this->record('failure');
        }
        $this->record('last');
    }

    #[Transactional]
    public function queueAndRollback(): void
    {
        $this->record('discard-first');
        $this->record('discard-last');
        throw new \RuntimeException('compatibility queue rollback');
    }

    #[AfterCommit]
    public function record(string $value): void
    {
        if ($value === 'failure') {
            throw new \RuntimeException('compatibility callback failure');
        }
        $this->events[] = 'record:' . $value;
    }
}
