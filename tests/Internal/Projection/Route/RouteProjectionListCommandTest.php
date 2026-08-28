<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Route;

use BlackOps\Http\Status\OperationStatusHttpContract;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetAbsentException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\Route\RouteProjectionListCommand;
use BlackOps\Internal\Projection\Route\RouteProjectionRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

final class RouteProjectionListCommandTest extends TestCase
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
        self::assertSame(0, $canonical->run([
            'command' => 'route:list',
        ], ['capture_stderr_separately' => true]));
        $alias = $this->applicationTester($fixture);
        self::assertSame(0, $alias->run([
            'command' => 'route:ls',
        ], ['capture_stderr_separately' => true]));
        self::assertSame($canonical->getDisplay(), $alias->getDisplay());
        self::assertSame($canonical->getErrorOutput(), $alias->getErrorOutput());
        self::assertSame('', $canonical->getErrorOutput());
        self::assertStringContainsString('Method', $canonical->getDisplay());
        self::assertStringContainsString(OperationStatusHttpContract::PATH, $canonical->getDisplay());

        $canonicalJson = $this->applicationTester($fixture);
        self::assertSame(0, $canonicalJson->run([
            'command' => 'route:list',
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        $aliasJson = $this->applicationTester($fixture);
        self::assertSame(0, $aliasJson->run([
            'command' => 'route:ls',
            '--json' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame($canonicalJson->getDisplay(), $aliasJson->getDisplay());
        self::assertSame($canonicalJson->getErrorOutput(), $aliasJson->getErrorOutput());
        $document = json_decode($canonicalJson->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $document['schemaVersion']);
        self::assertArrayNotHasKey('applicationProjectionStatus', $document);
        self::assertContains(OperationStatusHttpContract::PATH, array_column($document['routes'], 'path'));
    }

    public function testHumanOutputIsIdenticalForDecoratedAndUndecoratedConsole(): void
    {
        $fixture = $this->fixture();
        $plain = $this->applicationTester($fixture);
        self::assertSame(0, $plain->run([
            'command' => 'route:list',
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        $decorated = $this->applicationTester($fixture);
        self::assertSame(0, $decorated->run([
            'command' => 'route:list',
        ], [
            'capture_stderr_separately' => true,
            'decorated' => true,
        ]));

        self::assertSame($plain->getDisplay(), $decorated->getDisplay());
        self::assertSame($plain->getErrorOutput(), $decorated->getErrorOutput());
        self::assertSame('', $plain->getErrorOutput());
        self::assertStringNotContainsString("\x1B", $plain->getDisplay());
    }

    public function testAdversarialHumanPathIsLiteralForDecoratedAndUndecoratedConsole(): void
    {
        $fixture = $this->fixture(routeRecords: [[
            'method' => 'GET',
            'path' => '/literal/<info>path</info>',
            'strategy' => 'custom:Vendor\\Strategy',
            'operationType' => 'literal.read',
            'origin' => 'operation_manifest',
        ]]);
        $plain = new CommandTester($this->command($fixture));
        self::assertSame(0, $plain->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => false,
        ]));
        $decorated = new CommandTester($this->command($fixture));
        self::assertSame(0, $decorated->execute([], [
            'capture_stderr_separately' => true,
            'decorated' => true,
        ]));

        self::assertSame($plain->getDisplay(), $decorated->getDisplay());
        self::assertSame('', $plain->getErrorOutput());
        self::assertStringContainsString('/literal/<info>path</info>', $plain->getDisplay());
        self::assertStringNotContainsString("\x1B", $plain->getDisplay());
    }

    public function testAllArtifactStatusesFailSafelyWithExitTwo(): void
    {
        $cases = [];

        $cases['absent'] = $this->fixture(publish: false);

        $malformed = $this->fixture();
        $route = glob($malformed['locator']->directory() . '/route-*.json');
        self::assertIsArray($route);
        self::assertCount(1, $route);
        file_put_contents($route[0], "tampered\n");
        $cases['malformed'] = $malformed;

        $unsupported = $this->fixture();
        $root = file_get_contents($unsupported['locator']->rootPath());
        self::assertIsString($root);
        $document = json_decode($root, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        $document['schemaVersion'] = 2;
        file_put_contents(
            $unsupported['locator']->rootPath(),
            new ProjectionBuildSetCodec()->canonicalJson($document) . "\n",
        );
        $cases['unsupported'] = $unsupported;

        $stale = $this->fixture();
        $stale['expected']['route'] = str_repeat('0', 64);
        $cases['stale'] = $stale;

        $buildMismatch = $this->fixture();
        $cases['build_mismatch'] = $buildMismatch;

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
            self::assertSame("Route projection is unavailable or invalid.\n", $tester->getErrorOutput(), $status);
        }
    }

    public function testNonSelectedProjectionFailureAlsoFailsRouteRead(): void
    {
        foreach (['command', 'schedule'] as $kind) {
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
            self::assertSame("Route projection is unavailable or invalid.\n", $tester->getErrorOutput(), $kind);
        }
    }

    public function testJsonOptionRejectsNonBooleanInputWithExactInputError(): void
    {
        $fixture = $this->fixture();
        $tester = new CommandTester($this->command($fixture));

        self::assertSame(2, $tester->execute(['--json' => 'not-a-boolean'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame("Input error.\n", $tester->getErrorOutput());
    }

    public function testOperationalReadFailureAndInvalidInputsUseSafeWires(): void
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

        $invalidOption = new CommandTester($this->command($fixture));
        self::assertSame(2, $invalidOption->execute(['--unknown' => true], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $invalidOption->getDisplay());
        self::assertSame("Input error.\n", $invalidOption->getErrorOutput());

        $unexpectedArgument = new CommandTester($this->command($fixture));
        self::assertSame(2, $unexpectedArgument->execute(['unexpected'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $unexpectedArgument->getDisplay());
        self::assertSame("Input error.\n", $unexpectedArgument->getErrorOutput());

        $invalidIdentity = new CommandTester($this->command($fixture, expectedBuild: ''));
        self::assertSame(2, $invalidIdentity->execute([], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $invalidIdentity->getDisplay());
        self::assertSame("Route projection input is invalid.\n", $invalidIdentity->getErrorOutput());
    }

    public function testCallbackKeepsSharedLockThroughCallbackAndReleasesOnThrow(): void
    {
        $fixture = $this->fixture();
        $events = [];
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $reader = new ProjectionBuildSetReader($fixture['locator'], filesystem: $filesystem);
        $callbackIndex = null;
        $reader->withReadStatus(
            'build-1',
            $fixture['expected'],
            static function () use (&$events, &$callbackIndex): void {
                $callbackIndex = count($events);
            },
            selectedKey: 'route',
        );
        self::assertIsInt($callbackIndex);
        self::assertLessThanOrEqual(array_search('flock:' . LOCK_UN, $events, true), $callbackIndex);
        self::assertGreaterThan(array_search('flock:' . LOCK_SH, $events, true), $callbackIndex);

        $events = [];
        try {
            $reader->withReadStatus(
                'build-1',
                $fixture['expected'],
                static function (): void {
                    throw new RuntimeException('callback detail');
                },
                selectedKey: 'route',
            );
            self::fail('Expected callback failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('callback detail', $exception->getMessage());
        }
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'flock:' . LOCK_UN)),
        );
        self::assertSame('close', $events[array_key_last($events)]);

        $events = [];
        try {
            $reader->withReadStatus(
                'build-1',
                $fixture['expected'],
                static function (): void {
                    throw new ProjectionBuildSetAbsentException('callback absent detail');
                },
                selectedKey: 'route',
            );
            self::fail('Expected callback absent failure.');
        } catch (ProjectionBuildSetAbsentException $exception) {
            self::assertSame('callback absent detail', $exception->getMessage());
        }
        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'flock:' . LOCK_UN)),
        );
        self::assertSame('close', $events[array_key_last($events)]);
    }

    /**
     * @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture
     */
    private function command(
        array $fixture,
        ?ProjectionBuildSetReader $reader = null,
        string $expectedBuild = 'build-1',
    ): RouteProjectionListCommand {
        return new RouteProjectionListCommand(
            reader: $reader ?? new ProjectionBuildSetReader($fixture['locator']),
            expectedApplicationBuildId: $expectedBuild,
            expectedInputHashes: $fixture['expected'],
            targetRegistry: new \BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry(),
            renderer: new RouteProjectionRenderer(),
        );
    }

    /**
     * @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture
     */
    private function applicationTester(array $fixture): ApplicationTester
    {
        $application = new Application('BlackOps');
        $application->setAutoExit(false);
        $application->addCommand($this->command($fixture));

        return new ApplicationTester($application);
    }

    /**
     * @return array{locator: ProjectionBuildSetLocator, expected: array<string, string>}
     */
    private function fixture(bool $publish = true, ?array $routeRecords = null): array
    {
        $directory = sys_get_temp_dir() . '/blackops-route-list-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $this->directories[] = $directory;
        $locator = new ProjectionBuildSetLocator($directory);
        $inputs = $this->inputs($routeRecords);
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
    private function inputs(?array $routeRecords = null): array
    {
        return [
            'command' => [[
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List commands',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => $routeRecords ?? [
                OperationStatusHttpContract::descriptor(),
                [
                    'method' => 'POST',
                    'path' => '/reports',
                    'strategy' => 'inline',
                    'operationType' => 'report.create',
                    'origin' => 'operation_manifest',
                ],
            ],
            'schedule' => [[
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
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
