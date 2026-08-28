<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Queue\QueueStatusSnapshot;
use BlackOps\Core\Queue\QueueStatusSource;
use BlackOps\Core\Queue\QueueStatusSourceException;
use BlackOps\Internal\Console\QueueStatusCommand;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

final class QueueStatusCommandTest extends TestCase
{
    public function testSuccessfulHumanAndJsonInvocationsUseOneClockAndOneSourceRead(): void
    {
        $clock = new QueueStatusClockFixture();
        $source = new QueueStatusSourceFixture();
        $tester = new CommandTester(new QueueStatusCommand(static fn(): QueueStatusSource => $source, $clock));

        self::assertSame(0, $tester->execute([], ['capture_stderr_separately' => true, 'decorated' => false]));
        self::assertSame(
            "schemaVersion=1\nscope=installation\nobservedAt=2026-08-27T15:00:00.000000Z\n"
            . "counts.acceptedDelayed=0\ncounts.acceptedReady=1\ncounts.deadLettered=0\n"
            . "counts.retryDelayed=0\ncounts.retryReady=0\ncounts.runningActive=0\n"
            . "counts.runningExpired=0\ncounts.supervising=0\nagesSeconds.oldestDeadLettered=null\n"
            . "agesSeconds.oldestReady=0\nagesSeconds.oldestRunningExpired=null\n"
            . "agesSeconds.oldestSupervising=null\n",
            $tester->getDisplay(),
        );
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame(1, $clock->calls);
        self::assertSame(1, $source->calls);
        self::assertSame('2026-08-27T15:00:00.000000+00:00', $source->observedAt?->format('Y-m-d\TH:i:s.uP'));

        $json = new CommandTester(
            new QueueStatusCommand(
                static fn(): QueueStatusSource => new QueueStatusSourceFixture(),
                new QueueStatusClockFixture(),
            ),
        );
        self::assertSame(0, $json->execute(['--json' => true], ['capture_stderr_separately' => true]));
        self::assertStringStartsWith('{"agesSeconds":', $json->getDisplay());
        self::assertSame(1, substr_count($json->getDisplay(), "\n"));
        self::assertSame('', $json->getErrorOutput());
    }

    public function testInvalidInputFailsBeforeClockOrSourceFactory(): void
    {
        foreach ([
            ['--unknown' => true],
            ['unexpected' => 'value'],
            ['--json' => 'not-a-boolean'],
        ] as $input) {
            $clock = new QueueStatusClockFixture();
            $factoryCalls = 0;
            $tester = new CommandTester(new QueueStatusCommand(static function () use (
                &$factoryCalls,
            ): QueueStatusSource {
                ++$factoryCalls;
                throw new RuntimeException('factory-secret');
            }, $clock));

            self::assertSame(2, $tester->execute($input, ['capture_stderr_separately' => true]), json_encode($input));
            self::assertSame('', $tester->getDisplay(), json_encode($input));
            self::assertSame("queue.input_invalid\n", $tester->getErrorOutput(), json_encode($input));
            self::assertSame(0, $clock->calls, json_encode($input));
            self::assertSame(0, $factoryCalls, json_encode($input));
        }
    }

    public function testTypedFactoryFailuresUseSafeClassificationAndExitCodes(): void
    {
        $cases = [
            QueueStatusSourceException::configurationInvalid(new RuntimeException('dsn=password-secret')),
            QueueStatusSourceException::storageUnavailable(new RuntimeException('sql=secret')),
            QueueStatusSourceException::integrityFailed(new RuntimeException('row=tenant-secret')),
        ];

        foreach ($cases as $failure) {
            $tester = new CommandTester(new QueueStatusCommand(static function () use ($failure): QueueStatusSource {
                throw $failure;
            }, new QueueStatusClockFixture()));

            $exit = $tester->execute([], ['capture_stderr_separately' => true]);
            $expectedCode = $failure->failure->value;
            self::assertSame($expectedCode === 'configuration_invalid' ? 2 : 1, $exit);
            self::assertSame('', $tester->getDisplay());
            self::assertSame("queue.{$expectedCode}\n", $tester->getErrorOutput());
            self::assertStringNotContainsString('password-secret', $tester->getErrorOutput());
            self::assertStringNotContainsString('tenant-secret', $tester->getErrorOutput());
        }
    }

    public function testReadFailuresAndInvalidFactoryValuesDoNotLeakDetails(): void
    {
        $sourceFailure = new RuntimeException('dsn=password-secret sql=private-row');
        $source = new QueueStatusSourceFixture(failure: $sourceFailure);
        $tester = new CommandTester(
            new QueueStatusCommand(static fn(): QueueStatusSource => $source, new QueueStatusClockFixture()),
        );
        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame("queue.storage_unavailable\n", $tester->getErrorOutput());
        self::assertStringNotContainsString('password-secret', $tester->getErrorOutput());

        $invalid = new CommandTester(
            new QueueStatusCommand(static fn(): mixed => new \stdClass(), new QueueStatusClockFixture()),
        );
        self::assertSame(2, $invalid->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $invalid->getDisplay());
        self::assertSame("queue.configuration_invalid\n", $invalid->getErrorOutput());
    }

    public function testHumanWireIsIdenticalForDecoratedAndUndecoratedOutput(): void
    {
        $plain = new CommandTester(
            new QueueStatusCommand(
                static fn(): QueueStatusSource => new QueueStatusSourceFixture(),
                new QueueStatusClockFixture(),
            ),
        );
        $decorated = new CommandTester(
            new QueueStatusCommand(
                static fn(): QueueStatusSource => new QueueStatusSourceFixture(),
                new QueueStatusClockFixture(),
            ),
        );

        self::assertSame(0, $plain->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        self::assertSame(0, $decorated->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => true,
        ]));

        self::assertSame($plain->getDisplay(), $decorated->getDisplay());
        self::assertSame('', $plain->getErrorOutput());
        self::assertStringNotContainsString("\x1B", $plain->getDisplay());
    }
}

final class QueueStatusSourceFixture implements QueueStatusSource
{
    public int $calls = 0;
    public ?DateTimeImmutable $observedAt = null;

    public function __construct(
        private ?Throwable $failure = null,
    ) {}

    public function read(DateTimeImmutable $observedAt): QueueStatusSnapshot
    {
        ++$this->calls;
        $this->observedAt = $observedAt;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new QueueStatusSnapshot(
            observedAt: $observedAt,
            acceptedReady: 1,
            acceptedDelayed: 0,
            retryReady: 0,
            retryDelayed: 0,
            runningActive: 0,
            runningExpired: 0,
            supervising: 0,
            deadLettered: 0,
            oldestReady: 0,
            oldestRunningExpired: null,
            oldestSupervising: null,
            oldestDeadLettered: null,
        );
    }
}

final class QueueStatusClockFixture implements ClockInterface
{
    public int $calls = 0;

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-08-27T15:00:00.000000Z');
    }
}
