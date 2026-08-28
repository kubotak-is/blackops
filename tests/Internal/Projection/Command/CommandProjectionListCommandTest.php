<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Command;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\Command\CommandProjectionListCommand;
use BlackOps\Internal\Projection\Command\CommandProjectionNamespaceResolver;
use BlackOps\Internal\Projection\Command\CommandProjectionRenderer;
use BlackOps\Internal\Projection\Command\CommandProjectionTarget;
use BlackOps\Internal\Projection\Command\CommandProjectionTargetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandProjectionListCommandTest extends TestCase
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

    public function testAdversarialValuesRemainLiteralForDecoratedAndUndecoratedOutput(): void
    {
        $fixture = $this->fixture(commandRecords: [$this->adversarialRecord()]);
        $formats = ['txt', 'raw', 'json', 'xml', 'md'];

        foreach ($formats as $format) {
            $input = $format === 'raw' ? ['--raw' => true] : ['--format' => $format];
            $plain = new CommandTester($this->command($fixture));
            $decorated = new CommandTester($this->command($fixture));

            self::assertSame(
                0,
                $plain->execute($input, [
                    'capture_stderr_separately' => true,
                    'decorated' => false,
                ]),
                $format,
            );
            self::assertSame(
                0,
                $decorated->execute($input, [
                    'capture_stderr_separately' => true,
                    'decorated' => true,
                ]),
                $format,
            );
            self::assertSame($plain->getDisplay(), $decorated->getDisplay(), $format);
            self::assertSame($plain->getErrorOutput(), $decorated->getErrorOutput(), $format);
            self::assertStringNotContainsString("\x1B", $plain->getDisplay(), $format);
            self::assertStringEndsWith("\n", $plain->getDisplay(), $format);
            self::assertFalse(str_ends_with($plain->getDisplay(), "\n\n"), $format);

            match ($format) {
                'txt' => $this->assertTextAdversarialValues($plain->getDisplay()),
                'raw' => self::assertSame("<info>x</info>\t</error>,<fg=red>alias</>\n", $plain->getDisplay()),
                'json' => $this->assertJsonAdversarialValues($plain->getDisplay()),
                'xml' => $this->assertXmlAdversarialValues($plain->getDisplay()),
                'md' => $this->assertMarkdownAdversarialValues($plain->getDisplay()),
            };
        }
    }

    public function testAvailableJsonRawAndAllUseTheValidatedProjection(): void
    {
        $fixture = $this->fixture();

        $listApplication = $this->applicationTester($fixture);
        self::assertSame(0, $listApplication->run([
            'command' => 'list',
            '--format' => 'json',
        ], ['capture_stderr_separately' => true]));
        $lsApplication = $this->applicationTester($fixture);
        self::assertSame(0, $lsApplication->run([
            'command' => 'ls',
            '--format' => 'json',
        ], ['capture_stderr_separately' => true]));
        self::assertSame($listApplication->getDisplay(), $lsApplication->getDisplay());

        $jsonTester = new CommandTester($this->command($fixture));
        self::assertSame(0, $jsonTester->execute(['--json' => true], [
            'capture_stderr_separately' => true,
        ]));
        $json = json_decode($jsonTester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('available', $json['applicationProjectionStatus']);
        self::assertContains('ops:status', array_column($json['commands'], 'name'));
        self::assertNotContains('ops:hidden', array_column($json['commands'], 'name'));
        self::assertSame('', $jsonTester->getErrorOutput());

        $jsonFormatTester = new CommandTester($this->command($fixture));
        self::assertSame(0, $jsonFormatTester->execute(['--format' => 'json'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame($jsonTester->getDisplay(), $jsonFormatTester->getDisplay());

        foreach (['json', 'txt', 'raw'] as $format) {
            $normal = new CommandTester($this->command($fixture));
            $short = new CommandTester($this->command($fixture));
            $normalArguments = $format === 'raw' ? ['--raw' => true] : ['--format' => $format];
            $shortArguments = $format === 'raw'
                ? ['--raw' => true, '--short' => true]
                : ['--format' => $format, '--short' => true];
            self::assertSame(
                0,
                $normal->execute($normalArguments, [
                    'capture_stderr_separately' => true,
                ]),
                $format,
            );
            self::assertSame(
                0,
                $short->execute($shortArguments, [
                    'capture_stderr_separately' => true,
                ]),
                $format,
            );
            self::assertSame($normal->getDisplay(), $short->getDisplay(), $format);
            self::assertSame($normal->getErrorOutput(), $short->getErrorOutput(), $format);
        }

        $rawStructured = new CommandTester($this->command($fixture));
        self::assertSame(0, $rawStructured->execute([
            '--format' => 'json',
            '--raw' => true,
        ], ['capture_stderr_separately' => true]));
        self::assertSame($jsonFormatTester->getDisplay(), $rawStructured->getDisplay());

        $rawTester = new CommandTester($this->command($fixture));
        self::assertSame(0, $rawTester->execute(['--raw' => true], [
            'capture_stderr_separately' => true,
        ]));
        self::assertStringContainsString("ops:status\tos\n", $rawTester->getDisplay());
        self::assertStringNotContainsString('Status command', $rawTester->getDisplay());
        self::assertSame("applicationProjectionStatus=available\n", $rawTester->getErrorOutput());

        $allTester = new CommandTester($this->command($fixture));
        self::assertSame(0, $allTester->execute(['--format' => 'json', '--all' => true], [
            'capture_stderr_separately' => true,
        ]));
        $all = json_decode($allTester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $hidden = array_values(array_filter(
            $all['commands'],
            static fn(array $command): bool => $command['name'] === 'ops:hidden',
        ));
        self::assertCount(1, $hidden);
        self::assertTrue($hidden[0]['hidden']);
        self::assertArrayHasKey('hidden', $hidden[0]);
    }

    public function testAvailableProducerRowsExposeOnlyCanonicalRouteTarget(): void
    {
        $records = array_map(static fn(CommandProjectionTarget $target): array => $target->record(
            'framework',
        ), CommandProjectionTargetRegistry::all());
        $fixture = $this->fixture(commandRecords: $records);

        $this->assertFrameworkRouteTargetAcrossFormats($fixture, 'build-1', 'available', 35);
    }

    public function testEveryNonOperationalStatusFallsBackToEmbeddedRows(): void
    {
        $cases = [];

        $absent = $this->fixture(publish: false);
        $cases['absent'] = $absent;

        $malformed = $this->fixture();
        $command = glob($malformed['locator']->directory() . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        file_put_contents($command[0], "tampered\n");
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
        $stale['expected']['command'] = str_repeat('0', 64);
        $cases['stale'] = $stale;

        $buildMismatch = $this->fixture();
        $cases['build_mismatch'] = $buildMismatch;

        foreach ($cases as $status => $fixture) {
            $expectedBuild = $status === 'build_mismatch' ? 'build-2' : 'build-1';
            $tester = new CommandTester($this->command($fixture, expectedBuild: $expectedBuild));
            self::assertSame(
                0,
                $tester->execute(['--format' => 'json'], [
                    'capture_stderr_separately' => true,
                ]),
                $status,
            );
            $json = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($status, $json['applicationProjectionStatus']);
            self::assertContains('list', array_column($json['commands'], 'name'));
            self::assertNotContains('ops:status', array_column($json['commands'], 'name'));
            self::assertCount(34, $json['commands']);
            self::assertContains('build:compile', array_column($json['commands'], 'name'));
            self::assertSame(array_fill(0, 34, 'framework'), array_column($json['commands'], 'origin'));
            self::assertSame('', $tester->getErrorOutput());

            $rawTester = new CommandTester($this->command($fixture, expectedBuild: $expectedBuild));
            self::assertSame(
                0,
                $rawTester->execute(['--raw' => true], [
                    'capture_stderr_separately' => true,
                ]),
                $status,
            );
            self::assertStringContainsString("build:compile\t\n", $rawTester->getDisplay(), $status);
            self::assertSame("applicationProjectionStatus={$status}\n", $rawTester->getErrorOutput());

            $this->assertFrameworkRouteTargetAcrossFormats($fixture, $expectedBuild, $status, 35);
        }

        $nonSelected = $this->fixture();
        $route = glob($nonSelected['locator']->directory() . '/route-*.json');
        self::assertIsArray($route);
        self::assertCount(1, $route);
        file_put_contents($route[0], "tampered\n");
        $tester = new CommandTester($this->command($nonSelected));
        self::assertSame(0, $tester->execute(['--format' => 'json'], [
            'capture_stderr_separately' => true,
        ]));
        $json = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('malformed', $json['applicationProjectionStatus']);
        self::assertCount(34, $json['commands']);
        self::assertContains('build:compile', array_column($json['commands'], 'name'));
    }

    public function testNamespaceStableSelectionAndInputRulesRemainPure(): void
    {
        $fixture = $this->fixture();
        $namespace = $this->applicationTester($fixture);
        self::assertSame(0, $namespace->run([
            'command' => 'list',
            'namespace' => 'nested:deep',
            '--format' => 'json',
        ], ['capture_stderr_separately' => true]));
        $nested = json_decode($namespace->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['nested:deep:run'], array_column($nested['commands'], 'name'));

        $unique = $this->applicationTester($fixture);
        self::assertSame(0, $unique->run([
            'command' => 'list',
            'namespace' => 'nes:d',
            '--format' => 'json',
        ], ['capture_stderr_separately' => true]));
        self::assertSame(
            ['nested:deep:run'],
            array_column(json_decode($unique->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['commands'], 'name'),
        );

        $ambiguous = $this->applicationTester($fixture);
        self::assertSame(1, $ambiguous->run([
            'command' => 'list',
            'namespace' => 'al',
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $ambiguous->getDisplay());
        self::assertStringContainsString('The namespace "al" is ambiguous.', $ambiguous->getErrorOutput());

        $unknown = $this->applicationTester($fixture);
        self::assertSame(1, $unknown->run([
            'command' => 'list',
            'namespace' => 'zzz',
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $unknown->getDisplay());
        self::assertStringContainsString(
            'There are no commands defined in the "zzz" namespace.',
            $unknown->getErrorOutput(),
        );

        $default = new CommandTester($this->command($fixture));
        $short = new CommandTester($this->command($fixture));
        self::assertSame(0, $default->execute(['--format' => 'json'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame(0, $short->execute(['--format' => 'json', '--short' => true], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame($default->getDisplay(), $short->getDisplay());

        $invalid = new CommandTester($this->command($fixture));
        self::assertSame(2, $invalid->execute(['--format' => 'yaml'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $invalid->getDisplay());
        self::assertSame("Input error.\n", $invalid->getErrorOutput());

        $conflict = new CommandTester($this->command($fixture));
        self::assertSame(2, $conflict->execute([
            '--json' => true,
            '--format' => 'json',
        ], ['capture_stderr_separately' => true]));
        self::assertSame('', $conflict->getDisplay());
        self::assertSame("Input error.\n", $conflict->getErrorOutput());

        $missing = new CommandTester($this->command($fixture));
        self::assertSame(2, $missing->execute(['--format' => null], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $missing->getDisplay());
        self::assertSame("Input error.\n", $missing->getErrorOutput());

        $unknownOption = new CommandTester($this->command($fixture));
        self::assertSame(2, $unknownOption->execute(['--unknown' => true], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $unknownOption->getDisplay());
        self::assertSame("Input error.\n", $unknownOption->getErrorOutput());

        $repeatedInput = new ArgvInput(['blackops', 'list', '--format=txt', '--format=json']);
        $repeatedOutput = new BufferedOutput();
        $application = new Application('BlackOps');
        $application->setAutoExit(false);
        $application->addCommand($this->command($fixture));
        self::assertSame(0, $application->run($repeatedInput, $repeatedOutput));
        self::assertStringStartsWith('{', $repeatedOutput->fetch());

        self::assertSame(['ls'], $this->command($fixture)->getAliases());
        self::assertSame('List commands', $this->command($fixture)->getDescription());
    }

    public function testOperationalReaderFailureIsSafeAndProducesNoStdout(): void
    {
        $fixture = $this->fixture();
        $rootPath = $fixture['locator']->rootPath();
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use ($rootPath): void {
            if ($event === 'fopen:rb:' . $rootPath) {
                throw new ProjectionBuildSetFilesystemException('secret filesystem detail');
            }
        });
        $reader = new ProjectionBuildSetReader($fixture['locator'], filesystem: $filesystem);
        $tester = new CommandTester($this->command($fixture, reader: $reader));

        self::assertSame(1, $tester->execute(['--format' => 'json'], [
            'capture_stderr_separately' => true,
        ]));
        self::assertSame('', $tester->getDisplay());
        self::assertSame("Projection build-set read failed safely.\n", $tester->getErrorOutput());
        self::assertStringNotContainsString('secret filesystem detail', $tester->getErrorOutput());
    }

    /**
     * @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture
     */
    private function command(
        array $fixture,
        ?ProjectionBuildSetReader $reader = null,
        string $expectedBuild = 'build-1',
    ): CommandProjectionListCommand {
        return new CommandProjectionListCommand(
            reader: $reader ?? new ProjectionBuildSetReader($fixture['locator']),
            expectedApplicationBuildId: $expectedBuild,
            expectedInputHashes: $fixture['expected'],
            targetRegistry: new CommandProjectionTargetRegistry(),
            renderer: new CommandProjectionRenderer(),
            namespaceResolver: new CommandProjectionNamespaceResolver(),
        );
    }

    /**
     * @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture
     */
    private function applicationTester(
        array $fixture,
        ?ProjectionBuildSetReader $reader = null,
        string $expectedBuild = 'build-1',
    ): ApplicationTester {
        $application = new Application('BlackOps');
        $application->setAutoExit(false);
        $application->addCommand($this->command($fixture, $reader, $expectedBuild));

        return new ApplicationTester($application);
    }

    /**
     * @param array{locator: ProjectionBuildSetLocator, expected: array<string, string>} $fixture
     */
    private function assertFrameworkRouteTargetAcrossFormats(
        array $fixture,
        string $expectedBuild,
        string $status,
        int $expectedRows,
    ): void {
        foreach (['txt', 'raw', 'json', 'xml', 'md'] as $format) {
            $tester = new CommandTester($this->command($fixture, expectedBuild: $expectedBuild));
            $arguments = $format === 'raw'
                ? ['--raw' => true, '--all' => true]
                : ['--format' => $format, '--all' => true];
            self::assertSame(
                0,
                $tester->execute($arguments, [
                    'capture_stderr_separately' => true,
                ]),
                $status . ':' . $format,
            );
            $expectedError = in_array($format, ['txt', 'raw'], strict: true)
                ? "applicationProjectionStatus={$status}\n"
                : '';
            self::assertSame($expectedError, $tester->getErrorOutput(), $status . ':' . $format);

            match ($format) {
                'txt' => $this->assertTextFrameworkRouteTarget($tester->getDisplay(), $expectedRows),
                'raw' => $this->assertRawFrameworkRouteTarget($tester->getDisplay(), $expectedRows),
                'json' => $this->assertJsonFrameworkRouteTarget($tester->getDisplay(), $expectedRows),
                'xml' => $this->assertXmlFrameworkRouteTarget($tester->getDisplay(), $expectedRows, $status),
                'md' => $this->assertMarkdownFrameworkRouteTarget($tester->getDisplay(), $expectedRows, $status),
            };
        }
    }

    private function assertTextFrameworkRouteTarget(string $display, int $expectedRows): void
    {
        self::assertSame($expectedRows, substr_count($display, '| framework |'));
        self::assertSame(1, preg_match_all('/^\|\s*route:list\s+\|\s*route:ls\s+\|/m', $display));
        self::assertSame(0, preg_match('/^\|\s*route:ls\s+\|/m', $display));
        self::assertSame(1, substr_count($display, 'List compiled HTTP routes.'));
    }

    private function assertRawFrameworkRouteTarget(string $display, int $expectedRows): void
    {
        $rows = array_values(array_filter(explode("\n", $display), static fn(string $row): bool => $row !== ''));
        self::assertCount($expectedRows, $rows);
        self::assertSame(1, substr_count($display, "route:list\troute:ls\n"));
        self::assertSame(0, preg_match('/^route:ls\t/m', $display));
    }

    private function assertJsonFrameworkRouteTarget(string $display, int $expectedRows): void
    {
        $document = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount($expectedRows, $document['commands']);
        self::assertSame(['framework'], array_values(array_unique(array_column($document['commands'], 'origin'))));
        $this->assertStructuredFrameworkRouteTarget($document['commands']);
    }

    private function assertXmlFrameworkRouteTarget(string $display, int $expectedRows, string $status): void
    {
        self::assertSame($expectedRows, substr_count($display, '<command>'));
        self::assertSame($expectedRows, substr_count($display, '<origin>framework</origin>'));
        self::assertSame(1, substr_count($display, '<name>route:list</name>'));
        self::assertSame(1, substr_count($display, '<alias>route:ls</alias>'));
        self::assertSame(0, substr_count($display, '<name>route:ls</name>'));
        self::assertStringContainsString('status="' . $status . '"', $display);
    }

    private function assertMarkdownFrameworkRouteTarget(string $display, int $expectedRows, string $status): void
    {
        self::assertSame($expectedRows, substr_count($display, '| framework |'));
        self::assertSame(1, substr_count($display, '| route:list | route:ls |'));
        self::assertSame(0, preg_match('/^\|\s*route:ls\s+\|/m', $display));
        self::assertStringContainsString('status=' . $status, $display);
    }

    /** @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden?: bool}> $records */
    private function assertStructuredFrameworkRouteTarget(array $records): void
    {
        $canonical = array_values(array_filter(
            $records,
            static fn(array $record): bool => $record['name'] === 'route:list',
        ));
        self::assertCount(1, $canonical);
        self::assertSame(['route:ls'], $canonical[0]['aliases']);
        self::assertNotContains('route:ls', array_column($records, 'name'));
    }

    /**
     * @param list<array<string, mixed>>|null $commandRecords
     * @return array{locator: ProjectionBuildSetLocator, expected: array<string, string>}
     */
    private function fixture(bool $publish = true, ?array $commandRecords = null): array
    {
        $directory = sys_get_temp_dir() . '/blackops-command-list-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $this->directories[] = $directory;
        $locator = new ProjectionBuildSetLocator($directory);
        $inputs = [
            'command' => $commandRecords ?? [
                [
                    'name' => 'ops:status',
                    'aliases' => ['os'],
                    'description' => 'Status command',
                    'origin' => 'application',
                    'hidden' => false,
                ],
                [
                    'name' => 'ops:hidden',
                    'aliases' => [],
                    'description' => 'Hidden command',
                    'origin' => 'application',
                    'hidden' => true,
                ],
                [
                    'name' => 'nested:deep:run',
                    'aliases' => [],
                    'description' => 'Nested command',
                    'origin' => 'application',
                    'hidden' => false,
                ],
                [
                    'name' => 'alpha:run',
                    'aliases' => [],
                    'description' => 'Alpha command',
                    'origin' => 'application',
                    'hidden' => false,
                ],
                [
                    'name' => 'alpine:run',
                    'aliases' => [],
                    'description' => 'Alpine command',
                    'origin' => 'application',
                    'hidden' => false,
                ],
                [
                    'name' => 'plain',
                    'aliases' => ['ops:alias'],
                    'description' => 'Aliased command',
                    'origin' => 'application',
                    'hidden' => false,
                ],
            ],
            'route' => [[
                'method' => 'GET',
                'path' => '/fixture',
                'strategy' => 'inline',
                'operationType' => 'fixture.read',
                'origin' => 'operation_manifest',
            ]],
            'schedule' => [[
                'name' => 'fixture.run',
                'operationType' => 'fixture.run',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ]],
        ];
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

    /** @return array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool} */
    private function adversarialRecord(): array
    {
        return [
            'name' => '<info>x</info>',
            'aliases' => ['</error>', '<fg=red>alias</>'],
            'description' => '<comment>Description</comment> </error>',
            'origin' => 'application',
            'hidden' => false,
        ];
    }

    private function assertTextAdversarialValues(string $display): void
    {
        self::assertStringContainsString('<info>x</info>', $display);
        self::assertStringContainsString('</error>,<fg=red>alias</>', $display);
        self::assertStringContainsString('<comment>Description</comment> </error>', $display);
        self::assertStringEndsWith("\n", $display);
        self::assertStringNotContainsString("\n\n", $display);
    }

    private function assertJsonAdversarialValues(string $display): void
    {
        $document = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            [[
                'aliases' => ['</error>', '<fg=red>alias</>'],
                'description' => '<comment>Description</comment> </error>',
                'name' => '<info>x</info>',
                'origin' => 'application',
            ]],
            $document['commands'],
        );
        self::assertStringEndsWith("\n", $display);
    }

    private function assertXmlAdversarialValues(string $display): void
    {
        self::assertStringContainsString('<name>&lt;info&gt;x&lt;/info&gt;</name>', $display);
        self::assertStringContainsString('<alias>&lt;/error&gt;</alias>', $display);
        self::assertStringContainsString('<alias>&lt;fg=red&gt;alias&lt;/&gt;</alias>', $display);
        self::assertStringContainsString('&lt;comment&gt;Description&lt;/comment&gt; &lt;/error&gt;', $display);
        self::assertStringEndsWith("\n", $display);
    }

    private function assertMarkdownAdversarialValues(string $display): void
    {
        self::assertStringContainsString('| <info>x</info> |', $display);
        self::assertStringContainsString('</error>,<fg=red>alias</>', $display);
        self::assertStringContainsString('<comment>Description</comment> </error>', $display);
        self::assertStringEndsWith("\n", $display);
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
