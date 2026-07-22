<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;
use BlackOps\Internal\Console\DatabaseSeedCommand;
use BlackOps\Internal\Console\DatabaseSeedRuntimeException;
use BlackOps\Internal\Seeder\CompiledSeederRuntime;
use BlackOps\Internal\Seeder\SeederRuntimeException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

final class DatabaseSeedCommandTest extends TestCase
{
    public function testRunsConfiguredRootOnceAndWritesFixedSuccess(): void
    {
        $runner = new DatabaseSeedCommandRunner();
        $tester =
            new CommandTester(new DatabaseSeedCommand(static fn(): CompiledSeederRuntime => new CompiledSeederRuntime(
                $runner,
                DatabaseSeedCommandRoot::class,
            )));

        self::assertSame(0, $tester->execute([]));
        self::assertSame("Database seeding completed.\n", $tester->getDisplay());
        self::assertSame([DatabaseSeedCommandRoot::class], $runner->seeders);
    }

    public function testReportsUnconfiguredRuntimeAsFixedFailure(): void
    {
        $tester = new CommandTester(
            new DatabaseSeedCommand(
                static fn(): CompiledSeederRuntime => new CompiledSeederRuntime(new DatabaseSeedCommandRunner(), null),
            ),
        );

        self::assertSame(1, $tester->execute([]));
        self::assertSame("Database seeding is not configured.\n", $tester->getDisplay());
    }

    public function testSeparatesArtifactAndResolutionFailures(): void
    {
        $artifact = new CommandTester(
            new DatabaseSeedCommand(static fn(): never => throw DatabaseSeedRuntimeException::artifact()),
        );
        $resolution = new CommandTester(
            new DatabaseSeedCommand(static fn(): never => throw DatabaseSeedRuntimeException::resolution()),
        );

        self::assertSame(1, $artifact->execute([]));
        self::assertSame("Database seeding artifacts are unavailable.\n", $artifact->getDisplay());
        self::assertSame(1, $resolution->execute([]));
        self::assertSame("Database seeding runtime could not be resolved.\n", $resolution->getDisplay());
    }

    public function testMapsCompiledLocatorFailureToSafeResolutionFailure(): void
    {
        $runner = new DatabaseSeedCommandRunner(
            new SeederRuntimeException('Seeder is not available in the compiled application.'),
        );
        $tester =
            new CommandTester(new DatabaseSeedCommand(static fn(): CompiledSeederRuntime => new CompiledSeederRuntime(
                $runner,
                DatabaseSeedCommandRoot::class,
            )));

        self::assertSame(1, $tester->execute([]));
        self::assertSame("Database seeding runtime could not be resolved.\n", $tester->getDisplay());
    }

    public function testApplicationThrowableDetailIsHiddenAtDebugVerbosity(): void
    {
        $secret = 'sql-and-seed-value-that-must-not-appear';
        $runner = new DatabaseSeedCommandRunner(new RuntimeException($secret));
        $tester =
            new CommandTester(new DatabaseSeedCommand(static fn(): CompiledSeederRuntime => new CompiledSeederRuntime(
                $runner,
                DatabaseSeedCommandRoot::class,
            )));

        self::assertSame(1, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_DEBUG]));
        self::assertSame("Database seeding failed.\n", $tester->getDisplay());
        self::assertStringNotContainsString($secret, $tester->getDisplay());
        self::assertStringNotContainsString('RuntimeException', $tester->getDisplay());
    }
}

/** @mago-expect lint:single-class-per-file */
final class DatabaseSeedCommandRunner implements SeederRunner
{
    /** @var list<string> */
    public array $seeders = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
    ) {}

    public function run(string ...$seeders): void
    {
        $this->seeders = [...$this->seeders, ...$seeders];
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class DatabaseSeedCommandRoot implements Seeder
{
    public function run(): void {}
}
