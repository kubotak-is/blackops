<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\About\AboutMetadata;
use BlackOps\Internal\About\AboutMetadataSource;
use BlackOps\Internal\Console\AboutCommand;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReadResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

final class AboutCommandTest extends TestCase
{
    public function testValidInvocationCallsEachDependencyOnceAndWritesRawHumanWire(): void
    {
        $metadata = new AboutMetadata('1.2.0@dev', '8.5.0', 'cli');
        $source = new AboutMetadataSourceFixture($metadata);
        $projection = new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::AVAILABLE);
        $tester = new CommandTester(new AboutCommand($source, $projection));

        self::assertSame(0, $tester->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        self::assertSame(
            "schemaVersion=1\n"
            . "package.name=blackops/framework\n"
            . "package.version=1.2.0@dev\n"
            . "runtime.scope=current_process\n"
            . "runtime.phpVersion=8.5.0\n"
            . "runtime.sapi=cli\n"
            . "projectionBuildSet=available\n",
            $tester->getDisplay(),
        );
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame(1, $source->calls);
        self::assertSame(1, $projection->calls);
        self::assertSame('command', $projection->selectedKey);
    }

    public function testHumanAndJsonDecoratedOutputHaveExactUndecoratedParity(): void
    {
        $plainHuman = new CommandTester(
            new AboutCommand(
                new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::ABSENT),
            ),
        );
        $decoratedHuman = new CommandTester(
            new AboutCommand(
                new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::ABSENT),
            ),
        );

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
        self::assertStringNotContainsString("\x1B", $plainHuman->getDisplay());

        $plainJson = new CommandTester(
            new AboutCommand(
                new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::ABSENT),
            ),
        );
        $decoratedJson = new CommandTester(
            new AboutCommand(
                new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::ABSENT),
            ),
        );
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
        self::assertSame(
            '{"package":{"name":"blackops/framework","version":"dev-main"},'
            . '"projectionBuildSet":"absent",'
            . '"runtime":{"phpVersion":"8.5.0","sapi":"cli","scope":"current_process"},'
            . '"schemaVersion":1}'
            . "\n",
            $plainJson->getDisplay(),
        );
        self::assertSame(1, substr_count($plainJson->getDisplay(), "\n"));
        self::assertStringNotContainsString("\x1B", $plainJson->getDisplay());
    }

    public function testEveryProjectionStatusIsContractedWithoutChangingSuccessfulExit(): void
    {
        foreach ([
            'available' => ProjectionBuildSetRecoveryStatus::AVAILABLE,
            'absent' => ProjectionBuildSetRecoveryStatus::ABSENT,
            'malformed' => ProjectionBuildSetRecoveryStatus::MALFORMED,
            'unsupported' => ProjectionBuildSetRecoveryStatus::UNSUPPORTED,
            'stale' => ProjectionBuildSetRecoveryStatus::STALE,
            'build_mismatch' => ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH,
        ] as $expected => $status) {
            $tester = new CommandTester(
                new AboutCommand(
                    new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                    new AboutProjectionStatusSourceFixture($status),
                ),
            );

            self::assertSame(0, $tester->execute([], ['capture_stderr_separately' => true]), $expected);
            self::assertStringContainsString("projectionBuildSet={$expected}\n", $tester->getDisplay(), $expected);
            self::assertSame('', $tester->getErrorOutput(), $expected);
        }
    }

    public function testKnownProjectionExceptionsBecomeInstalledStateStatuses(): void
    {
        foreach ([
            new ProjectionBuildSetValidationException('dsn=password-secret'),
            new ProjectionBuildSetFilesystemException('path=/private/build'),
        ] as $failure) {
            $tester = new CommandTester(
                new AboutCommand(
                    new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                    new AboutProjectionStatusSourceFixture(failure: $failure),
                ),
            );

            self::assertSame(0, $tester->execute([], ['capture_stderr_separately' => true]));
            self::assertStringContainsString(
                $failure instanceof ProjectionBuildSetValidationException
                    ? 'projectionBuildSet=configuration_invalid'
                    : 'projectionBuildSet=read_failed',
                $tester->getDisplay(),
            );
            self::assertStringNotContainsString('password-secret', $tester->getDisplay());
            self::assertStringNotContainsString('/private/build', $tester->getDisplay());
            self::assertSame('', $tester->getErrorOutput());
        }
    }

    public function testMetadataFailureHasPrecedenceAndDoesNotReadProjection(): void
    {
        $metadata = new AboutMetadataSourceFixture(
            result: new \stdClass(),
            useDefault: false,
            failure: new RuntimeException('reference=/private/secret'),
        );
        $projection = new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::AVAILABLE);
        $tester = new CommandTester(new AboutCommand($metadata, $projection));

        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame("about.metadata_unavailable\n", $tester->getErrorOutput());
        self::assertStringNotContainsString('/private/secret', $tester->getErrorOutput());
        self::assertSame(1, $metadata->calls);
        self::assertSame(0, $projection->calls);
    }

    public function testUnexpectedProjectionResultOrThrowableIsSafeAndHasNoPartialOutput(): void
    {
        foreach ([
            new AboutProjectionStatusSourceFixture(result: new \stdClass(), useCallback: false),
            new AboutProjectionStatusSourceFixture(
                result: new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::ABSENT),
                useCallback: false,
            ),
            new AboutProjectionStatusSourceFixture(
                result: ProjectionBuildSetRecoveryStatus::ABSENT,
                useCallback: false,
            ),
            new AboutProjectionStatusSourceFixture(failure: new RuntimeException('build-id=secret hash=private-hash')),
        ] as $projection) {
            $tester = new CommandTester(
                new AboutCommand(
                    new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                    $projection,
                ),
            );

            self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
            self::assertSame('', $tester->getDisplay());
            self::assertSame("about.metadata_unavailable\n", $tester->getErrorOutput());
            self::assertStringNotContainsString('private-hash', $tester->getErrorOutput());
        }
    }

    public function testUnknownPositionalAndNonBooleanInputFailBeforeBothDependencies(): void
    {
        foreach ([
            ['--unknown' => true],
            ['unexpected' => 'value'],
            ['--json' => 'not-a-boolean'],
        ] as $input) {
            $metadata = new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli'));
            $projection = new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::AVAILABLE);
            $tester = new CommandTester(new AboutCommand($metadata, $projection));

            self::assertSame(2, $tester->execute($input, ['capture_stderr_separately' => true]), json_encode($input));
            self::assertSame('', $tester->getDisplay(), json_encode($input));
            self::assertSame("about.input_invalid\n", $tester->getErrorOutput(), json_encode($input));
            self::assertSame(0, $metadata->calls, json_encode($input));
            self::assertSame(0, $projection->calls, json_encode($input));
        }
    }

    public function testConstructorAndHelpPerformNoDependencyReads(): void
    {
        $metadata = new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli'));
        $projection = new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::AVAILABLE);
        $command = new AboutCommand($metadata, $projection);

        self::assertSame('about', $command->getName());
        self::assertSame(
            'Show installed BlackOps package, runtime, and projection status.',
            $command->getDescription(),
        );
        $command->getDefinition();
        self::assertSame(0, $metadata->calls);
        self::assertSame(0, $projection->calls);

        self::assertSame('', $command->getHelp());
        self::assertSame(0, $metadata->calls);
        self::assertSame(0, $projection->calls);

        $application = new Application('BlackOps');
        $application->setAutoExit(false);
        $application->addCommand($command);
        $listing = new ApplicationTester($application);

        self::assertSame(0, $listing->run([
            'command' => 'list',
        ], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('about', $listing->getDisplay());
        self::assertSame('', $listing->getErrorOutput());
        self::assertSame(0, $metadata->calls);
        self::assertSame(0, $projection->calls);

        self::assertSame(0, $listing->run([
            'command' => 'help',
            'command_name' => AboutCommand::NAME,
        ], ['capture_stderr_separately' => true]));
        self::assertStringContainsString(
            'Show installed BlackOps package, runtime, and projection status.',
            $listing->getDisplay(),
        );
        self::assertSame('', $listing->getErrorOutput());
        self::assertSame(0, $metadata->calls);
        self::assertSame(0, $projection->calls);
    }

    public function testBroadExceptionCanaryNeverReachesStdoutOrStderr(): void
    {
        $canary =
            'dsn=password-secret path=/private/tenant payload=private-payload '
            . 'reference=private-reference dependency=private-dependency environment=private-environment '
            . 'extension=private-extension hostname=private-host runtime-profile=private-profile';
        $cases = [
            new AboutCommand(
                new AboutMetadataSourceFixture(failure: new RuntimeException($canary)),
                new AboutProjectionStatusSourceFixture(ProjectionBuildSetRecoveryStatus::AVAILABLE),
            ),
            new AboutCommand(
                new AboutMetadataSourceFixture(new AboutMetadata('dev-main', '8.5.0', 'cli')),
                new AboutProjectionStatusSourceFixture(failure: new RuntimeException($canary)),
            ),
        ];

        foreach ($cases as $command) {
            $tester = new CommandTester($command);
            self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
            self::assertSame('', $tester->getDisplay());
            self::assertSame("about.metadata_unavailable\n", $tester->getErrorOutput());
            self::assertStringNotContainsString($canary, $tester->getDisplay());
            self::assertStringNotContainsString($canary, $tester->getErrorOutput());
        }
    }
}

final class AboutMetadataSourceFixture implements AboutMetadataSource
{
    public int $calls = 0;

    public function __construct(
        private mixed $result = null,
        private bool $useDefault = true,
        private ?Throwable $failure = null,
    ) {}

    public function read(): mixed
    {
        ++$this->calls;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if (!$this->useDefault) {
            return $this->result;
        }

        return $this->result ?? new AboutMetadata('dev-main', '8.5.0', 'cli');
    }
}

final class AboutProjectionStatusSourceFixture implements ProjectionBuildSetStatusSource
{
    public int $calls = 0;
    public ?string $selectedKey = null;

    public function __construct(
        private mixed $result = ProjectionBuildSetRecoveryStatus::ABSENT,
        private bool $useCallback = true,
        private ?Throwable $failure = null,
    ) {}

    public function withStatus(callable $callback, string $selectedKey = 'command'): mixed
    {
        ++$this->calls;
        $this->selectedKey = $selectedKey;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if (!$this->useCallback) {
            return $this->result;
        }

        $status = $this->result instanceof ProjectionBuildSetRecoveryStatus
            ? $this->result
            : ProjectionBuildSetRecoveryStatus::ABSENT;
        $read = $status === ProjectionBuildSetRecoveryStatus::AVAILABLE
            ? new ProjectionBuildSetReadResult('build', 'hash', [])
            : null;

        return $callback(new ProjectionBuildSetRecoveryResult($status, $read));
    }
}
