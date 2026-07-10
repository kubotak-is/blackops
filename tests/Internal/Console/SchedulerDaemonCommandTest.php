<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\SchedulerDaemonCommand;
use BlackOps\Internal\Scheduler\MaintenanceScheduler;
use BlackOps\Internal\Scheduler\MaintenanceTask;
use BlackOps\Internal\Scheduler\MaintenanceTaskResult;
use BlackOps\Internal\Scheduler\Sleeper;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class SchedulerDaemonCommandTest extends TestCase
{
    public function testRunsConfiguredIterationsAndSleepsBetweenThem(): void
    {
        $task = new SchedulerDaemonCommandTask();
        $sleeper = new RecordingSchedulerSleeper();
        $tester = new CommandTester(
            new SchedulerDaemonCommand(
                new MaintenanceScheduler([$task]),
                new FixedSchedulerDaemonCommandClock('2026-07-11T00:00:00Z'),
                $sleeper,
            ),
        );

        $status = $tester->execute(['--iterations' => '2', '--interval' => '5']);

        self::assertSame(0, $status);
        self::assertSame(2, $task->runs);
        self::assertSame([5], $sleeper->seconds);
        self::assertStringContainsString('Scheduler daemon iteration 1 completed', $tester->getDisplay());
        self::assertStringContainsString('Scheduler daemon iteration 2 completed', $tester->getDisplay());
    }

    public function testRejectsInvalidInterval(): void
    {
        $tester = new CommandTester(
            new SchedulerDaemonCommand(
                new MaintenanceScheduler([new SchedulerDaemonCommandTask()]),
                new FixedSchedulerDaemonCommandClock('2026-07-11T00:00:00Z'),
                new RecordingSchedulerSleeper(),
            ),
        );

        $this->expectException(InvalidArgumentException::class);

        $tester->execute(['--iterations' => '1', '--interval' => '0']);
    }
}

final class SchedulerDaemonCommandTask implements MaintenanceTask
{
    public int $runs = 0;

    public function name(): string
    {
        return 'sample';
    }

    public function run(DateTimeImmutable $now): MaintenanceTaskResult
    {
        ++$this->runs;

        return new MaintenanceTaskResult($this->name(), 1, $now->format(DATE_ATOM));
    }
}

final readonly class FixedSchedulerDaemonCommandClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

final class RecordingSchedulerSleeper implements Sleeper
{
    /**
     * @var list<int>
     */
    public array $seconds = [];

    public function sleep(int $seconds): void
    {
        $this->seconds[] = $seconds;
    }
}
