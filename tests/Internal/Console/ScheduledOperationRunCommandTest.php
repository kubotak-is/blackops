<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Internal\Console\ScheduledOperationRunCommand;
use BlackOps\Internal\Scheduling\ScheduledOperationRunResult;
use BlackOps\Internal\Scheduling\ScheduledOperationRunService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ScheduledOperationRunCommandTest extends TestCase
{
    public function testUnknownOptionUsesSafeHumanConfigurationErrorAndExitTwo(): void
    {
        $tester =
            new CommandTester(new ScheduledOperationRunCommand(static fn(): ScheduledOperationRunService => new FakeScheduledRunService(
                new ScheduledOperationRunResult(0, 0, 0, 0, 0),
            )));

        self::assertSame(Command::INVALID, $tester->execute(['--unknown' => true]));
        self::assertSame("Scheduled operation run failed [configuration_error].\n", $tester->getDisplay());
    }

    public function testUnknownOptionWithJsonUsesSafeVersionedConfigurationErrorAndExitTwo(): void
    {
        $tester =
            new CommandTester(new ScheduledOperationRunCommand(static fn(): ScheduledOperationRunService => new FakeScheduledRunService(
                new ScheduledOperationRunResult(0, 0, 0, 0, 0),
            )));

        self::assertSame(Command::INVALID, $tester->execute(['--unknown' => true, '--json' => true]));
        self::assertSame(
            '{"schemaVersion":1,"status":"failed","code":"configuration_error"}' . "\n",
            $tester->getDisplay(),
        );
    }

    public function testNoScheduleWritesZeroHumanCountsAndSuccess(): void
    {
        $tester =
            new CommandTester(new ScheduledOperationRunCommand(static fn(): ScheduledOperationRunService => new FakeScheduledRunService(
                new ScheduledOperationRunResult(0, 0, 0, 0, 0),
            )));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(
            "Scheduled operation run completed.\nevaluated: 0\naccepted: 0\nskipped_misfire: 0\nskipped_overlap: 0\nfailed: 0\n",
            $tester->getDisplay(),
        );
    }

    public function testWritesHumanCountShapeAndSuccess(): void
    {
        $tester =
            new CommandTester(new ScheduledOperationRunCommand(static fn(): ScheduledOperationRunService => new FakeScheduledRunService(
                new ScheduledOperationRunResult(2, 1, 3, 4, 0),
            )));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(
            "Scheduled operation run completed.\nevaluated: 2\naccepted: 1\nskipped_misfire: 3\nskipped_overlap: 4\nfailed: 0\n",
            $tester->getDisplay(),
        );
    }

    public function testWritesVersionedJsonAndFailureExit(): void
    {
        $tester =
            new CommandTester(new ScheduledOperationRunCommand(static fn(): ScheduledOperationRunService => new FakeScheduledRunService(
                new ScheduledOperationRunResult(1, 0, 0, 0, 1),
            )));

        self::assertSame(Command::FAILURE, $tester->execute(['--json' => true]));
        self::assertSame(
            '{"schemaVersion":1,"status":"failed","evaluated":1,"accepted":0,"skipped_misfire":0,"skipped_overlap":0,"failed":1}'
            . "\n",
            $tester->getDisplay(),
        );
    }

    public function testConfigurationErrorUsesExitTwoAndDoesNotExposeMessage(): void
    {
        $secret = 'credential-value';
        $tester = new CommandTester(
            new ScheduledOperationRunCommand(
                static fn(): ScheduledOperationRunService => throw new \InvalidArgumentException($secret),
            ),
        );

        self::assertSame(Command::INVALID, $tester->execute(['--json' => true]));
        self::assertSame(
            '{"schemaVersion":1,"status":"failed","code":"configuration_error"}' . "\n",
            $tester->getDisplay(),
        );
        self::assertStringNotContainsString($secret, $tester->getDisplay());
    }

    public function testApplicationBootstrapFailureUsesCoreConfigurationCategory(): void
    {
        $tester = new CommandTester(
            new ScheduledOperationRunCommand(
                static fn(): ScheduledOperationRunService => throw new ApplicationBootstrapException(
                    'bootstrap-detail',
                ),
            ),
        );

        self::assertSame(Command::INVALID, $tester->execute(['--json' => true]));
        self::assertSame(
            '{"schemaVersion":1,"status":"failed","code":"configuration_error"}' . "\n",
            $tester->getDisplay(),
        );
        self::assertStringNotContainsString('bootstrap-detail', $tester->getDisplay());
    }

    public function testRuntimeErrorUsesExitOneAndDoesNotExposeMessage(): void
    {
        $secret = 'sql-password';
        $tester = new CommandTester(
            new ScheduledOperationRunCommand(
                static fn(): ScheduledOperationRunService => throw new RuntimeException($secret),
            ),
        );

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame("Scheduled operation run failed [runtime_error].\n", $tester->getDisplay());
        self::assertStringNotContainsString($secret, $tester->getDisplay());
    }
}

final readonly class FakeScheduledRunService implements ScheduledOperationRunService
{
    public function __construct(
        private ScheduledOperationRunResult $result,
    ) {}

    public function run(): ScheduledOperationRunResult
    {
        return $this->result;
    }
}
