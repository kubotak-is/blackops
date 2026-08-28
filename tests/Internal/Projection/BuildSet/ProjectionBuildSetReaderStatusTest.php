<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\BuildSet;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use PHPUnit\Framework\TestCase;

/** @mago-expect lint:too-many-methods */
final class ProjectionBuildSetReaderStatusTest extends TestCase
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

    public function testAvailableStatusUsesCallerInjectedBuildAndInputIdentities(): void
    {
        $locator = $this->publish();
        $expected = $this->expectedInputHashes();
        $reader = new ProjectionBuildSetReader($locator);

        $result = $reader->readStatus('build-1', $expected);

        self::assertSame(ProjectionBuildSetRecoveryStatus::AVAILABLE, $result->status);
        self::assertNotNull($result->read);
        self::assertSame('build-1', $result->read->applicationBuildId);
        self::assertSame('list', $result->read->records('command')[0]['name']);
        self::assertSame('build-1', $reader->read()->applicationBuildId);

        $wrongBuild = $reader->readStatus('build-2', $expected);
        self::assertSame(ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH, $wrongBuild->status);

        $wrongInput = $expected;
        $wrongInput['command'] = str_repeat('0', 64);
        $stale = $reader->readStatus('build-1', $wrongInput);
        self::assertSame(ProjectionBuildSetRecoveryStatus::STALE, $stale->status);
    }

    public function testAbsentStatusCoversMissingLockAndNonSelectedGeneration(): void
    {
        $locator = $this->locator();
        $reader = new ProjectionBuildSetReader($locator);

        self::assertSame(
            ProjectionBuildSetRecoveryStatus::ABSENT,
            $reader->readStatus('build-1', $this->emptyExpectedHashes())->status,
        );

        $this->publishAt($locator);
        $route = glob($locator->directory() . '/route-*.json');
        self::assertIsArray($route);
        self::assertCount(1, $route);
        unlink($route[0]);

        self::assertSame(
            ProjectionBuildSetRecoveryStatus::ABSENT,
            $reader->readStatus('build-1', $this->expectedInputHashes())->status,
        );
    }

    public function testMalformedStatusCoversRootAndGenerationBytes(): void
    {
        $locator = $this->publish();
        $command = glob($locator->directory() . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        file_put_contents($command[0], "tampered\n");

        $reader = new ProjectionBuildSetReader($locator);
        self::assertSame(
            ProjectionBuildSetRecoveryStatus::MALFORMED,
            $reader->readStatus('build-1', $this->expectedInputHashes())->status,
        );

        $locator = $this->publish();
        $root = $this->root($locator);
        $root['buildSetHash'] = str_repeat('0', 64);
        file_put_contents($locator->rootPath(), new ProjectionBuildSetCodec()->canonicalJson($root) . "\n");

        self::assertSame(
            ProjectionBuildSetRecoveryStatus::MALFORMED,
            new ProjectionBuildSetReader($locator)->readStatus('build-1', $this->expectedInputHashes())->status,
        );
    }

    public function testUnsupportedStatusCoversFutureRootAndProjectionKinds(): void
    {
        $locator = $this->publish();
        $root = $this->root($locator);
        $root['schemaVersion'] = 2;
        $this->writeRoot($locator, $root);

        self::assertSame(
            ProjectionBuildSetRecoveryStatus::UNSUPPORTED,
            new ProjectionBuildSetReader($locator)->readStatus('build-1', $this->expectedInputHashes())->status,
        );

        $locator = $this->publish();
        $root = $this->root($locator);
        $routeDescriptor = $root['projections']['route'];
        $oldPath = $routeDescriptor['path'];
        $bytes = file_get_contents($locator->path($oldPath));
        self::assertIsString($bytes);
        $document = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        $document['kind'] = 'blackops_future_projection';
        $futureBytes = new ProjectionBuildSetCodec()->canonicalJson($document) . "\n";
        $futureSha = hash('sha256', $futureBytes);
        $futurePath = 'route-' . $futureSha . '.json';
        file_put_contents($locator->path($futurePath), $futureBytes);
        unlink($locator->path($oldPath));
        $root['projections']['route'] = [
            'kind' => 'blackops_route_projection',
            'inputHash' => $routeDescriptor['inputHash'],
            'path' => $futurePath,
            'sha256' => $futureSha,
        ];
        $this->writeRoot($locator, $root);

        self::assertSame(
            ProjectionBuildSetRecoveryStatus::UNSUPPORTED,
            new ProjectionBuildSetReader($locator)->readStatus('build-1', $this->expectedInputHashes())->status,
        );
    }

    public function testCompoundStatusUsesFixedPrecedenceRegardlessOfProjectionPosition(): void
    {
        $cases = [
            static function (self $test, ProjectionBuildSetLocator $locator): array {
                $command = glob($locator->directory() . '/command-*.json');
                self::assertIsArray($command);
                self::assertCount(1, $command);
                file_put_contents($command[0], "tampered\n");
                $expected = $test->expectedInputHashes();
                $expected['schedule'] = str_repeat('0', 64);

                return [$expected, ProjectionBuildSetRecoveryStatus::MALFORMED];
            },
            static function (self $test, ProjectionBuildSetLocator $locator): array {
                $schedule = glob($locator->directory() . '/schedule-*.json');
                self::assertIsArray($schedule);
                self::assertCount(1, $schedule);
                file_put_contents($schedule[0], "tampered\n");
                $expected = $test->expectedInputHashes();
                $expected['command'] = str_repeat('0', 64);

                return [$expected, ProjectionBuildSetRecoveryStatus::MALFORMED];
            },
            static function (self $test, ProjectionBuildSetLocator $locator): array {
                $route = glob($locator->directory() . '/route-*.json');
                self::assertIsArray($route);
                self::assertCount(1, $route);
                unlink($route[0]);
                $command = glob($locator->directory() . '/command-*.json');
                self::assertIsArray($command);
                self::assertCount(1, $command);
                file_put_contents($command[0], "tampered\n");
                $expected = $test->expectedInputHashes();
                $expected['command'] = str_repeat('0', 64);

                return [$expected, ProjectionBuildSetRecoveryStatus::ABSENT];
            },
            static function (self $test, ProjectionBuildSetLocator $locator): array {
                $command = glob($locator->directory() . '/command-*.json');
                self::assertIsArray($command);
                self::assertCount(1, $command);
                file_put_contents($command[0], "tampered\n");
                $test->makeUnsupportedRoute($locator);

                return [$test->expectedInputHashes(), ProjectionBuildSetRecoveryStatus::MALFORMED];
            },
            static function (self $test, ProjectionBuildSetLocator $locator): array {
                $test->makeUnsupportedRoute($locator);

                return [$test->expectedInputHashes(), ProjectionBuildSetRecoveryStatus::UNSUPPORTED];
            },
            static function (self $test, ProjectionBuildSetLocator $locator): array {
                $expected = $test->expectedInputHashes();
                $expected['command'] = str_repeat('0', 64);

                return [$expected, ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH];
            },
        ];

        foreach ($cases as $case) {
            $locator = $this->publish();
            [$expected, $status] = $case($this, $locator);
            $actual = new ProjectionBuildSetReader($locator)->readStatus('build-2', $expected);
            self::assertSame($status, $actual->status);
        }
    }

    public function testOperationalReadFailureRemainsAFileSystemException(): void
    {
        $locator = $this->publish();
        $rootPath = $locator->rootPath();
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use ($rootPath): void {
            if ($event !== 'fopen:rb:' . $rootPath) {
                return;
            }
            rename($rootPath, $rootPath . '.moved');
            mkdir($rootPath);
        });

        $this->expectException(ProjectionBuildSetFilesystemException::class);
        new ProjectionBuildSetReader($locator, filesystem: $filesystem)->readStatus(
            'build-1',
            $this->expectedInputHashes(),
        );
    }

    /** @return array<string, string> */
    private function expectedInputHashes(): array
    {
        $codec = new ProjectionBuildSetCodec();
        $root = $codec->root('build-1', $this->inputs());

        return array_map(static fn(object $projection): string => $projection->inputHash, $root['projections']);
    }

    /** @return array<string, string> */
    private function emptyExpectedHashes(): array
    {
        return [
            'command' => str_repeat('0', 64),
            'route' => str_repeat('0', 64),
            'schedule' => str_repeat('0', 64),
        ];
    }

    private function publish(): ProjectionBuildSetLocator
    {
        $locator = $this->locator();
        $this->publishAt($locator);

        return $locator;
    }

    private function publishAt(ProjectionBuildSetLocator $locator): void
    {
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
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

    private function makeUnsupportedRoute(ProjectionBuildSetLocator $locator): void
    {
        $root = $this->root($locator);
        $routeDescriptor = $root['projections']['route'];
        $oldPath = $routeDescriptor['path'];
        $bytes = file_get_contents($locator->path($oldPath));
        self::assertIsString($bytes);
        $document = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        $document['kind'] = 'blackops_future_projection';
        $codec = new ProjectionBuildSetCodec();
        $futureBytes = $codec->canonicalJson($document) . "\n";
        $futureSha = hash('sha256', $futureBytes);
        $futurePath = 'route-' . $futureSha . '.json';
        file_put_contents($locator->path($futurePath), $futureBytes);
        unlink($locator->path($oldPath));
        $root['projections']['route'] = [
            'kind' => 'blackops_route_projection',
            'inputHash' => $routeDescriptor['inputHash'],
            'path' => $futurePath,
            'sha256' => $futureSha,
        ];
        $this->writeRoot($locator, $root);
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function inputs(): array
    {
        return [
            'command' => [[
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List operations',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => [[
                'method' => 'GET',
                'path' => '/operations/{operationId}',
                'strategy' => 'inline',
                'operationType' => 'operation.read',
                'origin' => 'operation_manifest',
            ]],
            'schedule' => [[
                'name' => 'operation.reconcile',
                'operationType' => 'operation.reconcile',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ]],
        ];
    }

    private function locator(): ProjectionBuildSetLocator
    {
        $directory = sys_get_temp_dir() . '/blackops-projection-build-set-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));
        $this->directories[] = $directory;

        return new ProjectionBuildSetLocator($directory);
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }
            chmod($path, 0o600);
            unlink($path);
        }
        rmdir($directory);
    }
}
