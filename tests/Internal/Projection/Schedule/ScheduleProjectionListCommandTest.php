<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Schedule;

use BlackOps\Http\Status\OperationStatusHttpContract;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetAbsentException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\Schedule\ScheduleProjectionListCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

final class ScheduleProjectionListCommandTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            chmod($directory, 0o700);
            $this->removeDirectory($directory);
        }
    }

    public function testCanonicalAndAliasHaveIdenticalHumanAndJsonWires(): void
    {
        $fixture = $this->fixture();

        $canonical = $this->applicationTester($fixture);
        self::assertSame(0, $canonical->run(['command' => 'schedule:list'], [
            'capture_stderr_separately' => true,
        ]));
        $alias = $this->applicationTester($fixture);
        self::assertSame(0, $alias->run(['command' => 'schedule:ls'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame($canonical->getDisplay(), $alias->getDisplay());
        self::assertSame($canonical->getErrorOutput(), $alias->getErrorOutput());
        self::assertSame('', $canonical->getErrorOutput());
        self::assertStringContainsString('Name', $canonical->getDisplay());
        self::assertStringContainsString('fixture.run', $canonical->getDisplay());

        $canonicalJson = $this->applicationTester($fixture);
        self::assertSame(0, $canonicalJson->run([
            'command' => 'schedule:list',
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        $aliasJson = $this->applicationTester($fixture);
        self::assertSame(0, $aliasJson->run([
            'command' => 'schedule:ls',
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame($canonicalJson->getDisplay(), $aliasJson->getDisplay());
        self::assertSame($canonicalJson->getErrorOutput(), $aliasJson->getErrorOutput());
        $document = json_decode($canonicalJson->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['schedules', 'schemaVersion'], array_keys($document));
        self::assertArrayNotHasKey('applicationProjectionStatus', $document);
        self::assertSame('fixture.run', $document['schedules'][0]['name']);
    }

    public function testEmptyScheduleSetIsSuccessful(): void
    {
        $fixture = $this->fixture(scheduleRecords: []);
        $tester = new CommandTester($this->command($fixture));

        self::assertSame(0, $tester->execute(['--json' => true], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame(
            ['schedules' => [], 'schemaVersion' => 1],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testAllNonAvailableStatusesFailWithTheClosedWire(): void
    {
        $cases = [];
        $cases['absent'] = $this->fixture(publish: false);

        $malformed = $this->fixture();
        $schedule = glob($malformed['locator']->directory() . '/schedule-*.json');
        self::assertIsArray($schedule);
        self::assertCount(1, $schedule);
        file_put_contents($schedule[0], "tampered\n");
        $cases['malformed'] = $malformed;

        $unsupported = $this->fixture();
        $root = file_get_contents($unsupported['locator']->rootPath());
        self::assertIsString($root);
        $rootDocument = json_decode($root, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($rootDocument);
        $rootDocument['schemaVersion'] = 2;
        file_put_contents(
            $unsupported['locator']->rootPath(),
            new ProjectionBuildSetCodec()->canonicalJson($rootDocument) . "\n",
        );
        $cases['unsupported'] = $unsupported;

        $stale = $this->fixture();
        $stale['expected']['schedule'] = str_repeat('0', 64);
        $cases['stale'] = $stale;
        $cases['build_mismatch'] = $this->fixture();

        foreach ($cases as $status => $fixture) {
            $expectedBuild = $status === 'build_mismatch' ? 'build-2' : 'build-1';
            $tester = new CommandTester($this->command($fixture, expectedBuild: $expectedBuild));

            self::assertSame(
                2,
                $tester->execute([], [
                    'capture_stderr_separately' => true,
                ]),
                $status,
            );
            self::assertSame('', $tester->getDisplay(), $status);
            self::assertSame("Schedule projection is unavailable or invalid.\n", $tester->getErrorOutput(), $status);
        }
    }

    public function testNonSelectedProjectionFailureAlsoFailsScheduleRead(): void
    {
        foreach (['command', 'route'] as $kind) {
            $fixture = $this->fixture();
            $projection = glob($fixture['locator']->directory() . '/' . $kind . '-*.json');
            self::assertIsArray($projection);
            self::assertCount(1, $projection);
            file_put_contents($projection[0], "tampered\n");

            $tester = new CommandTester($this->command($fixture));
            self::assertSame(
                2,
                $tester->execute(['--json' => true], [
                    'capture_stderr_separately' => true,
                ]),
                $kind,
            );
            self::assertSame('', $tester->getDisplay(), $kind);
            self::assertSame("Schedule projection is unavailable or invalid.\n", $tester->getErrorOutput(), $kind);
        }
    }

    public function testInputAndOperationalFailuresDoNotLeakDetails(): void
    {
        $fixture = $this->fixture();
        $rootPath = $fixture['locator']->rootPath();
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use ($rootPath): void {
            if ($event === 'fopen:rb:' . $rootPath) {
                throw new ProjectionBuildSetFilesystemException('credential path must not leak');
            }
        });
        $operational = new CommandTester($this->command(
            $fixture,
            reader: new ProjectionBuildSetReader($fixture['locator'], filesystem: $filesystem),
        ));
        self::assertSame(1, $operational->execute(['--json' => true], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $operational->getDisplay());
        self::assertSame("Projection build-set read failed safely.\n", $operational->getErrorOutput());

        foreach ([['--unknown' => true], ['unexpected']] as $input) {
            $invalid = new CommandTester($this->command($fixture));
            self::assertSame(2, $invalid->execute($input, [
                'capture_stderr_separately' => true,
            ]));
            self::assertSame('', $invalid->getDisplay());
            self::assertSame("Input error.\n", $invalid->getErrorOutput());
        }

        $invalidJson = new CommandTester($this->command($fixture));
        self::assertSame(2, $invalidJson->execute(['--json' => 'not-a-boolean'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $invalidJson->getDisplay());
        self::assertSame("Input error.\n", $invalidJson->getErrorOutput());

        $invalidIdentity = new CommandTester($this->command($fixture, expectedBuild: ''));
        self::assertSame(2, $invalidIdentity->execute([], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $invalidIdentity->getDisplay());
        self::assertSame("Schedule projection input is invalid.\n", $invalidIdentity->getErrorOutput());
    }

    /** @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture */
    private function command(
        array $fixture,
        ?ProjectionBuildSetReader $reader = null,
        string $expectedBuild = 'build-1',
    ): ScheduleProjectionListCommand {
        return new ScheduleProjectionListCommand(
            reader: $reader ?? new ProjectionBuildSetReader($fixture['locator']),
            expectedApplicationBuildId: $expectedBuild,
            expectedInputHashes: $fixture['expected'],
        );
    }

    /** @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture */
    private function applicationTester(array $fixture): ApplicationTester
    {
        $application = new Application('BlackOps');
        $application->setAutoExit(false);
        $application->addCommand($this->command($fixture));

        return new ApplicationTester($application);
    }

    /** @param list<array<string, mixed>>|null $scheduleRecords
     * @return array{locator: ProjectionBuildSetLocator, expected: array<string, string>}
     */
    private function fixture(bool $publish = true, ?array $scheduleRecords = null): array
    {
        $directory = sys_get_temp_dir() . '/blackops-schedule-list-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $this->directories[] = $directory;
        $locator = new ProjectionBuildSetLocator($directory);
        $inputs = $this->inputs($scheduleRecords);
        $package = new ProjectionBuildSetCodec()->root('build-1', $inputs);
        if ($publish) {
            new ProjectionBuildSetPublisher($locator)->publish('build-1', $inputs);
        }

        return [
            'locator' => $locator,
            'expected' => array_map(
                static fn(array $descriptor): string => $descriptor['inputHash'],
                $package['root']['projections'],
            ),
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function inputs(?array $scheduleRecords = null): array
    {
        return [
            'command' => [[
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List commands',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => [OperationStatusHttpContract::descriptor()],
            'schedule' => $scheduleRecords ?? [[
                'name' => 'fixture.run',
                'operationType' => 'fixture.run',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ]],
        ];
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if (!is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                chmod($path, 0o600);
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
