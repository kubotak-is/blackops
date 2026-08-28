<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\DiagnosticsCheckCommand;
use BlackOps\Internal\Diagnostics\SystemDiagnosticsCheck;
use BlackOps\Internal\Diagnostics\SystemDiagnosticsNormalizer;
use BlackOps\Internal\Diagnostics\SystemDiagnosticsRenderer;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReadResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class DiagnosticsCheckCommandTest extends TestCase
{
    public function testSuccessfulHumanAndJsonInvocationsUseOneClockAndOneDependencyCall(): void
    {
        $source = new DiagnosticsStatusSourceFixture();
        $query = new DiagnosticsHealthQueryFixture();
        $clock = new DiagnosticsClockFixture();
        $command = new DiagnosticsCheckCommand(new SystemDiagnosticsCheck($source, $query, $clock));

        $human = new CommandTester($command);
        self::assertSame(0, $human->execute([], ['capture_stderr_separately' => true, 'decorated' => false]));
        self::assertSame(
            "schemaVersion=1\nstatus=pass\ncheckedAt=2026-08-27T15:00:00.000000Z\nprojectionBuildSet=available\nreadiness.compiled_artifact=pass\nreadiness.runtime_configuration=pass\nreadiness.database=pass\nreadiness.migration_compatibility=pass\nreadiness.storage_key_provider=pass\nreadiness.runtime_services=pass\n",
            $human->getDisplay(),
        );
        self::assertSame('', $human->getErrorOutput());
        self::assertSame(1, $source->calls);
        self::assertSame(1, $query->calls);
        self::assertSame(1, $clock->calls);

        $json = new CommandTester(
            new DiagnosticsCheckCommand(
                new SystemDiagnosticsCheck(
                    new DiagnosticsStatusSourceFixture(),
                    new DiagnosticsHealthQueryFixture(),
                    new DiagnosticsClockFixture(),
                ),
                new SystemDiagnosticsRenderer(),
            ),
        );
        self::assertSame(0, $json->execute(['--json' => true], ['capture_stderr_separately' => true]));
        self::assertStringStartsWith('{"checkedAt":"2026-08-27T15:00:00.000000Z"', $json->getDisplay());
        self::assertSame('', $json->getErrorOutput());
        self::assertSame(1, substr_count($json->getDisplay(), "\n"));
    }

    public function testProjectionCallbackFinishesBeforeReadinessQuery(): void
    {
        $events = [];
        $source = new DiagnosticsStatusSourceFixture(static function () use (&$events): void {
            $events[] = 'projection-end';
        });
        $query = new DiagnosticsHealthQueryFixture(static function () use (&$events): void {
            $events[] = 'readiness';
        });
        $command = new DiagnosticsCheckCommand(
            new SystemDiagnosticsCheck($source, $query, new DiagnosticsClockFixture()),
        );

        self::assertSame(0, new CommandTester($command)->execute([], ['capture_stderr_separately' => true]));
        self::assertSame(['projection-end', 'readiness'], $events);
    }

    public function testProjectionCallbackReturnsOnlyItsStatusBeforeReadinessRuns(): void
    {
        $source = new DiagnosticsStatusSourceFixture();
        $query = new DiagnosticsHealthQueryFixture();
        $report = new SystemDiagnosticsCheck($source, $query, new DiagnosticsClockFixture())->check();

        self::assertSame(ProjectionBuildSetRecoveryStatus::AVAILABLE, $source->callbackResult);
        self::assertSame('available', $report->projectionBuildSet);
        self::assertSame(1, $query->calls);
    }

    public function testMissingQueryIsSafeFailureWithoutCallingAQuery(): void
    {
        $source = new DiagnosticsStatusSourceFixture();
        $tester = new CommandTester(new DiagnosticsCheckCommand(
            new SystemDiagnosticsCheck($source, null, new DiagnosticsClockFixture()),
        ));

        self::assertSame(1, $tester->execute(['--json' => true], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('"code":"query_unavailable"', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testUnknownOptionPositionalAndNonBooleanInputFailBeforeDependencies(): void
    {
        foreach ([
            ['--unknown' => true],
            ['unexpected' => 'value'],
            ['--json' => 'not-a-boolean'],
        ] as $input) {
            $source = new DiagnosticsStatusSourceFixture();
            $query = new DiagnosticsHealthQueryFixture();
            $tester = new CommandTester(new DiagnosticsCheckCommand(
                new SystemDiagnosticsCheck($source, $query, new DiagnosticsClockFixture()),
            ));

            self::assertSame(2, $tester->execute($input, ['capture_stderr_separately' => true]), json_encode($input));
            self::assertSame('', $tester->getDisplay(), json_encode($input));
            self::assertSame("diagnostics.input_invalid\n", $tester->getErrorOutput(), json_encode($input));
            self::assertSame(0, $source->calls, json_encode($input));
            self::assertSame(0, $query->calls, json_encode($input));
        }
    }

    public function testQueryThrowIsContractedWithoutLeakingItsMessage(): void
    {
        $query = new DiagnosticsHealthQueryFixture(throw: true);
        $tester = new CommandTester(new DiagnosticsCheckCommand(
            new SystemDiagnosticsCheck(new DiagnosticsStatusSourceFixture(), $query, new DiagnosticsClockFixture()),
        ));

        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('readiness.query_failed=fail', $tester->getDisplay());
        self::assertStringNotContainsString('password-secret', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testTypedProjectionFailuresAreSafeAndDoNotLeakTheirMessages(): void
    {
        foreach ([
            new ProjectionBuildSetValidationException('configuration dsn=password-secret'),
            new ProjectionBuildSetFilesystemException('read path=/secret/password'),
        ] as $failure) {
            $tester = new CommandTester(new DiagnosticsCheckCommand(
                new SystemDiagnosticsCheck(
                    new DiagnosticsStatusSourceFixture(failure: $failure),
                    new DiagnosticsHealthQueryFixture(),
                    new DiagnosticsClockFixture(),
                ),
            ));

            self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
            self::assertStringContainsString(
                $failure instanceof ProjectionBuildSetValidationException
                    ? 'projectionBuildSet=configuration_invalid'
                    : 'projectionBuildSet=read_failed',
                $tester->getDisplay(),
            );
            self::assertStringNotContainsString('password-secret', $tester->getDisplay());
            self::assertStringNotContainsString('/secret/password', $tester->getDisplay());
            self::assertSame('', $tester->getErrorOutput());
        }
    }

    public function testEachReadinessCodeCanFailWithoutCollapsingTheValidSixRowReport(): void
    {
        foreach (SystemDiagnosticsNormalizer::READINESS_CODES as $failingCode) {
            $report = new SystemDiagnosticsCheck(
                new DiagnosticsStatusSourceFixture(),
                new DiagnosticsHealthQueryFixture(failingCode: $failingCode),
                new DiagnosticsClockFixture(),
            )->check();

            self::assertSame('fail', $report->status, $failingCode);
            self::assertCount(7, $report->checks, $failingCode);
            self::assertSame(
                SystemDiagnosticsNormalizer::READINESS_CODES,
                array_column(array_slice($report->checks, 1), 'code'),
                $failingCode,
            );
            self::assertSame(
                [$failingCode],
                array_column(
                    array_values(array_filter(
                        array_slice($report->checks, 1),
                        static fn(array $check): bool => $check['status'] === 'fail',
                    )),
                    'code',
                ),
                $failingCode,
            );
        }
    }

    public function testHumanOutputIsIdenticalForDecoratedAndUndecoratedConsole(): void
    {
        $plain = new CommandTester(new DiagnosticsCheckCommand(
            new SystemDiagnosticsCheck(
                new DiagnosticsStatusSourceFixture(),
                new DiagnosticsHealthQueryFixture(),
                new DiagnosticsClockFixture(),
            ),
        ));
        self::assertSame(0, $plain->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));

        $decorated = new CommandTester(new DiagnosticsCheckCommand(
            new SystemDiagnosticsCheck(
                new DiagnosticsStatusSourceFixture(),
                new DiagnosticsHealthQueryFixture(),
                new DiagnosticsClockFixture(),
            ),
        ));
        self::assertSame(0, $decorated->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => true,
        ]));

        self::assertSame($plain->getDisplay(), $decorated->getDisplay());
        self::assertSame($plain->getErrorOutput(), $decorated->getErrorOutput());
        self::assertStringNotContainsString("\x1B", $plain->getDisplay());
    }
}

final class DiagnosticsStatusSourceFixture implements ProjectionBuildSetStatusSource
{
    public int $calls = 0;
    public mixed $callbackResult = null;

    /** @param (callable(): void)|null $after */
    public function __construct(
        private $after = null,
        private ?\Throwable $failure = null,
    ) {}

    public function withStatus(callable $callback, string $selectedKey = 'command'): mixed
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->callbackResult = $callback(
            new ProjectionBuildSetRecoveryResult(
                ProjectionBuildSetRecoveryStatus::AVAILABLE,
                new ProjectionBuildSetReadResult('build', str_repeat('a', 64), []),
            ),
        );
        if ($this->after !== null) {
            ($this->after)();
        }

        return $this->callbackResult;
    }
}

final class DiagnosticsHealthQueryFixture implements OperationalHealthQuery
{
    public int $calls = 0;

    /** @param (callable(): void)|null $after */
    public function __construct(
        private $after = null,
        private bool $throw = false,
        private ?string $failingCode = null,
    ) {}

    public function check(OperationalHealthKind $kind): OperationalHealthReport
    {
        ++$this->calls;
        if ($this->after !== null) {
            ($this->after)();
        }
        if ($this->throw) {
            throw new \RuntimeException('password-secret dsn');
        }

        return new OperationalHealthReport(
            $kind,
            $this->failingCode === null ? OperationalHealthStatus::Pass : OperationalHealthStatus::Fail,
            new DateTimeImmutable('1999-01-01T00:00:00Z'),
            array_map(function (string $code): OperationalHealthCheck {
                return $code === $this->failingCode
                    ? OperationalHealthCheck::fail($code)
                    : OperationalHealthCheck::pass($code);
            }, [
                'runtime_services',
                'database',
                'compiled_artifact',
                'storage_key_provider',
                'migration_compatibility',
                'runtime_configuration',
            ]),
        );
    }
}

final class DiagnosticsClockFixture implements ClockInterface
{
    public int $calls = 0;

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return new DateTimeImmutable('2026-08-27T15:00:00+00:00');
    }
}
