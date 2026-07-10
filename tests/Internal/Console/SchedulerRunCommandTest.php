<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\SchedulerRunCommand;
use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\MaintenanceTask;
use BlackOps\Internal\Scheduler\MaintenanceTaskResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class SchedulerRunCommandTest extends TestCase
{
    public function testRunsSchedulerOnceAndPrintsResult(): void
    {
        $task = new SchedulerRunCommandTask();
        $tester = new CommandTester(
            new SchedulerRunCommand(
                new MaintenanceScheduler([$task]),
                new FixedSchedulerCommandClock('2026-07-11T00:00:00Z'),
            ),
        );

        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertSame(1, $task->runs);
        self::assertSame('2026-07-11T00:00:00+00:00', $task->now?->format(DATE_ATOM));
        self::assertStringContainsString('Scheduler run completed', $tester->getDisplay());
        self::assertStringContainsString('tasks: 1', $tester->getDisplay());
        self::assertStringContainsString('total_affected: 4', $tester->getDisplay());
        self::assertStringContainsString('sample affected=4 summary=done', $tester->getDisplay());
    }
}

final class SchedulerRunCommandTask implements MaintenanceTask
{
    public int $runs = 0;
    public ?DateTimeImmutable $now = null;

    public function name(): string
    {
        return 'sample';
    }

    public function run(DateTimeImmutable $now): MaintenanceTaskResult
    {
        ++$this->runs;
        $this->now = $now;

        return new MaintenanceTaskResult($this->name(), 4, 'done');
    }
}

final readonly class FixedSchedulerCommandClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}
