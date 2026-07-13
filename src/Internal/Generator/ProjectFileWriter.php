<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use Closure;
use InvalidArgumentException;
use Throwable;

final readonly class ProjectFileWriter
{
    /** @var Closure(string, string): int */
    private Closure $writeFile;

    /** @var Closure(string, string, int): void */
    private Closure $beforePublish;

    /**
     * @param null|Closure(string, string): int $writeFile
     * @param null|Closure(string, string, int): void $beforePublish
     */
    public function __construct(?Closure $writeFile = null, ?Closure $beforePublish = null)
    {
        $this->writeFile = $writeFile ?? static function (string $path, string $contents): int {
            $written = file_put_contents($path, $contents, LOCK_EX);
            if ($written === false) {
                throw new InvalidArgumentException('Unable to prepare generated file.');
            }

            return $written;
        };
        $this->beforePublish = $beforePublish ?? static function (
            string $_temporary,
            string $_target,
            int $_index,
        ): void {};
    }

    /** @param array<string, string> $files Project-relative path to complete contents. */
    public function write(string $basePath, array $files): void
    {
        $root = project_file_root($basePath);
        $targets = project_file_targets($root, $files);
        project_assert_file_targets_available($targets);
        project_write_files($root, $targets, $files, $this->writeFile, $this->beforePublish);
    }
}

function project_file_root(string $basePath): string
{
    $root = realpath($basePath);
    if ($root === false || !is_dir($root)) {
        throw new InvalidArgumentException('Application base path must be an existing directory.');
    }

    return $root === DIRECTORY_SEPARATOR ? $root : rtrim($root, DIRECTORY_SEPARATOR);
}

/**
 * @param array<string, string> $files
 * @return array<string, string>
 */
function project_file_targets(string $root, array $files): array
{
    if ($files === []) {
        throw new InvalidArgumentException('Project file generation requires at least one target.');
    }

    $targets = [];
    foreach ($files as $relative => $_contents) {
        project_assert_safe_relative_path($relative);
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        project_assert_existing_ancestor_within_root($root, $target);
        $targets[$relative] = $target;
    }

    return $targets;
}

function project_assert_existing_ancestor_within_root(string $root, string $target): void
{
    $ancestor = dirname($target);
    while (!file_exists($ancestor) && !is_link($ancestor)) {
        $ancestor = dirname($ancestor);
    }

    if (!is_dir($ancestor)) {
        throw new InvalidArgumentException('Generated file ancestor must be a directory.');
    }

    $resolved = realpath($ancestor);
    if ($resolved === false || $resolved !== $root && !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
        throw new InvalidArgumentException('Generated file path resolves outside the application.');
    }
}

function project_assert_safe_relative_path(string $path): void
{
    $invalid =
        $path === ''
        || str_starts_with($path, '/')
        || str_contains($path, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        || array_intersect(explode('/', $path), ['', '.', '..']) !== [];

    if ($invalid) {
        throw new InvalidArgumentException('Generated file path is invalid.');
    }
}

/** @param array<string, string> $targets */
function project_assert_file_targets_available(array $targets): void
{
    foreach ($targets as $relative => $target) {
        if (file_exists($target) || is_link($target)) {
            throw new InvalidArgumentException(sprintf('Generated file already exists: %s', $relative));
        }
    }
}

/**
 * @param array<string, string> $targets
 * @param array<string, string> $files
 * @param Closure(string, string): int $writeFile
 * @param Closure(string, string, int): void $beforePublish
 */
function project_write_files(
    string $root,
    array $targets,
    array $files,
    Closure $writeFile,
    Closure $beforePublish,
): void {
    /** @var array{temporary: list<string>, targets: list<string>, directories: list<string>} $state */
    $state = ['temporary' => [], 'targets' => [], 'directories' => []];
    set_error_handler(static fn(int $_severity, string $_message, string $_file, int $_line): bool => true);

    try {
        try {
            project_prepare_files($root, $targets, $files, $writeFile, $state);
            project_publish_files($targets, $beforePublish, $state);
        } catch (Throwable $exception) {
            project_rollback_files($state);

            if ($exception instanceof InvalidArgumentException) {
                throw $exception;
            }

            throw new InvalidArgumentException('Project file generation failed.');
        }
    } finally {
        restore_error_handler();
    }
}

/**
 * @param array<string, string> $targets
 * @param array<string, string> $files
 * @param Closure(string, string): int $writeFile
 * @param array{temporary: list<string>, targets: list<string>, directories: list<string>} $state
 */
function project_prepare_files(string $root, array $targets, array $files, Closure $writeFile, array &$state): void
{
    foreach ($targets as $relative => $target) {
        $directory = dirname($target);
        project_ensure_directory($root, $directory, $state['directories']);
        $temporary = $directory . '/.blackops-' . basename($target) . '-' . bin2hex(random_bytes(8)) . '.tmp';
        $state['temporary'][] = $temporary;
        $written = $writeFile($temporary, $files[$relative]);

        if ($written !== strlen($files[$relative])) {
            throw new InvalidArgumentException(sprintf('Unable to prepare generated file: %s', $relative));
        }
    }
}

/**
 * @param array<string, string> $targets
 * @param Closure(string, string, int): void $beforePublish
 * @param array{temporary: list<string>, targets: list<string>, directories: list<string>} $state
 */
function project_publish_files(array $targets, Closure $beforePublish, array &$state): void
{
    $index = 0;
    foreach ($targets as $relative => $target) {
        $temporary = $state['temporary'][$index];
        $beforePublish($temporary, $target, $index);

        if (!link($temporary, $target)) {
            throw new InvalidArgumentException(sprintf('Unable to create generated file: %s', $relative));
        }

        $state['targets'][] = $target;
        if (!unlink($temporary)) {
            throw new InvalidArgumentException(sprintf('Unable to finalize generated file: %s', $relative));
        }

        ++$index;
    }
}

/** @param list<string> $createdDirectories */
function project_ensure_directory(string $root, string $directory, array &$createdDirectories): void
{
    if (is_dir($directory)) {
        return;
    }

    $missing = project_missing_directories($root, $directory);
    if (!mkdir($directory, recursive: true) && !is_dir($directory)) {
        throw new InvalidArgumentException('Unable to create generated file directory.');
    }
    project_assert_existing_ancestor_within_root($root, $directory);

    foreach (array_reverse($missing) as $created) {
        if (!is_dir($created)) {
            continue;
        }

        $createdDirectories[] = $created;
    }
}

/** @return list<string> */
function project_missing_directories(string $root, string $directory): array
{
    $missing = [];
    $candidate = $directory;
    while (!is_dir($candidate)) {
        $missing[] = $candidate;
        $candidate = dirname($candidate);
    }

    $resolved = realpath($candidate);
    if ($resolved === false || $resolved !== $root && !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
        throw new InvalidArgumentException('Generated file directory resolves outside the application.');
    }

    return $missing;
}

/**
 * @param array{temporary: list<string>, targets: list<string>, directories: list<string>} $state
 */
function project_rollback_files(array $state): void
{
    foreach ($state['temporary'] as $temporary) {
        project_remove_file($temporary);
    }

    foreach (array_reverse($state['targets']) as $target) {
        project_remove_file($target);
    }

    foreach (array_reverse($state['directories']) as $directory) {
        $entries = is_dir($directory) ? scandir($directory) : false;
        if ($entries === false || array_diff($entries, ['.', '..']) !== []) {
            continue;
        }

        rmdir($directory);
    }
}

function project_remove_file(string $path): void
{
    if (is_file($path) || is_link($path)) {
        unlink($path);
    }
}
