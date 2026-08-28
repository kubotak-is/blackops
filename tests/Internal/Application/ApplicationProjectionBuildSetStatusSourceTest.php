<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationProjectionBuildSetStatusSourceTest extends TestCase
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

    public function testMissingApplicationBuildAndDirectoryAreAbsentWithoutWriting(): void
    {
        $missing = new ApplicationProjectionBuildSetStatusSource($this->snapshot(
            sys_get_temp_dir() . '/blackops-status-missing-' . bin2hex(random_bytes(4)),
        ));
        $result = $missing->withStatus($this->identity(...));
        self::assertSame(ProjectionBuildSetRecoveryStatus::ABSENT, $result->status);

        $directory = $this->directory('missing-build');
        $configuration = $this->snapshot($directory . '/var/build');
        $result = new ApplicationProjectionBuildSetStatusSource($configuration)->withStatus($this->identity(...));
        self::assertSame(ProjectionBuildSetRecoveryStatus::ABSENT, $result->status);
        self::assertFileDoesNotExist($directory . '/var/build/projection-build-set.json');
        self::assertFileDoesNotExist($directory . '/var/build/.blackops-projection-build-set.lock');
    }

    public function testMissingBuildSectionIsAbsentButPresentInvalidConfigurationIsTyped(): void
    {
        $missing = new ApplicationConfigurationSnapshot(sys_get_temp_dir(), ['app' => []], [], [], []);
        $result = new ApplicationProjectionBuildSetStatusSource($missing)->withStatus($this->identity(...));
        self::assertSame(ProjectionBuildSetRecoveryStatus::ABSENT, $result->status);

        foreach ([
            ['app' => ['build' => 'invalid']],
            ['app' => ['build' => ['container' => '/tmp/container.php']]],
        ] as $configuration) {
            try {
                new ApplicationProjectionBuildSetStatusSource(
                    new ApplicationConfigurationSnapshot(sys_get_temp_dir(), $configuration, [], [], []),
                )->withStatus($this->identity(...));
                self::fail('Expected invalid application build configuration.');
            } catch (ProjectionBuildSetValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSymlinkSpecialAndBrokenDirectoriesAreConfigurationFailures(): void
    {
        $target = $this->directory('target');
        $link = $this->directory('link') . '-path';
        self::assertTrue(symlink($target, $link));
        $this->assertConfigurationFailure($link);

        $special = $this->directory('special') . '-file';
        self::assertTrue(file_put_contents($special, 'not a directory') !== false);
        $this->assertConfigurationFailure($special);

        $broken = $this->directory('broken') . '-link';
        self::assertTrue(symlink($broken . '-target', $broken));
        $this->assertConfigurationFailure($broken);

        $blocked = $this->directory('blocked');
        self::assertTrue(chmod($blocked, 0o000));
        $this->assertOperationalFailure($blocked . '/missing');
    }

    public function testPublishedRootUsesConfiguredIdentityAndAllProjectionDescriptors(): void
    {
        $directory = $this->directory('published');
        $locator = new ProjectionBuildSetLocator($directory);
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $source = new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory));

        $result = $source->withStatus($this->identity(...), selectedKey: 'schedule');
        self::assertSame(ProjectionBuildSetRecoveryStatus::AVAILABLE, $result->status);
        self::assertNotNull($result->read);
        self::assertSame('build-1', $result->read->applicationBuildId);
        self::assertSame('fixture.run', $result->read->records('schedule')[0]['name']);

        $mismatch = new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory, 'build-2'));
        self::assertSame(
            ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH,
            $mismatch->withStatus($this->identity(...))->status,
        );
    }

    public function testDescriptorMismatchIsStaleAndNonSelectedMismatchInvalidatesTheSet(): void
    {
        $directory = $this->directory('identity');
        $locator = new ProjectionBuildSetLocator($directory);
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());

        $root = $this->root($locator);
        $root['projections']['schedule']['inputHash'] = str_repeat('0', 64);
        $this->writeRoot($locator, $root);
        $source = new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory));
        self::assertSame(ProjectionBuildSetRecoveryStatus::STALE, $source->withStatus($this->identity(...))->status);

        $directory = $this->directory('non-selected');
        $locator = new ProjectionBuildSetLocator($directory);
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $command = glob($directory . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        file_put_contents($command[0], "tampered\n");
        $source = new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory));
        self::assertSame(
            ProjectionBuildSetRecoveryStatus::MALFORMED,
            $source->withStatus($this->identity(...))->status,
        );
    }

    public function testCallbackRunsUnderSharedLockAndThrowStillReleasesIt(): void
    {
        $directory = $this->directory('callback');
        $locator = new ProjectionBuildSetLocator($directory);
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $events = [];
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (&$events): void {
            $events[] = $event;
        });
        $source = new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory), $filesystem);

        try {
            $source->withStatus(static function (ProjectionBuildSetRecoveryResult $result) use ($locator): void {
                self::assertTrue($result->isAvailable());
                $lock = fopen($locator->lockPath(), 'rb');
                self::assertIsResource($lock);
                try {
                    self::assertFalse(flock($lock, LOCK_EX | LOCK_NB));
                } finally {
                    fclose($lock);
                }
                throw new RuntimeException('callback detail');
            });
            self::fail('Expected callback failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('callback detail', $exception->getMessage());
        }

        self::assertSame(
            1,
            count(array_filter($events, static fn(string $event): bool => $event === 'flock:' . LOCK_UN)),
        );
        self::assertSame('close', $events[array_key_last($events)]);
    }

    public function testOperationalReadFailureRemainsTypedAndDoesNotExposeItsDetail(): void
    {
        $directory = $this->directory('operational');
        $locator = new ProjectionBuildSetLocator($directory);
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $rootPath = $locator->rootPath();
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use ($rootPath): void {
            if ($event === 'fopen:rb:' . $rootPath) {
                throw new ProjectionBuildSetFilesystemException('credential detail');
            }
        });

        $this->expectException(ProjectionBuildSetFilesystemException::class);
        new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory), $filesystem)->withStatus(
            $this->identity(...),
        );
    }

    private function identity(ProjectionBuildSetRecoveryResult $result): ProjectionBuildSetRecoveryResult
    {
        return $result;
    }

    private function assertConfigurationFailure(string $directory): void
    {
        try {
            new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory))->withStatus(
                $this->identity(...),
            );
            self::fail('Expected invalid projection build-set directory.');
        } catch (ProjectionBuildSetValidationException) {
            self::addToAssertionCount(1);
        }
    }

    private function assertOperationalFailure(string $directory): void
    {
        try {
            new ApplicationProjectionBuildSetStatusSource($this->snapshot($directory))->withStatus(
                $this->identity(...),
            );
            self::fail('Expected operational projection build-set failure.');
        } catch (ProjectionBuildSetFilesystemException) {
            self::addToAssertionCount(1);
        }
    }

    private function snapshot(string $directory, string $buildId = 'build-1'): ApplicationConfigurationSnapshot
    {
        return new ApplicationConfigurationSnapshot(
            sys_get_temp_dir(),
            [
                'app' => ['build' => [
                    'operation_manifest' => $directory . '/operations.php',
                    'http_manifest' => $directory . '/http.php',
                    'frontend_manifest' => $directory . '/frontend.php',
                    'container' => $directory . '/container.php',
                    'container_class' => 'CompiledContainer',
                    'container_namespace' => '',
                    'application_build_id' => $buildId,
                ]],
            ],
            [],
            [],
            [],
        );
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function inputs(): array
    {
        return [
            'command' => [[
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List commands',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => [[
                'method' => 'GET',
                'path' => '/operations/{operationId}',
                'strategy' => null,
                'operationType' => null,
                'origin' => 'framework_fixed',
            ]],
            'schedule' => [[
                'name' => 'fixture.run',
                'operationType' => 'fixture.run',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function root(ProjectionBuildSetLocator $locator): array
    {
        $bytes = file_get_contents($locator->rootPath());
        self::assertIsString($bytes);
        $root = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($root);

        return $root;
    }

    /** @param array<string, mixed> $root */
    private function writeRoot(ProjectionBuildSetLocator $locator, array $root): void
    {
        unset($root['buildSetHash']);
        $codec = new ProjectionBuildSetCodec();
        $root['buildSetHash'] = hash(
            'sha256',
            $codec->encodeString('blackops.projection-build-set.v1')
                . $codec->encodeBytes($codec->canonicalJson($root)),
        );
        file_put_contents($locator->rootPath(), $codec->canonicalJson($root) . "\n");
    }

    private function directory(string $name): string
    {
        $directory = sys_get_temp_dir() . '/blackops-status-' . $name . '-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($directory, 0o700, true));
        $this->directories[] = $directory;

        return $directory;
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
