<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:no-error-control-operator
 */
final class ProjectionBuildSetFilesystem
{
    /** @var callable(string): void|null */
    private $observer;

    /** @param callable(string): void|null $observer */
    public function __construct(?callable $observer = null)
    {
        $this->observer = $observer;
    }

    /** @return array<string, int>|false */
    public function lstat(string $path): array|false
    {
        $this->event('lstat:' . $path);

        return @lstat($path);
    }

    /** @param resource $handle
     * @return array<string, int>|false
     */
    public function fstat(mixed $handle): array|false
    {
        $this->event('fstat');

        return @fstat($handle);
    }

    /** @return resource|false */
    public function fopen(string $path, string $mode): mixed
    {
        $this->event('fopen:' . $mode . ':' . $path);

        return @fopen($path, $mode);
    }

    /** @param resource $handle */
    public function flock(mixed $handle, int $operation): bool
    {
        $this->event('flock:' . $operation);

        return @flock($handle, $operation);
    }

    /** @param resource $handle */
    public function fread(mixed $handle, int $length): string|false
    {
        $this->event('read:' . $length);

        return @stream_get_contents($handle, $length);
    }

    /** @param resource $handle */
    public function fwrite(mixed $handle, string $bytes): int|false
    {
        $this->event('write:' . strlen($bytes));

        return @fwrite($handle, $bytes);
    }

    /** @param resource $handle */
    public function fflush(mixed $handle): bool
    {
        $this->event('flush');

        return @fflush($handle);
    }

    /** @param resource $handle */
    public function fsync(mixed $handle): bool
    {
        $this->event('fsync');

        return @fsync($handle);
    }

    /** @param resource $handle */
    public function fclose(mixed $handle): bool
    {
        $this->event('close');

        return @fclose($handle);
    }

    public function rename(string $from, string $to): bool
    {
        $this->event('rename:' . $from . ':' . $to);

        return @rename($from, $to);
    }

    public function unlink(string $path): bool
    {
        $this->event('unlink:' . $path);

        return @unlink($path);
    }

    /** @return list<string>|false */
    public function entries(string $directory, int $maximum): array|false
    {
        $this->event('scan:' . $directory);
        $this->event('scan-limit:' . $maximum);
        $handle = $this->openEntries($directory);
        if (!is_resource($handle)) {
            return false;
        }
        $entries = [];
        try {
            while (($entry = $this->readEntry($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $entries[] = $entry;
                if (count($entries) > $maximum) {
                    break;
                }
            }
        } finally {
            $this->closeEntries($handle);
        }

        return $entries;
    }

    /** @return resource|false */
    private function openEntries(string $directory): mixed
    {
        $this->event('opendir:' . $directory);

        return @opendir($directory);
    }

    /** @param resource $handle */
    private function readEntry(mixed $handle): string|false
    {
        $entry = @readdir($handle);
        $this->event('readdir:' . ($entry === false ? '<eof>' : $entry));

        return $entry;
    }

    /** @param resource $handle */
    private function closeEntries(mixed $handle): void
    {
        $this->event('closedir');
        closedir($handle);
    }

    /** @return resource|false */
    public function openDirectory(string $directory): mixed
    {
        $this->event('open-directory:' . $directory);

        return @fopen(filename: $directory, mode: 'r');
    }

    public function regular(array $stat): bool
    {
        if (!array_key_exists('mode', $stat) || !is_int($stat['mode'])) {
            return false;
        }

        return ($stat['mode'] & 0o170_000) === 0o100_000;
    }

    /** @param array<string, int> $left
     * @param array<string, int> $right
     */
    public function sameIdentity(array $left, array $right): bool
    {
        return (
            $this->regular($left)
            && $this->regular($right)
            && ($left['dev'] ?? null) === ($right['dev'] ?? null)
            && ($left['ino'] ?? null) === ($right['ino'] ?? null)
        );
    }

    /** @return resource */
    /** @mago-expect lint:no-boolean-flag-parameter */
    public function openVerified(
        ProjectionBuildSetLocator $locator,
        string $basename,
        string $mode,
        bool $absentIsAllowed = false,
    ): mixed {
        $path = $locator->path($basename);
        $before = $this->lstat($path);
        if ($before === false) {
            if ($absentIsAllowed) {
                return null;
            }
            throw new ProjectionBuildSetAbsentException('Projection build-set path is absent: ' . $basename);
        }
        if (!$this->regular($before)) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set path is not a regular file: '
            . $basename);
        }
        $handle = $this->fopen($path, $mode);
        if (!is_resource($handle)) {
            throw new ProjectionBuildSetFilesystemException('Projection build-set path could not be opened: '
            . $basename);
        }
        try {
            $middle = $this->fstat($handle);
            $after = $this->lstat($path);
            if (
                $middle === false
                || $after === false
                || !$this->sameIdentity($before, $middle)
                || !$this->sameIdentity($middle, $after)
            ) {
                throw new ProjectionBuildSetFilesystemException('Projection build-set path changed during open: '
                . $basename);
            }

            return $handle;
        } catch (Throwable $exception) {
            $this->fclose($handle);
            throw $exception;
        }
    }

    /** @param resource $handle */
    public function readBounded(mixed $handle, int $maximum): string
    {
        $bytes = $this->fread($handle, $maximum + 1);
        if ($bytes !== false && strlen($bytes) > $maximum) {
            throw new ProjectionBuildSetReadLimitException('Projection build-set file exceeds its bounded read size.');
        }
        if ($bytes === false) {
            throw new ProjectionBuildSetFilesystemException(
                'Projection build-set file could not be read within its bound.',
            );
        }

        return $bytes;
    }

    /** @param resource $handle */
    public function writeAll(mixed $handle, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = $this->fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new ProjectionBuildSetFilesystemException('Projection build-set file could not be written.');
            }
            $offset += $written;
        }
    }

    public function event(string $event): void
    {
        if ($this->observer !== null) {
            ($this->observer)($event);
        }
    }
}
