<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ProjectionBuildSetReader
{
    /** @var array<string, int> */
    private const STATUS_PRECEDENCE = [
        'absent' => 0,
        'malformed' => 1,
        'unsupported' => 2,
        'build_mismatch' => 3,
        'stale' => 4,
        'available' => 5,
    ];

    public function __construct(
        private readonly ProjectionBuildSetLocator $locator,
        private readonly ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
        private readonly ProjectionBuildSetFilesystem $filesystem = new ProjectionBuildSetFilesystem(),
    ) {}

    public function read(string $selectedKey = 'command'): ProjectionBuildSetReadResult
    {
        $this->key($selectedKey);

        return $this->withReadLock(
            static fn(ProjectionBuildSetReadResult $result): ProjectionBuildSetReadResult => $result,
        );
    }

    /**
     * Read the complete set and return its typed artifact status.
     *
     * @param array<string, mixed> $expectedInputHashes
     */
    public function readStatus(
        string $expectedApplicationBuildId,
        array $expectedInputHashes,
        string $selectedKey = 'command',
    ): ProjectionBuildSetRecoveryResult {
        return $this->withReadStatus(
            $expectedApplicationBuildId,
            $expectedInputHashes,
            static fn(ProjectionBuildSetRecoveryResult $result): ProjectionBuildSetRecoveryResult => $result,
            $selectedKey,
        );
    }

    /**
     * Read the published set using only the configured application identity.
     *
     * @template T
     * @param callable(ProjectionBuildSetRecoveryResult): T $callback
     * @return T
     */
    public function withPublishedStatus(
        string $expectedApplicationBuildId,
        callable $callback,
        string $selectedKey = 'command',
    ): mixed {
        $this->key($selectedKey);
        $this->expectedApplicationBuildId($expectedApplicationBuildId);

        return $this->withSharedLock(
            fn(): mixed => $callback($this->publishedStatusLocked($expectedApplicationBuildId)),
            static fn(): mixed => $callback(new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::ABSENT)),
        );
    }

    /**
     * Read the complete set and invoke the callback before releasing the shared
     * lock. This keeps selection, rendering, and output writes in one read
     * transaction.
     *
     * @template T
     * @param array<string, mixed> $expectedInputHashes
     * @param callable(ProjectionBuildSetRecoveryResult): T $callback
     * @return T
     */
    public function withReadStatus(
        string $expectedApplicationBuildId,
        array $expectedInputHashes,
        callable $callback,
        string $selectedKey = 'command',
    ): mixed {
        $this->key($selectedKey);
        $expectedInputHashes = $this->expectedIdentities($expectedApplicationBuildId, $expectedInputHashes);

        return $this->withSharedLock(
            fn(): mixed => $callback($this->readStatusLocked($expectedApplicationBuildId, $expectedInputHashes)),
            static fn(): mixed => $callback(new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::ABSENT)),
        );
    }

    /**
     * @template T
     * @param callable(ProjectionBuildSetReadResult): T $callback
     * @return T
     */
    public function withReadLock(callable $callback): mixed
    {
        return $this->withSharedLock(function () use ($callback): mixed {
            $result = $this->readCompleteLocked();

            return $callback($result);
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @param (callable(): T)|null $onAbsent
     * @return T
     */
    private function withSharedLock(callable $callback, ?callable $onAbsent = null): mixed
    {
        try {
            $lock = $this->filesystem->openVerified($this->locator, ProjectionBuildSetLocator::LOCK_NAME, 'rb');
            if (!is_resource($lock)) {
                throw new ProjectionBuildSetAbsentException('Projection build-set lock is absent.');
            }
        } catch (ProjectionBuildSetAbsentException $exception) {
            if ($onAbsent === null) {
                throw $exception;
            }

            return $onAbsent();
        }
        $locked = false;
        try {
            if (!$this->filesystem->flock($lock, LOCK_SH)) {
                throw new ProjectionBuildSetFilesystemException(
                    'Projection build-set shared lock could not be acquired.',
                );
            }
            $locked = true;
            $this->verifyHeldLock($lock);
            $lockStat = $this->filesystem->fstat($lock);
            if ($lockStat === false || ($lockStat['size'] ?? null) !== 0) {
                throw new ProjectionBuildSetFilesystemException('Projection build-set lock must remain empty.');
            }

            return $callback();
        } finally {
            if ($locked) {
                $this->filesystem->flock($lock, LOCK_UN);
            }
            $this->filesystem->fclose($lock);
        }
    }

    private function readCompleteLocked(): ProjectionBuildSetReadResult
    {
        $rootDocument = $this->readRootLocked();
        $applicationBuildId = $rootDocument['applicationBuildId'];
        $buildSetHash = $rootDocument['buildSetHash'];
        /** @var array<string, ProjectionBuildSetProjection> $projections */
        $projections = [];

        foreach ($this->codec->kinds() as $key => $kind) {
            $descriptor = $rootDocument['projections'][$key];
            $projections[$key] = $this->readProjectionLocked($descriptor, $kind, $applicationBuildId);
        }

        return new ProjectionBuildSetReadResult(
            applicationBuildId: $applicationBuildId,
            buildSetHash: $buildSetHash,
            projections: $projections,
        );
    }

    /**
     * @param array<string, mixed> $expectedInputHashes
     * @return array<string, string>
     */
    private function expectedIdentities(string $expectedApplicationBuildId, array $expectedInputHashes): array
    {
        $this->expectedApplicationBuildId($expectedApplicationBuildId);
        $keys = array_keys($this->codec->kinds());
        if (count($expectedInputHashes) !== count($keys)) {
            throw new ProjectionBuildSetValidationException('Expected projection input identities are not exact.');
        }

        /** @var array<string, string> $normalized */
        $normalized = [];
        foreach ($keys as $key) {
            $value = $expectedInputHashes[$key] ?? null;
            if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
                throw new ProjectionBuildSetValidationException(
                    'Expected projection input identity must be lowercase SHA-256 hex.',
                );
            }
            $normalized[$key] = $value;
        }
        if (array_diff(array_keys($expectedInputHashes), $keys) !== []) {
            throw new ProjectionBuildSetValidationException('Expected projection input identities are not exact.');
        }

        return $normalized;
    }

    private function expectedApplicationBuildId(string $expectedApplicationBuildId): void
    {
        if (
            $expectedApplicationBuildId === ''
            || strlen($expectedApplicationBuildId) > 128
            || preg_match('//u', $expectedApplicationBuildId) !== 1
            || preg_match('/[\x00-\x1F\x7F-\x9F]/u', $expectedApplicationBuildId) === 1
        ) {
            throw new ProjectionBuildSetValidationException('Expected application build identity is invalid.');
        }
    }

    /**
     * @param array<string, string> $expectedInputHashes
     */
    private function readStatusLocked(
        string $expectedApplicationBuildId,
        array $expectedInputHashes,
    ): ProjectionBuildSetRecoveryResult {
        try {
            $rootDocument = $this->readRootLocked();
        } catch (ProjectionBuildSetAbsentException) {
            return new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::ABSENT);
        } catch (ProjectionBuildSetReadLimitException) {
            return new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::MALFORMED);
        } catch (ProjectionBuildSetValidationException $exception) {
            return new ProjectionBuildSetRecoveryResult($this->statusForReason($exception->reason));
        }

        $status = null;
        if ($rootDocument['applicationBuildId'] !== $expectedApplicationBuildId) {
            $status = ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH;
        }
        /** @var array<string, ProjectionBuildSetProjection> $projections */
        $projections = [];
        foreach ($this->codec->kinds() as $key => $kind) {
            try {
                $projections[$key] = $this->readProjectionLocked(
                    $rootDocument['projections'][$key],
                    $kind,
                    $rootDocument['applicationBuildId'],
                );
            } catch (ProjectionBuildSetAbsentException) {
                $status = $this->moreSevere($status, ProjectionBuildSetRecoveryStatus::ABSENT);
            } catch (ProjectionBuildSetReadLimitException) {
                $status = $this->moreSevere($status, ProjectionBuildSetRecoveryStatus::MALFORMED);
            } catch (ProjectionBuildSetValidationException $exception) {
                $status = $this->moreSevere($status, $this->statusForReason($exception->reason));
            }
        }
        foreach ($this->codec->kinds() as $key => $kind) {
            if (!array_key_exists($key, $projections)) {
                continue;
            }
            if ($projections[$key]->inputHash !== $expectedInputHashes[$key]) {
                $status = $this->moreSevere($status, ProjectionBuildSetRecoveryStatus::STALE);
            }
        }

        if ($status !== null) {
            return new ProjectionBuildSetRecoveryResult($status);
        }

        return new ProjectionBuildSetRecoveryResult(
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            new ProjectionBuildSetReadResult(
                applicationBuildId: $rootDocument['applicationBuildId'],
                buildSetHash: $rootDocument['buildSetHash'],
                projections: $projections,
            ),
        );
    }

    private function publishedStatusLocked(string $expectedApplicationBuildId): ProjectionBuildSetRecoveryResult
    {
        try {
            $rootDocument = $this->readRootLocked();
        } catch (ProjectionBuildSetAbsentException) {
            return new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::ABSENT);
        } catch (ProjectionBuildSetReadLimitException) {
            return new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::MALFORMED);
        } catch (ProjectionBuildSetValidationException $exception) {
            return new ProjectionBuildSetRecoveryResult($this->statusForReason($exception->reason));
        }

        $status = $rootDocument['applicationBuildId'] === $expectedApplicationBuildId
            ? null
            : ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH;
        /** @var array<string, ProjectionBuildSetProjection> $projections */
        $projections = [];
        foreach ($this->codec->kinds() as $key => $kind) {
            try {
                $projections[$key] = $this->readProjectionLocked(
                    $rootDocument['projections'][$key],
                    $kind,
                    $rootDocument['applicationBuildId'],
                );
            } catch (ProjectionBuildSetAbsentException) {
                $status = $this->moreSevere($status, ProjectionBuildSetRecoveryStatus::ABSENT);
            } catch (ProjectionBuildSetReadLimitException) {
                $status = $this->moreSevere($status, ProjectionBuildSetRecoveryStatus::MALFORMED);
            } catch (ProjectionBuildSetValidationException $exception) {
                $status = $this->moreSevere($status, $this->statusForReason($exception->reason));
            }
        }

        if ($status !== null) {
            return new ProjectionBuildSetRecoveryResult($status);
        }

        return new ProjectionBuildSetRecoveryResult(
            ProjectionBuildSetRecoveryStatus::AVAILABLE,
            new ProjectionBuildSetReadResult(
                applicationBuildId: $rootDocument['applicationBuildId'],
                buildSetHash: $rootDocument['buildSetHash'],
                projections: $projections,
            ),
        );
    }

    /** @return array{schemaVersion: int, kind: string, applicationBuildId: string, buildSetHash: string, projections: array<string, array{kind: string, inputHash: string, path: string, sha256: string}>} */
    private function readRootLocked(): array
    {
        $root = $this->filesystem->openVerified($this->locator, ProjectionBuildSetLocator::ROOT_NAME, 'rb');
        if (!is_resource($root)) {
            throw new ProjectionBuildSetAbsentException('Projection build-set root is absent.');
        }
        try {
            $rootBytes = $this->filesystem->readBounded($root, ProjectionBuildSetCodec::ROOT_MAX_BYTES);
        } finally {
            $this->filesystem->fclose($root);
        }

        return $this->codec->decodeRoot($rootBytes);
    }

    /**
     * @param array{kind: string, inputHash: string, path: string, sha256: string} $descriptor
     */
    private function readProjectionLocked(
        array $descriptor,
        string $kind,
        string $applicationBuildId,
    ): ProjectionBuildSetProjection {
        $path = $descriptor['path'];
        $handle = $this->filesystem->openVerified($this->locator, $path, 'rb');
        if (!is_resource($handle)) {
            throw new ProjectionBuildSetAbsentException('Projection generation is absent: ' . $path);
        }
        try {
            $bytes = $this->filesystem->readBounded($handle, ProjectionBuildSetCodec::PROJECTION_MAX_BYTES);
        } finally {
            $this->filesystem->fclose($handle);
        }
        if (hash('sha256', $bytes) !== $descriptor['sha256']) {
            throw new ProjectionBuildSetValidationException(
                'Projection generation digest does not match its descriptor.',
            );
        }
        $projection = $this->codec->decodeProjection(
            bytes: $bytes,
            expectedKind: $kind,
            expectedApplicationBuildId: $applicationBuildId,
            expectedInputHash: $descriptor['inputHash'],
        );
        if ($projection->path !== $path || $projection->sha256 !== $descriptor['sha256']) {
            throw new ProjectionBuildSetValidationException(
                'Projection generation basename is not bound to its bytes.',
            );
        }

        return $projection;
    }

    private function statusForReason(ProjectionBuildSetValidationReason $reason): ProjectionBuildSetRecoveryStatus
    {
        return match ($reason) {
            ProjectionBuildSetValidationReason::MALFORMED => ProjectionBuildSetRecoveryStatus::MALFORMED,
            ProjectionBuildSetValidationReason::UNSUPPORTED => ProjectionBuildSetRecoveryStatus::UNSUPPORTED,
            ProjectionBuildSetValidationReason::STALE => ProjectionBuildSetRecoveryStatus::STALE,
            ProjectionBuildSetValidationReason::BUILD_MISMATCH => ProjectionBuildSetRecoveryStatus::BUILD_MISMATCH,
        };
    }

    private function moreSevere(
        ?ProjectionBuildSetRecoveryStatus $current,
        ProjectionBuildSetRecoveryStatus $candidate,
    ): ProjectionBuildSetRecoveryStatus {
        if (
            $current === null
            || self::STATUS_PRECEDENCE[$candidate->value] < self::STATUS_PRECEDENCE[$current->value]
        ) {
            return $candidate;
        }

        return $current;
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

    private function key(string $key): void
    {
        if (!array_key_exists($key, $this->codec->kinds())) {
            throw new ProjectionBuildSetValidationException('Projection key is unsupported.');
        }
    }
}
