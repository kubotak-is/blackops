<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ProjectionBuildSetPublisher
{
    public const MAX_DIRECTORY_ENTRIES = 256;
    public const MAX_GC_DELETIONS = 32;
    public const MAX_TEMP_ATTEMPTS = 16;

    /** @var callable(): string */
    private $nonceGenerator;

    /** @var callable(string): void|null */
    private $checkpoint;

    public function __construct(
        private readonly ProjectionBuildSetLocator $locator,
        private readonly ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
        private readonly ProjectionBuildSetFilesystem $filesystem = new ProjectionBuildSetFilesystem(),
        ?callable $nonceGenerator = null,
        ?callable $checkpoint = null,
    ) {
        $this->nonceGenerator = static function () use ($nonceGenerator): string {
            if ($nonceGenerator === null) {
                return random_bytes(16);
            }
            $nonce = $nonceGenerator();
            if (!is_string($nonce)) {
                throw new ProjectionBuildSetFilesystemException('Projection temporary nonce must be a string.');
            }

            return $nonce;
        };
        $this->checkpoint = $checkpoint;
    }

    /**
     * @param array<string, ProjectionBuildSetProjection|array<int, array<string, mixed>>> $projections
     * @mago-expect lint:halstead
     */
    public function publish(string $applicationBuildId, array $projections): ProjectionBuildSetPublicationResult
    {
        $this->deletedCandidates = [];
        $package = $this->codec->root($applicationBuildId, $projections);
        $rootBytes = $package['bytes'];
        /** @var array<string, ProjectionBuildSetProjection> $normalized */
        $normalized = $package['projections'];
        $newPaths = array_values(array_map(
            static fn(ProjectionBuildSetProjection $projection): string => $projection->path,
            $normalized,
        ));
        $lock = null;
        $locked = false;
        $temporaryPaths = [];
        $published = false;

        try {
            $initialEntries = $this->entries(self::MAX_DIRECTORY_ENTRIES - 1);
            $lock = $this->prepareLock($initialEntries);
            if (!is_resource($lock)) {
                throw new ProjectionBuildSetFilesystemException('Projection build-set lock could not be prepared.');
            }
            if (!$this->filesystem->flock($lock, LOCK_EX)) {
                throw new ProjectionBuildSetFilesystemException(
                    'Projection build-set exclusive lock could not be acquired.',
                );
            }
            $locked = true;
            $this->verifyHeldLock($lock);
            $this->checkpoint('after-exclusive-lock');

            $entries = $this->entries(self::MAX_DIRECTORY_ENTRIES);
            $this->entryBound($entries);
            $rootExists = $this->pathExists(ProjectionBuildSetLocator::ROOT_NAME);
            $this->validateCurrentRootIfPresent();
            $this->validateReservedEntries($entries);
            $absentTargets = 0;
            foreach ($normalized as $projection) {
                $path = $projection->path;
                if (!$this->pathExists($path)) {
                    $absentTargets++;
                    continue;
                }
                $this->verifyExistingGeneration($projection);
            }
            $projectedCommitted = count($entries) + $absentTargets + ($rootExists ? 0 : 1);
            if (
                $projectedCommitted > self::MAX_DIRECTORY_ENTRIES
                || ($projectedCommitted + ($rootExists ? 1 : 0)) > self::MAX_DIRECTORY_ENTRIES
            ) {
                throw new ProjectionBuildSetFilesystemException(
                    'Projection build-set directory committed bound is exceeded.',
                );
            }
            $candidates = $this->gcCandidates($entries, $newPaths);
            if (count($candidates) > self::MAX_GC_DELETIONS) {
                throw new ProjectionBuildSetFilesystemException('Projection build-set cleanup bound is exceeded.');
            }
            $this->checkpoint('before-first-temporary');

            foreach ($normalized as $key => $projection) {
                if ($this->pathExists($projection->path)) {
                    continue;
                }
                [$temporary, $handle] = $this->createTemporary($this->prefixForKey($key));
                $temporaryPaths[] = $temporary;
                try {
                    $this->filesystem->writeAll($handle, $projection->bytes);
                    if (!$this->filesystem->fflush($handle) || !$this->filesystem->fsync($handle)) {
                        throw new ProjectionBuildSetFilesystemException('Projection generation fsync failed.');
                    }
                } finally {
                    $this->filesystem->fclose($handle);
                }
                $this->checkpoint('projection-temporary-written:' . $key);
                if (
                    $this->pathExists($projection->path)
                    || !$this->filesystem->rename(
                        $this->locator->path($temporary),
                        $this->locator->path($projection->path),
                    )
                ) {
                    throw new ProjectionBuildSetFilesystemException(
                        'Projection generation could not be published immutably.',
                    );
                }
                array_pop($temporaryPaths);
                $this->checkpoint('projection-generation-renamed:' . $key);
            }
            $this->syncDirectory();
            $this->checkpoint('projection-directory-synced');

            [$rootTemporary, $rootHandle] = $this->createTemporary('root');
            $temporaryPaths[] = $rootTemporary;
            try {
                $this->filesystem->writeAll($rootHandle, $rootBytes);
                if (!$this->filesystem->fflush($rootHandle) || !$this->filesystem->fsync($rootHandle)) {
                    throw new ProjectionBuildSetFilesystemException('Projection root fsync failed.');
                }
            } finally {
                $this->filesystem->fclose($rootHandle);
            }
            $this->checkpoint('root-temporary-written');
            if (!$this->filesystem->rename($this->locator->path($rootTemporary), $this->locator->rootPath())) {
                throw new ProjectionBuildSetFilesystemException(
                    'Projection build-set root could not be atomically replaced.',
                );
            }
            array_pop($temporaryPaths);
            $this->syncDirectory();
            $published = true;
            $this->checkpoint('root-committed');

            $cleanupComplete = $this->cleanup($candidates);

            return new ProjectionBuildSetPublicationResult(
                published: true,
                cleanupComplete: $cleanupComplete,
                applicationBuildId: $applicationBuildId,
                buildSetHash: $package['buildSetHash'],
                deleted: $this->deletedCandidates,
            );
        } catch (Throwable $exception) {
            foreach ($temporaryPaths as $temporary) {
                if (!$this->pathExists($temporary)) {
                    continue;
                }
                $this->filesystem->unlink($this->locator->path($temporary));
            }
            if ($published) {
                return new ProjectionBuildSetPublicationResult(
                    published: true,
                    cleanupComplete: false,
                    applicationBuildId: $applicationBuildId,
                    buildSetHash: $package['buildSetHash'],
                    deleted: $this->deletedCandidates,
                );
            }
            throw $exception;
        } finally {
            if ($locked && is_resource($lock)) {
                $this->filesystem->flock($lock, LOCK_UN);
            }
            if (is_resource($lock)) {
                $this->filesystem->fclose($lock);
            }
        }
    }

    /** @var list<string> */
    private array $deletedCandidates = [];

    /** @param list<string> $initialEntries
     * @return resource
     */
    private function prepareLock(array $initialEntries): mixed
    {
        $lockName = ProjectionBuildSetLocator::LOCK_NAME;
        $lockStat = $this->filesystem->lstat($this->locator->lockPath());
        if ($lockStat === false) {
            if (count($initialEntries) >= self::MAX_DIRECTORY_ENTRIES) {
                throw new ProjectionBuildSetFilesystemException(
                    'Projection build-set bootstrap lock slot is unavailable.',
                );
            }
            $handle = $this->filesystem->fopen($this->locator->lockPath(), 'x+b');
            if (is_resource($handle)) {
                try {
                    $this->verifyHeldLock($handle);
                    if (!$this->filesystem->fflush($handle) || !$this->filesystem->fsync($handle)) {
                        throw new ProjectionBuildSetFilesystemException(
                            'Projection build-set bootstrap lock fsync failed.',
                        );
                    }

                    return $handle;
                } catch (Throwable $exception) {
                    $this->filesystem->fclose($handle);
                    throw $exception;
                }
            }
            $lockStat = $this->filesystem->lstat($this->locator->lockPath());
            if ($lockStat === false) {
                throw new ProjectionBuildSetFilesystemException(
                    'Projection build-set bootstrap lock could not be created.',
                );
            }
        }
        if (!$this->filesystem->regular($lockStat)) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set lock is not regular.');
        }
        $handle = $this->filesystem->openVerified($this->locator, $lockName, 'rb');
        if (!is_resource($handle)) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set lock could not be opened.');
        }
        $stat = $this->filesystem->fstat($handle);
        if ($stat === false || ($stat['size'] ?? 0) !== 0) {
            $this->filesystem->fclose($handle);
            throw new ProjectionBuildSetFilesystemException('Projection build-set lock must remain empty.');
        }

        return $handle;
    }

    /** @return list<string> */
    private function entries(int $maximum): array
    {
        $entries = $this->filesystem->entries($this->locator->directory(), $maximum);
        if ($entries === false) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set directory could not be scanned.');
        }

        return $entries;
    }

    /** @param list<string> $entries */
    private function entryBound(array $entries): void
    {
        if (count($entries) > self::MAX_DIRECTORY_ENTRIES) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set directory entry bound is exceeded.');
        }
    }

    private function pathExists(string $basename): bool
    {
        return $this->filesystem->lstat($this->locator->path($basename)) !== false;
    }

    private function validateCurrentRootIfPresent(): void
    {
        $root = $this->filesystem->openVerified(
            $this->locator,
            ProjectionBuildSetLocator::ROOT_NAME,
            'rb',
            absentIsAllowed: true,
        );
        if ($root === null) {
            return;
        }
        if (!is_resource($root)) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set root could not be opened.');
        }
        try {
            $bytes = $this->filesystem->readBounded($root, ProjectionBuildSetCodec::ROOT_MAX_BYTES);
        } finally {
            $this->filesystem->fclose($root);
        }
        $document = $this->codec->decodeRoot($bytes);
        foreach ($this->codec->kinds() as $key => $kind) {
            $descriptor = $document['projections'][$key];
            $handle = $this->filesystem->openVerified($this->locator, $descriptor['path'], 'rb');
            if (!is_resource($handle)) {
                throw new ProjectionBuildSetAbsentException('Existing projection generation is absent.');
            }
            try {
                $projectionBytes = $this->filesystem->readBounded(
                    $handle,
                    ProjectionBuildSetCodec::PROJECTION_MAX_BYTES,
                );
            } finally {
                $this->filesystem->fclose($handle);
            }
            if (hash('sha256', $projectionBytes) !== $descriptor['sha256']) {
                throw new ProjectionBuildSetValidationException('Existing projection generation digest is invalid.');
            }
            $projection = $this->codec->decodeProjection(
                bytes: $projectionBytes,
                expectedKind: $kind,
                expectedApplicationBuildId: $document['applicationBuildId'],
                expectedInputHash: $descriptor['inputHash'],
            );
            if ($projection->path !== $descriptor['path']) {
                throw new ProjectionBuildSetValidationException('Existing projection generation path is invalid.');
            }
        }
    }

    /** @param list<string> $entries */
    private function validateReservedEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            if (!$this->isGeneration($entry) && !$this->isTemporary($entry)) {
                continue;
            }
            $stat = $this->filesystem->lstat($this->locator->path($entry));
            if ($stat === false || !$this->filesystem->regular($stat)) {
                throw new ProjectionBuildSetFilesystemException('Reserved projection entry is not regular: ' . $entry);
            }
        }
    }

    private function verifyExistingGeneration(ProjectionBuildSetProjection $projection): void
    {
        $handle = $this->filesystem->openVerified($this->locator, $projection->path, 'rb');
        if (!is_resource($handle)) {
            throw new ProjectionBuildSetFilesystemException('Existing generation target could not be opened.');
        }
        try {
            $bytes = $this->filesystem->readBounded($handle, ProjectionBuildSetCodec::PROJECTION_MAX_BYTES);
        } finally {
            $this->filesystem->fclose($handle);
        }
        if ($bytes !== $projection->bytes || hash('sha256', $bytes) !== $projection->sha256) {
            throw new ProjectionBuildSetValidationException(
                'Existing generation target does not match immutable bytes.',
            );
        }
    }

    /** @param list<string> $entries
     * @param list<string> $newPaths
     * @return list<string>
     */
    private function gcCandidates(array $entries, array $newPaths): array
    {
        $candidates = [];
        foreach ($entries as $entry) {
            if (
                $entry === ProjectionBuildSetLocator::ROOT_NAME
                || $entry === ProjectionBuildSetLocator::LOCK_NAME
                || in_array(needle: $entry, haystack: $newPaths, strict: true)
            ) {
                continue;
            }
            if ($this->isGeneration($entry) || $this->isTemporary($entry)) {
                $candidates[] = $entry;
            }
        }
        usort($candidates, strcmp(...));

        return $candidates;
    }

    /** @return array{0: string, 1: resource} */
    private function createTemporary(string $role): array
    {
        for ($attempt = 0; $attempt < self::MAX_TEMP_ATTEMPTS; $attempt++) {
            $nonce = ($this->nonceGenerator)();
            if (strlen($nonce) !== 16) {
                throw new ProjectionBuildSetFilesystemException('Projection temporary nonce must be exactly 16 bytes.');
            }
            $name = '.blackops-projection-build-set-' . $role . '-' . bin2hex($nonce) . '.tmp';
            $handle = $this->filesystem->fopen($this->locator->path($name), 'x+b');
            if (is_resource($handle)) {
                $this->verifyHandlePath($handle, $name);

                return [$name, $handle];
            }
        }

        throw new ProjectionBuildSetFilesystemException('Projection temporary namespace is exhausted.');
    }

    private function syncDirectory(): void
    {
        $handle = $this->filesystem->openDirectory($this->locator->directory());
        if (!is_resource($handle)) {
            throw new ProjectionBuildSetFilesystemException(
                'Projection build-set directory could not be opened for fsync.',
            );
        }
        try {
            if (!$this->filesystem->fsync($handle)) {
                throw new ProjectionBuildSetFilesystemException('Projection build-set directory fsync failed.');
            }
        } finally {
            $this->filesystem->fclose($handle);
        }
    }

    /** @param list<string> $candidates */
    private function cleanup(array $candidates): bool
    {
        $this->checkpoint('before-gc');
        $this->deletedCandidates = [];
        foreach ($candidates as $candidate) {
            $stat = $this->filesystem->lstat($this->locator->path($candidate));
            if ($stat === false) {
                continue;
            }
            if (!$this->filesystem->regular($stat) || !$this->filesystem->unlink($this->locator->path($candidate))) {
                try {
                    $this->syncDirectory();
                } catch (Throwable) {
                    return false;
                }
                return false;
            }
            $this->deletedCandidates[] = $candidate;
        }
        try {
            $this->syncDirectory();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /** @param resource $lock */
    private function verifyHeldLock(mixed $lock): void
    {
        $stat = $this->filesystem->fstat($lock);
        $pathStat = $this->filesystem->lstat($this->locator->lockPath());
        if ($stat === false || $pathStat === false || !$this->filesystem->sameIdentity($stat, $pathStat)) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set lock changed while held.');
        }
    }

    /** @param resource $handle */
    private function verifyHandlePath(mixed $handle, string $basename): void
    {
        $stat = $this->filesystem->fstat($handle);
        $pathStat = $this->filesystem->lstat($this->locator->path($basename));
        if ($stat === false || $pathStat === false || !$this->filesystem->sameIdentity($stat, $pathStat)) {
            $this->filesystem->fclose($handle);
            throw new ProjectionBuildSetFilesystemException('Projection build-set file changed while being created.');
        }
    }

    private function checkpoint(string $event): void
    {
        if ($this->checkpoint !== null) {
            ($this->checkpoint)($event);
        }
    }

    private function isGeneration(string $entry): bool
    {
        return preg_match('/^(?:command|route|schedule)-[0-9a-f]{64}\.json$/D', $entry) === 1;
    }

    private function isTemporary(string $entry): bool
    {
        return (
            preg_match('/^\.blackops-projection-build-set-(?:command|route|schedule|root)-[0-9a-f]{32}\.tmp$/D', $entry)
            === 1
        );
    }

    private function prefixForKey(string $key): string
    {
        if (!array_key_exists($key, $this->codec->kinds())) {
            throw new ProjectionBuildSetValidationException('Projection key is unsupported.');
        }

        return $key;
    }
}
