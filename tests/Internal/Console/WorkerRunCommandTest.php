<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\WorkerRunCommand;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\Execution\WorkerLoop;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class WorkerRunCommandTest extends TestCase
{
    public function testRunsWorkerWithFiniteIterationsAndIdleSleep(): void
    {
        $worker = new RecordingWorkerLoop(3);
        $tester = new CommandTester(new WorkerRunCommand($worker));

        $status = $tester->execute([
            '--iterations' => '5',
            '--idle-sleep-milliseconds' => '25',
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(5, $worker->maximumIterations);
        self::assertSame(25, $worker->idleSleepMilliseconds);
        self::assertStringContainsString('Processed claims: 3', $tester->getDisplay());
    }

    public function testClaimInterruptionReturnsFailure(): void
    {
        $tester = new CommandTester(new WorkerRunCommand(
            new RecordingWorkerLoop(exception: new WorkerClaimLostException('claim lost')),
        ));

        $status = $tester->execute(['--iterations' => '1']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('claim lost', $tester->getDisplay());
    }

    public function testRejectsInvalidWorkerOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new WorkerRunCommand(new RecordingWorkerLoop()))->execute([
            '--idle-sleep-milliseconds' => '0',
        ]);
    }
}

final class RecordingWorkerLoop implements WorkerLoop
{
    public ?int $maximumIterations = null;

    public int $idleSleepMilliseconds = 0;

    public function __construct(
        private int $processed = 0,
        private ?WorkerClaimLostException $exception = null,
    ) {}

    public function run(?int $maximumIterations = null, int $idleSleepMilliseconds = 1_000): int
    {
        $this->maximumIterations = $maximumIterations;
        $this->idleSleepMilliseconds = $idleSleepMilliseconds;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->processed;
    }
}
