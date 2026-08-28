<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\BuildSet;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetAbsentException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ProjectionBuildSetPublisherTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function testPublishesCompleteSetAndReaderValidatesAllThreeFiles(): void
    {
        $locator = $this->locator();
        $publisher = new ProjectionBuildSetPublisher($locator);
        $result = $publisher->publish('build-1', $this->inputs());

        self::assertTrue($result->published);
        self::assertTrue($result->cleanupComplete);
        $read = new ProjectionBuildSetReader($locator)->read('route');
        self::assertSame('build-1', $read->applicationBuildId);
        self::assertCount(3, $read->projections);
        self::assertSame('/operations/{operationId}', $read->records('route')[0]['path']);
    }

    public function testReaderDoesNotBootstrapMissingLock(): void
    {
        $locator = $this->locator();

        $this->expectException(ProjectionBuildSetAbsentException::class);
        new ProjectionBuildSetReader($locator)->read();
    }

    public function testReaderKeepsSharedLockForCallbackLifetime(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $reader = new ProjectionBuildSetReader($locator);

        $reader->withReadLock(function () use ($locator): void {
            $handle = fopen($locator->lockPath(), 'rb');
            self::assertIsResource($handle);
            try {
                self::assertFalse(flock($handle, LOCK_EX | LOCK_NB));
            } finally {
                fclose($handle);
            }
        });
    }

    public function testReaderRejectsNonEmptyLock(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        file_put_contents($locator->lockPath(), 'unexpected');

        $this->expectException(ProjectionBuildSetFilesystemException::class);
        new ProjectionBuildSetReader($locator)->read();
    }

    public function testBoundedDirectoryScanStopsAfterTheMaxPlusOneNonDotEntry(): void
    {
        $locator = $this->locator();
        foreach (['first', 'second', 'third'] as $entry) {
            file_put_contents($locator->path($entry), $entry);
        }
        $events = [];
        $sentinel = $locator->path('sentinel');
        $nonDotReads = 0;
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (
            &$events,
            &$nonDotReads,
            $sentinel,
        ): void {
            $events[] = $event;
            if (!str_starts_with($event, 'readdir:') || in_array($event, ['readdir:.', 'readdir:..'], true)) {
                return;
            }
            $nonDotReads++;
            if ($nonDotReads === 3) {
                file_put_contents($sentinel, 'sentinel');
            }
        });

        $entries = $filesystem->entries($locator->directory(), 2);

        self::assertIsArray($entries);
        self::assertCount(3, $entries);
        self::assertSame(3, $nonDotReads);
        self::assertFileExists($sentinel);
        self::assertNotContains('readdir:sentinel', $events);
    }

    public function testPublisherUsesBootstrap255AndUnderLock256ScanLimits(): void
    {
        $locator = $this->locator();
        $limits = [];
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (&$limits): void {
            if (str_starts_with($event, 'scan-limit:')) {
                $limits[] = $event;
            }
        });

        new ProjectionBuildSetPublisher(locator: $locator, filesystem: $filesystem)->publish(
            'build-1',
            $this->inputs(),
        );

        self::assertSame(['scan-limit:255', 'scan-limit:256'], $limits);
    }

    public function testPartialCleanupSyncsDirectoryAndReturnsDeletedPrefix(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $first = '.blackops-projection-build-set-command-' . str_repeat('a', 32) . '.tmp';
        $second = '.blackops-projection-build-set-command-' . str_repeat('b', 32) . '.tmp';
        file_put_contents($locator->path($first), 'stale');
        file_put_contents($locator->path($second), 'stale');
        $events = [];
        $cleanupArmed = false;
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (
            &$events,
            &$cleanupArmed,
            $locator,
            $second,
        ): void {
            $events[] = $event;
            if ($cleanupArmed && $event === 'unlink:' . $locator->path($second)) {
                unlink($locator->path($second));
                mkdir($locator->path($second));
            }
        });
        $checkpoint = static function (string $event) use (&$cleanupArmed): void {
            if ($event === 'root-committed') {
                $cleanupArmed = true;
            }
        };

        $result = new ProjectionBuildSetPublisher(
            locator: $locator,
            filesystem: $filesystem,
            checkpoint: $checkpoint,
        )->publish('build-2', $this->inputs());

        self::assertTrue($result->published);
        self::assertFalse($result->cleanupComplete);
        self::assertSame([$first], $result->deleted);
        self::assertSame('build-2', new ProjectionBuildSetReader($locator)->read()->applicationBuildId);
        $firstUnlink = array_search('unlink:' . $locator->path($first), $events, true);
        $secondUnlink = array_search('unlink:' . $locator->path($second), $events, true);
        $directorySyncs = array_keys(array_filter(
            $events,
            static fn(string $event): bool => $event === 'open-directory:' . $locator->directory(),
        ));
        self::assertIsInt($firstUnlink);
        self::assertIsInt($secondUnlink);
        self::assertNotEmpty($directorySyncs);
        self::assertLessThan($secondUnlink, $firstUnlink);
        self::assertGreaterThan($secondUnlink, end($directorySyncs));
    }

    public function testPublisherReuseResetsDeletedPrefixBeforeRootCommitFailure(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $first = '.blackops-projection-build-set-command-' . str_repeat('c', 32) . '.tmp';
        $second = '.blackops-projection-build-set-command-' . str_repeat('d', 32) . '.tmp';
        file_put_contents($locator->path($first), 'stale');
        file_put_contents($locator->path($second), 'stale');
        $publication = 0;
        $cleanupArmed = false;
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (
            &$cleanupArmed,
            &$publication,
            $locator,
            $second,
        ): void {
            if ($publication === 1 && $cleanupArmed && $event === 'unlink:' . $locator->path($second)) {
                unlink($locator->path($second));
                mkdir($locator->path($second));
            }
        });
        $checkpoint = static function (string $event) use (&$cleanupArmed, &$publication): void {
            if ($event === 'before-first-temporary') {
                $publication++;
            }
            if ($event === 'root-committed' && $publication === 1) {
                $cleanupArmed = true;
            }
            if ($event === 'root-committed' && $publication === 2) {
                throw new RuntimeException('simulated root-committed failure');
            }
        };
        $publisher = new ProjectionBuildSetPublisher(
            locator: $locator,
            filesystem: $filesystem,
            checkpoint: $checkpoint,
        );

        $firstResult = $publisher->publish('build-2', $this->inputs());
        self::assertSame([$first], $firstResult->deleted);
        self::assertFalse($firstResult->cleanupComplete);
        rmdir($locator->path($second));
        file_put_contents($locator->path($second), 'stale');

        $secondResult = $publisher->publish('build-3', $this->inputs());

        self::assertTrue($secondResult->published);
        self::assertFalse($secondResult->cleanupComplete);
        self::assertSame([], $secondResult->deleted);
        self::assertSame('build-3', new ProjectionBuildSetReader($locator)->read()->applicationBuildId);
    }

    public function testPostCommitCleanupFailureReturnsTypedPartialResult(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        file_put_contents(
            $locator->path('.blackops-projection-build-set-command-' . str_repeat('a', 32) . '.tmp'),
            'stale',
        );
        $publisher =
            new ProjectionBuildSetPublisher(locator: $locator, checkpoint: static function (string $event): void {
                if ($event === 'before-gc') {
                    throw new RuntimeException('simulated cleanup failure');
                }
            });

        $result = $publisher->publish('build-2', $this->inputs());

        self::assertTrue($result->published);
        self::assertFalse($result->cleanupComplete);
        self::assertSame('build-2', new ProjectionBuildSetReader($locator)->read()->applicationBuildId);
    }

    public function testExistingGenerationMismatchFailsBeforeReplacement(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $command = glob($locator->directory() . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        file_put_contents($command[0], "tampered\n");

        $this->expectException(ProjectionBuildSetException::class);
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
    }

    public function testNonSelectedGenerationMismatchInvalidatesTheCompleteSet(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $route = glob($locator->directory() . '/route-*.json');
        self::assertIsArray($route);
        self::assertCount(1, $route);
        file_put_contents($route[0], "tampered\n");

        $this->expectException(ProjectionBuildSetException::class);
        new ProjectionBuildSetReader($locator)->read('command');
    }

    public function testMixedBuildGenerationIsRejectedEvenWhenRootSelfHashIsValid(): void
    {
        $locator = $this->locator();
        $codec = new ProjectionBuildSetCodec();
        $first = $codec->root('build-1', $this->inputs());
        $second = $codec->root('build-2', $this->inputs());
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $route = $second['projections']['route'];
        file_put_contents($locator->path($route->path), $route->bytes);
        $mixed = $first['root'];
        $mixed['projections']['route'] = [
            'kind' => $route->kind,
            'inputHash' => $route->inputHash,
            'path' => $route->path,
            'sha256' => $route->sha256,
        ];
        $withoutHash = $mixed;
        unset($withoutHash['buildSetHash']);
        $mixed['buildSetHash'] = hash(
            'sha256',
            $codec->encodeString('blackops.projection-build-set.v1')
                . $codec->encodeBytes($codec->canonicalJson($withoutHash)),
        );
        file_put_contents($locator->rootPath(), $codec->canonicalJson($mixed) . "\n");

        $this->expectException(ProjectionBuildSetValidationException::class);
        new ProjectionBuildSetReader($locator)->read();
    }

    public function testFailureBeforeRootRenameLeavesOldRootCurrent(): void
    {
        $locator = $this->locator();
        $first = new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $publisher =
            new ProjectionBuildSetPublisher(locator: $locator, checkpoint: static function (string $event): void {
                if ($event === 'projection-directory-synced') {
                    throw new RuntimeException('simulated pre-commit failure');
                }
            });

        $this->expectException(RuntimeException::class);
        try {
            $publisher->publish('build-2', $this->inputs());
        } finally {
            self::assertSame($first->buildSetHash, new ProjectionBuildSetReader($locator)->read()->buildSetHash);
        }
    }

    public function testPublicationCheckpointsKeepRootLastAndGcAfterCommit(): void
    {
        $locator = $this->locator();
        $checkpoints = [];
        new ProjectionBuildSetPublisher(locator: $locator, checkpoint: static function (string $event) use (
            &$checkpoints,
        ): void {
            $checkpoints[] = $event;
        })->publish('build-1', $this->inputs());

        self::assertSame(
            [
                'after-exclusive-lock',
                'before-first-temporary',
                'projection-temporary-written:command',
                'projection-generation-renamed:command',
                'projection-temporary-written:route',
                'projection-generation-renamed:route',
                'projection-temporary-written:schedule',
                'projection-generation-renamed:schedule',
                'projection-directory-synced',
                'root-temporary-written',
                'root-committed',
                'before-gc',
            ],
            $checkpoints,
        );
    }

    public function testTooManyCleanupCandidatesFailBeforeGenerationMutation(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        for ($index = 0; $index < 33; $index++) {
            file_put_contents(
                $locator->path(
                    '.blackops-projection-build-set-command-'
                    . str_pad((string) $index, 32, 'a', STR_PAD_LEFT)
                    . '.tmp',
                ),
                'stale',
            );
        }

        $this->expectException(ProjectionBuildSetException::class);
        try {
            new ProjectionBuildSetPublisher($locator)->publish('build-2', $this->inputs());
        } finally {
            self::assertSame('build-1', new ProjectionBuildSetReader($locator)->read()->applicationBuildId);
        }
    }

    public function testThirtyTwoCleanupCandidatesAreWithinTheBound(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $stale = [];
        for ($index = 0; $index < 29; $index++) {
            $name =
                '.blackops-projection-build-set-command-' . str_pad((string) $index, 32, 'c', STR_PAD_LEFT) . '.tmp';
            file_put_contents($locator->path($name), 'stale');
            $stale[] = $name;
        }

        $result = new ProjectionBuildSetPublisher($locator)->publish('build-2', $this->inputs());

        self::assertTrue($result->cleanupComplete);
        self::assertCount(32, $result->deleted);
        foreach ($stale as $name) {
            self::assertFileDoesNotExist($locator->path($name));
        }
    }

    public function testDirectoryEntryBoundRejects256ReplacementAnd257Entries(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        for ($index = 0; $index < 251; $index++) {
            file_put_contents($locator->path('unknown-' . $index), 'keep');
        }

        try {
            new ProjectionBuildSetPublisher($locator)->publish('build-2', $this->inputs());
            self::fail('Expected the root replacement slot to be reserved.');
        } catch (ProjectionBuildSetException) {
            self::assertSame('build-1', new ProjectionBuildSetReader($locator)->read()->applicationBuildId);
        }

        file_put_contents($locator->path('unknown-251'), 'keep');
        try {
            new ProjectionBuildSetPublisher($locator)->publish('build-2', $this->inputs());
            self::fail('Expected the directory entry bound to reject the sentinel entry.');
        } catch (ProjectionBuildSetException) {
            self::assertSame('build-1', new ProjectionBuildSetReader($locator)->read()->applicationBuildId);
        }
    }

    public function testMissingLockReserves255EntriesButRejects256BeforeMutation(): void
    {
        $first = $this->locator();
        for ($index = 0; $index < 255; $index++) {
            file_put_contents($first->path('unknown-' . $index), 'keep');
        }
        try {
            new ProjectionBuildSetPublisher($first)->publish('build-1', $this->inputs());
            self::fail('Expected complete-set projected capacity to reject the reserved lock slot.');
        } catch (ProjectionBuildSetException) {
            self::assertFileExists($first->lockPath());
            self::assertFileDoesNotExist($first->rootPath());
            self::assertCount(256, array_diff(scandir($first->directory()) ?: [], ['.', '..']));
        }

        $second = $this->locator();
        for ($index = 0; $index < 256; $index++) {
            file_put_contents($second->path('unknown-' . $index), 'keep');
        }
        try {
            new ProjectionBuildSetPublisher($second)->publish('build-1', $this->inputs());
            self::fail('Expected the missing-lock bootstrap slot to reject 256 entries.');
        } catch (ProjectionBuildSetException) {
            self::assertFileDoesNotExist($second->lockPath());
            self::assertFileDoesNotExist($second->rootPath());
            self::assertCount(256, array_diff(scandir($second->directory()) ?: [], ['.', '..']));
        }
    }

    public function testRootTemporarySlotIsReservedWhenRootIsAbsent(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        self::assertTrue(unlink($locator->rootPath()));
        for ($index = 0; $index < 252; $index++) {
            file_put_contents($locator->path('unknown-' . $index), 'keep');
        }

        try {
            new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
            self::fail('Expected the absent-root temporary slot to reject 256 entries.');
        } catch (ProjectionBuildSetException) {
            self::assertFileDoesNotExist($locator->rootPath());
        }
    }

    public function testLocatorRejectsAPathEscape(): void
    {
        $locator = $this->locator();

        $this->expectException(ProjectionBuildSetValidationException::class);
        $locator->path('../outside');
    }

    public function testRecognizedGenerationSymlinkFailsClosed(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $command = glob($locator->directory() . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        $target = $locator->directory() . '/outside.txt';
        file_put_contents($target, 'outside');
        $generation = $command[0];
        self::assertTrue(unlink($generation));
        self::assertTrue(symlink($target, $generation));

        $this->expectException(ProjectionBuildSetException::class);
        new ProjectionBuildSetReader($locator)->read();
    }

    public function testRecognizedGenerationSpecialFileFailsBeforeOpen(): void
    {
        if (!function_exists('posix_mkfifo')) {
            self::markTestSkipped('The POSIX FIFO fixture is unavailable.');
        }

        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $command = glob($locator->directory() . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        self::assertTrue(unlink($command[0]));
        self::assertTrue(posix_mkfifo($command[0], 0600));

        $this->expectException(ProjectionBuildSetFilesystemException::class);
        new ProjectionBuildSetReader($locator)->read();
    }

    public function testOpenHandleReplacementFailsClosedBeforeIomutation(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $command = glob($locator->directory() . '/command-*.json');
        self::assertIsArray($command);
        self::assertCount(1, $command);
        $commandPath = $command[0];
        $armed = false;
        $replaced = false;
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (
            &$armed,
            &$replaced,
            $commandPath,
        ): void {
            if ($event === 'fopen:rb:' . $commandPath) {
                $armed = true;
                return;
            }
            if ($armed && !$replaced && $event === 'fstat') {
                $replaced = true;
                rename($commandPath, $commandPath . '.old');
                file_put_contents($commandPath, "replacement\n");
            }
        });

        $this->expectException(ProjectionBuildSetFilesystemException::class);
        try {
            new ProjectionBuildSetReader($locator, filesystem: $filesystem)->read();
        } finally {
            self::assertTrue($replaced);
        }
    }

    public function testLockReplacementAfterSharedAcquireFailsClosed(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $armed = false;
        $replaced = false;
        $lockPath = $locator->lockPath();
        $filesystem = new ProjectionBuildSetFilesystem(static function (string $event) use (
            &$armed,
            &$replaced,
            $lockPath,
        ): void {
            if ($event === 'flock:1') {
                $armed = true;
                return;
            }
            if ($armed && !$replaced && $event === 'fstat') {
                $replaced = true;
                rename($lockPath, $lockPath . '.old');
                file_put_contents($lockPath, '');
            }
        });

        $this->expectException(ProjectionBuildSetFilesystemException::class);
        try {
            new ProjectionBuildSetReader($locator, filesystem: $filesystem)->read();
        } finally {
            self::assertTrue($replaced);
        }
    }

    public function testBoundedGarbageCollectionRemovesRecognizedCandidatesOnly(): void
    {
        $locator = $this->locator();
        new ProjectionBuildSetPublisher($locator)->publish('build-1', $this->inputs());
        $stale = [];
        for ($index = 0; $index < 2; $index++) {
            $name = '.blackops-projection-build-set-route-' . str_pad((string) $index, 32, 'b', STR_PAD_LEFT) . '.tmp';
            file_put_contents($locator->path($name), 'stale');
            $stale[] = $name;
        }
        $unknown = 'unknown-entry';
        file_put_contents($locator->path($unknown), 'keep');

        $result = new ProjectionBuildSetPublisher($locator)->publish('build-2', $this->inputs());

        self::assertTrue($result->cleanupComplete);
        foreach ($stale as $name) {
            self::assertFileDoesNotExist($locator->path($name));
        }
        self::assertFileExists($locator->path($unknown));
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
            unlink($path);
        }
        rmdir($directory);
    }
}
