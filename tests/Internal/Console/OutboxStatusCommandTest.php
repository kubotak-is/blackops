<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Exception\ConfigurationFailure;
use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use BlackOps\Core\Outbox\OutboxStatusSource;
use BlackOps\Core\Outbox\OutboxStatusSourceException;
use BlackOps\Internal\Console\OutboxStatusCommand;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

final class OutboxStatusCommandTest extends TestCase
{
    public function testSuccessfulHumanInvocationUsesOneClockAndOneSourceRead(): void
    {
        $clock = new OutboxStatusClockFixture();
        $source = new OutboxStatusSourceFixture();
        $factoryCalls = 0;
        $tester = new CommandTester(new OutboxStatusCommand(static function () use (
            &$factoryCalls,
            $source,
        ): OutboxStatusSource {
            ++$factoryCalls;

            return $source;
        }, $clock));

        self::assertSame(0, $tester->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        self::assertSame(
            "schemaVersion=1\n"
            . "scope=installation\n"
            . "observedAt=2026-08-27T15:00:00.000000Z\n"
            . "counts.deadLettered=0\n"
            . "counts.leasedActive=0\n"
            . "counts.leasedExpired=0\n"
            . "counts.pendingDelayed=0\n"
            . "counts.pendingReady=1\n"
            . "counts.retryDelayed=0\n"
            . "counts.retryReady=0\n"
            . "agesSeconds.oldestDeadLettered=null\n"
            . "agesSeconds.oldestLeasedExpired=null\n"
            . "agesSeconds.oldestReady=0\n",
            $tester->getDisplay(),
        );
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame(1, $clock->calls);
        self::assertSame(1, $factoryCalls);
        self::assertSame(1, $source->calls);
        self::assertSame('2026-08-27T15:00:00.000000+00:00', $source->observedAt?->format('Y-m-d\TH:i:s.uP'));
    }

    public function testHumanAndJsonDecoratedOutputHaveExactUndecoratedParity(): void
    {
        $plainHuman = new CommandTester($this->command());
        $decoratedHuman = new CommandTester($this->command());
        self::assertSame(0, $plainHuman->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        self::assertSame(0, $decoratedHuman->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => true,
        ]));
        self::assertSame($plainHuman->getDisplay(), $decoratedHuman->getDisplay());
        self::assertSame('', $plainHuman->getErrorOutput());
        self::assertSame('', $decoratedHuman->getErrorOutput());

        $plainJson = new CommandTester($this->command());
        $decoratedJson = new CommandTester($this->command());
        self::assertSame(0, $plainJson->execute(['--json' => true], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        self::assertSame(0, $decoratedJson->execute(['--json' => true], [
            'capture_stderr_separately' => true,
            'decorated' => true,
        ]));
        self::assertSame($plainJson->getDisplay(), $decoratedJson->getDisplay());
        self::assertSame('', $plainJson->getErrorOutput());
        self::assertSame('', $decoratedJson->getErrorOutput());
        self::assertSame(1, substr_count($plainJson->getDisplay(), "\n"));
        self::assertStringNotContainsString("\x1B", $plainJson->getDisplay());
    }

    public function testInvalidInputFailsBeforeClockOrSourceFactory(): void
    {
        foreach ([
            ['--unknown' => true],
            ['unexpected' => 'value'],
            ['--json' => 'not-a-boolean'],
        ] as $input) {
            $clock = new OutboxStatusClockFixture();
            $factoryCalls = 0;
            $tester = new CommandTester(new OutboxStatusCommand(static function () use (
                &$factoryCalls,
            ): OutboxStatusSource {
                ++$factoryCalls;
                throw new RuntimeException('factory-secret');
            }, $clock));

            self::assertSame(2, $tester->execute($input, ['capture_stderr_separately' => true]), json_encode($input));
            self::assertSame('', $tester->getDisplay(), json_encode($input));
            self::assertSame("outbox.input_invalid\n", $tester->getErrorOutput(), json_encode($input));
            self::assertSame(0, $clock->calls, json_encode($input));
            self::assertSame(0, $factoryCalls, json_encode($input));
        }
    }

    public function testTypedFactoryAndSourceFailuresUseSafeClassificationAndExitCodes(): void
    {
        $factoryFailures = [
            OutboxStatusSourceException::configurationInvalid(new RuntimeException('dsn=password-secret')),
            OutboxStatusSourceException::storageUnavailable(new RuntimeException('sql=private-secret')),
            OutboxStatusSourceException::integrityFailed(new RuntimeException('row=tenant-secret')),
        ];
        foreach ($factoryFailures as $failure) {
            $tester = new CommandTester(new OutboxStatusCommand(static function () use ($failure): OutboxStatusSource {
                throw $failure;
            }, new OutboxStatusClockFixture()));

            $exit = $tester->execute([], ['capture_stderr_separately' => true]);
            $expected = $failure->failure->value;
            self::assertSame($expected === 'configuration_invalid' ? 2 : 1, $exit);
            self::assertSame('', $tester->getDisplay());
            self::assertSame("outbox.{$expected}\n", $tester->getErrorOutput());
            self::assertStringNotContainsString('password-secret', $tester->getErrorOutput());
            self::assertStringNotContainsString('private-secret', $tester->getErrorOutput());
            self::assertStringNotContainsString('tenant-secret', $tester->getErrorOutput());
        }

        $factoryKinds = [
            'invalid argument' => [new \InvalidArgumentException('config-secret'), 2],
            'logic' => [new \LogicException('logic-secret'), 2],
            'configuration' => [new OutboxConfigurationFailure('configuration-secret'), 2],
            'storage' => [new RuntimeException('connection-secret'), 1],
        ];
        foreach ($factoryKinds as $label => [$failure, $exit]) {
            $tester = new CommandTester(new OutboxStatusCommand(static function () use ($failure): OutboxStatusSource {
                throw $failure;
            }, new OutboxStatusClockFixture()));

            self::assertSame($exit, $tester->execute([], ['capture_stderr_separately' => true]), $label);
            self::assertSame('', $tester->getDisplay(), $label);
            self::assertSame(
                $exit === 2 ? "outbox.configuration_invalid\n" : "outbox.storage_unavailable\n",
                $tester->getErrorOutput(),
                $label,
            );
        }

        foreach ([
            OutboxStatusSourceException::configurationInvalid(new RuntimeException('configuration-secret')),
            OutboxStatusSourceException::storageUnavailable(new RuntimeException('storage-secret')),
            OutboxStatusSourceException::integrityFailed(new RuntimeException('integrity-secret')),
            new RuntimeException('read-secret'),
        ] as $failure) {
            $source = new OutboxStatusSourceFixture(failure: $failure);
            $tester = new CommandTester(
                new OutboxStatusCommand(static fn(): OutboxStatusSource => $source, new OutboxStatusClockFixture()),
            );

            $exit = $tester->execute([], ['capture_stderr_separately' => true]);
            $expected = $failure instanceof OutboxStatusSourceException
                ? $failure->failure->value
                : 'storage_unavailable';
            self::assertSame($expected === 'configuration_invalid' ? 2 : 1, $exit);
            self::assertSame('', $tester->getDisplay());
            self::assertSame("outbox.{$expected}\n", $tester->getErrorOutput());
            self::assertStringNotContainsString('secret', $tester->getDisplay());
            self::assertStringNotContainsString('secret', $tester->getErrorOutput());
        }
    }

    public function testWrongFactoryTypeAndClockFailureRemainSafe(): void
    {
        $invalid = new CommandTester(
            new OutboxStatusCommand(static fn(): mixed => new \stdClass(), new OutboxStatusClockFixture()),
        );
        self::assertSame(2, $invalid->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $invalid->getDisplay());
        self::assertSame("outbox.configuration_invalid\n", $invalid->getErrorOutput());

        $clock = new OutboxStatusClockFixture(new RuntimeException('clock-secret'));
        $factoryCalls = 0;
        $tester = new CommandTester(new OutboxStatusCommand(static function () use (
            &$factoryCalls,
        ): OutboxStatusSource {
            ++$factoryCalls;
            throw new RuntimeException('factory-secret');
        }, $clock));
        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame("outbox.storage_unavailable\n", $tester->getErrorOutput());
        self::assertStringNotContainsString('secret', $tester->getErrorOutput());
        self::assertSame(1, $clock->calls);
        self::assertSame(0, $factoryCalls);
    }

    public function testApplicationListAndHelpPerformNoDependencyReads(): void
    {
        $clock = new OutboxStatusClockFixture();
        $source = new OutboxStatusSourceFixture();
        $factoryCalls = 0;
        $application = new Application('BlackOps');
        $application->setAutoExit(false);
        $application->addCommand(new OutboxStatusCommand(static function () use (
            &$factoryCalls,
            $source,
        ): OutboxStatusSource {
            ++$factoryCalls;

            return $source;
        }, $clock));
        $tester = new ApplicationTester($application);

        self::assertSame(0, $factoryCalls);
        self::assertSame(0, $tester->run([
            'command' => 'list',
        ], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('outbox:status', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $source->calls);
        self::assertSame(0, $factoryCalls);

        self::assertSame(0, $tester->run([
            'command' => 'help',
            'command_name' => OutboxStatusCommand::NAME,
        ], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('Show aggregate transactional outbox status.', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $source->calls);
        self::assertSame(0, $factoryCalls);
    }

    private function command(): OutboxStatusCommand
    {
        return new OutboxStatusCommand(
            static fn(): OutboxStatusSource => new OutboxStatusSourceFixture(),
            new OutboxStatusClockFixture(),
        );
    }
}

final class OutboxStatusSourceFixture implements OutboxStatusSource
{
    public int $calls = 0;
    public ?DateTimeImmutable $observedAt = null;

    public function __construct(
        private ?Throwable $failure = null,
    ) {}

    public function read(DateTimeImmutable $observedAt): OutboxStatusSnapshot
    {
        ++$this->calls;
        $this->observedAt = $observedAt;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new OutboxStatusSnapshot(
            observedAt: $observedAt,
            pendingReady: 1,
            pendingDelayed: 0,
            retryReady: 0,
            retryDelayed: 0,
            leasedActive: 0,
            leasedExpired: 0,
            deadLettered: 0,
            oldestReady: 0,
            oldestLeasedExpired: null,
            oldestDeadLettered: null,
        );
    }
}

final class OutboxStatusClockFixture implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private ?Throwable $failure = null,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new DateTimeImmutable('2026-08-27T15:00:00.000000Z');
    }
}

final class OutboxConfigurationFailure extends RuntimeException implements ConfigurationFailure {}
