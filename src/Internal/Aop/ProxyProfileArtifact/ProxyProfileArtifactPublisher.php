<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\ProxyProfileArtifact;

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactManifest;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use InvalidArgumentException;
use RuntimeException;

/** Publishes complete, build-bound proxy-profile units without directory discovery. */
final readonly class ProxyProfileArtifactPublisher
{
    private const SCHEMA_VERSION = 1;

    public function publishFramework(
        string $root,
        string $buildId,
        ?string $frameworkDirectory,
        ?FrameworkProxyArtifactManifest $frameworkManifest,
    ): ProxyProfileArtifactManifest {
        $this->assertBuildId($buildId);
        $frameworkManifestHash = $frameworkManifest instanceof FrameworkProxyArtifactManifest
            ? $frameworkManifest->manifestHash
            : null;
        if (
            $frameworkManifest instanceof FrameworkProxyArtifactManifest
            && (
                $frameworkManifest->applicationBuildId !== $buildId
                || !$frameworkManifest->profile->equals(FrameworkProxyProfile::FRAMEWORK)
                || preg_match('/^[a-f0-9]{64}$/D', $frameworkManifest->manifestHash) !== 1
                || $frameworkManifest->canonicalHash() !== $frameworkManifest->manifestHash
            )
        ) {
            throw new InvalidArgumentException('Framework proxy artifact manifest identity is invalid.');
        }
        if (($frameworkDirectory === null) !== ($frameworkManifestHash === null)) {
            throw new InvalidArgumentException('Framework proxy artifact directory and hash must be paired.');
        }
        $relative = null;
        if ($frameworkDirectory !== null) {
            if (!is_dir($frameworkDirectory) || is_link($frameworkDirectory)) {
                throw new InvalidArgumentException('Framework proxy artifact directory is invalid.');
            }
            $expectedSibling = rtrim(dirname($root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'framework-proxies';
            if (is_link($expectedSibling)) {
                throw new InvalidArgumentException('Framework proxy artifact directory location is invalid.');
            }
            $actualParent = realpath(dirname($frameworkDirectory));
            $siblingParent = is_dir($expectedSibling) ? realpath($expectedSibling) : $expectedSibling;
            if ($actualParent === false || $actualParent !== $siblingParent) {
                throw new InvalidArgumentException('Framework proxy artifact directory location is invalid.');
            }
            $leaf = basename($frameworkDirectory);
            if (!str_starts_with($leaf, $buildId . '-')) {
                throw new InvalidArgumentException('Framework proxy artifact directory identity is invalid.');
            }
            $inputHash = substr($leaf, strlen($buildId) + 1);
            if (preg_match('/^[a-f0-9]{64}$/D', $inputHash) !== 1) {
                throw new InvalidArgumentException('Framework proxy artifact directory identity is invalid.');
            }
            if (
                $frameworkManifest instanceof FrameworkProxyArtifactManifest
                && $frameworkManifest->inputHash !== $inputHash
            ) {
                throw new InvalidArgumentException('Framework proxy artifact directory identity is invalid.');
            }
            if ($frameworkManifest instanceof FrameworkProxyArtifactManifest) {
                $manifestPath = $frameworkDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
                $actual = is_file($manifestPath) && !is_link($manifestPath)
                    ? json_decode((string) file_get_contents($manifestPath), true)
                    : null;
                if (!is_array($actual) || $actual !== $frameworkManifest->toArray()) {
                    throw new InvalidArgumentException('Framework proxy artifact manifest is invalid.');
                }
                $this->validateFrameworkInventory($frameworkDirectory, $frameworkManifest);
            }
            $relative = '../framework-proxies/' . $leaf;
        }
        $manifest = $this->manifest(
            $buildId,
            FrameworkProxyProfile::framework(),
            [],
            $relative,
            $frameworkManifestHash,
        );
        return $this->publish($root, $manifest, []);
    }

    /** @param array<string,string> $sources */
    private function publish(
        string $root,
        ProxyProfileArtifactManifest $manifest,
        array $sources,
    ): ProxyProfileArtifactManifest {
        if (is_link($root) || !is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('Proxy profile artifact root could not be created.');
        }
        $directory = $root . DIRECTORY_SEPARATOR . $manifest->applicationBuildId . '-' . $manifest->contentHash;
        if (is_link($directory)) {
            throw new RuntimeException('Proxy profile artifact unit is invalid.');
        }
        if (is_dir($directory)) {
            return $this->readExisting($directory, $manifest);
        }
        $staging = $root . DIRECTORY_SEPARATOR . '.staging-' . bin2hex(random_bytes(12));
        if (!mkdir($staging, 0775, true) && !is_dir($staging)) {
            throw new RuntimeException('Proxy profile artifact staging directory could not be created.');
        }
        if (
            $sources !== []
            && !mkdir($staging . DIRECTORY_SEPARATOR . 'aop', 0775, true)
            && !is_dir($staging . DIRECTORY_SEPARATOR . 'aop')
        ) {
            throw new RuntimeException('Proxy profile artifact staging directory could not be created.');
        }
        try {
            foreach ($sources as $relative => $source) {
                $target = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!copy($source, $target)) {
                    throw new RuntimeException('Proxy profile artifact could not be copied.');
                }
                if (hash_file('sha256', $target) !== $manifest->files[$relative]) {
                    throw new RuntimeException('Proxy profile artifact copy integrity is invalid.');
                }
            }
            $json =
                json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                . "\n";
            if (file_put_contents($staging . DIRECTORY_SEPARATOR . 'manifest.json', $json, LOCK_EX) !== strlen($json)) {
                throw new RuntimeException('Proxy profile artifact manifest could not be written.');
            }
            if (!rename($staging, $directory)) {
                if (is_dir($directory)) {
                    return $this->readExisting($directory, $manifest);
                }
                throw new RuntimeException('Proxy profile artifact could not be published.');
            }
        } finally {
            if (is_dir($staging)) {
                $this->remove($staging);
            }
        }
        return $manifest;
    }

    /** @param array<string,string> $files */
    private function manifest(
        string $buildId,
        FrameworkProxyProfile $profile,
        array $files,
        ?string $frameworkDirectory = null,
        ?string $frameworkManifestHash = null,
    ): ProxyProfileArtifactManifest {
        $candidate = new ProxyProfileArtifactManifest(
            self::SCHEMA_VERSION,
            $buildId,
            $profile,
            '',
            $files,
            $frameworkDirectory,
            $frameworkManifestHash,
        );
        return new ProxyProfileArtifactManifest(
            $candidate->schemaVersion,
            $candidate->applicationBuildId,
            $candidate->profile,
            $candidate->canonicalHash(),
            $candidate->files,
            $candidate->frameworkDirectory,
            $candidate->frameworkManifestHash,
        );
    }

    private function readExisting(
        string $directory,
        ProxyProfileArtifactManifest $expected,
    ): ProxyProfileArtifactManifest {
        if (is_link($directory) || !is_dir($directory)) {
            throw new RuntimeException('Proxy profile artifact unit is invalid.');
        }
        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($manifestPath) || is_link($manifestPath)) {
            throw new RuntimeException('Proxy profile artifact manifest is invalid.');
        }
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($decoded) || $decoded !== $expected->toArray()) {
            throw new RuntimeException('Proxy profile artifact identity collision.');
        }
        $entries = array_values(array_filter(
            scandir($directory) ?: [],
            static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
        ));
        $expectedEntries = ['manifest.json'];
        if ($expected->files !== []) {
            $expectedEntries[] = 'aop';
        }
        sort($entries);
        sort($expectedEntries);
        if ($entries !== $expectedEntries) {
            throw new RuntimeException('Proxy profile artifact inventory is invalid.');
        }
        if (
            $expected->files !== []
            && (!is_dir($directory . DIRECTORY_SEPARATOR . 'aop') || is_link($directory . DIRECTORY_SEPARATOR . 'aop'))
        ) {
            throw new RuntimeException('Proxy profile artifact inventory is invalid.');
        }
        if ($expected->files !== []) {
            $actualAop = array_values(array_filter(
                scandir($directory . DIRECTORY_SEPARATOR . 'aop') ?: [],
                static fn(string $entry): bool => $entry !== '.' && $entry !== '..',
            ));
            sort($actualAop);
            $expectedAop = array_map(static fn(string $path): string => basename($path), array_keys($expected->files));
            sort($expectedAop);
            if ($actualAop !== $expectedAop) {
                throw new RuntimeException('Proxy profile artifact inventory is invalid.');
            }
        }
        foreach ($expected->files as $relative => $hash) {
            $file = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $actual = is_file($file) && !is_link($file) ? hash_file('sha256', $file) : false;
            if (!is_string($actual) || $actual !== $hash) {
                throw new RuntimeException('Proxy profile artifact file integrity is invalid.');
            }
        }
        if ($expected->frameworkDirectory !== null) {
            $frameworkRoot = realpath(dirname($directory) . DIRECTORY_SEPARATOR . '../framework-proxies');
            $frameworkDirectory = dirname($directory) . DIRECTORY_SEPARATOR . $expected->frameworkDirectory;
            $resolvedFrameworkDirectory = realpath($frameworkDirectory);
            $frameworkRootPath = dirname($directory) . DIRECTORY_SEPARATOR . '../framework-proxies';
            if (
                $frameworkRoot === false
                || $resolvedFrameworkDirectory === false
                || dirname($resolvedFrameworkDirectory) !== $frameworkRoot
                || is_link($frameworkRootPath)
            ) {
                throw new RuntimeException('Framework proxy artifact directory is invalid.');
            }
            $frameworkManifestPath = $resolvedFrameworkDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
            $frameworkData = is_file($frameworkManifestPath) && !is_link($frameworkManifestPath)
                ? json_decode((string) file_get_contents($frameworkManifestPath), true)
                : null;
            if (
                !is_array($frameworkData)
                || ($frameworkData['manifest_hash'] ?? null) !== $expected->frameworkManifestHash
            ) {
                throw new RuntimeException('Framework proxy artifact manifest is invalid.');
            }
            $canonicalFrameworkData = $frameworkData;
            unset($canonicalFrameworkData['manifest_hash']);
            if (
                hash('sha256', json_encode(
                    $canonicalFrameworkData,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                )) !== $expected->frameworkManifestHash
            ) {
                throw new RuntimeException('Framework proxy artifact manifest is invalid.');
            }
            $frameworkFiles = $frameworkData['files'] ?? null;
            if (!is_array($frameworkFiles)) {
                throw new RuntimeException('Framework proxy artifact inventory is invalid.');
            }
            foreach ($frameworkFiles as $relative => $hash) {
                if (
                    !is_string($relative)
                    || !is_string($hash)
                    || str_contains($relative, '..')
                    || str_contains($relative, '\\')
                ) {
                    throw new RuntimeException('Framework proxy artifact inventory is invalid.');
                }
                $file =
                    $resolvedFrameworkDirectory
                    . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $actual = is_file($file) && !is_link($file) ? hash_file('sha256', $file) : false;
                if (!is_string($actual) || $actual !== $hash) {
                    throw new RuntimeException('Framework proxy artifact file integrity is invalid.');
                }
            }
        }
        return $expected;
    }

    private function remove(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) && !is_link($path) ? $this->remove($path) : unlink($path);
        }
        rmdir($directory);
    }

    private function validateFrameworkInventory(string $directory, FrameworkProxyArtifactManifest $manifest): void
    {
        $expected = ['manifest.json' => true];
        $expectedDirectories = ['proxies' => true];
        foreach ($manifest->files as $path => $hash) {
            if (
                !is_string($path)
                || !is_string($hash)
                || str_contains($path, '..')
                || str_contains($path, '\\')
                || str_starts_with($path, '/')
            ) {
                throw new InvalidArgumentException('Framework proxy artifact inventory is invalid.');
            }
            $expected[$path] = true;
            $parts = explode('/', $path);
            array_pop($parts);
            $prefix = '';
            foreach ($parts as $part) {
                $prefix = $prefix === '' ? $part : $prefix . '/' . $part;
                $expectedDirectories[$prefix] = true;
            }
        }
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->isLink()) {
                    throw new InvalidArgumentException('Framework proxy artifact inventory is invalid.');
                }
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                if ($file->isDir() && !isset($expectedDirectories[$relative])) {
                    throw new InvalidArgumentException('Framework proxy artifact inventory is invalid.');
                }
                if (!$file->isDir() && !isset($expected[$relative])) {
                    throw new InvalidArgumentException('Framework proxy artifact inventory is invalid.');
                }
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidArgumentException('Framework proxy artifact inventory is invalid.');
        }
        foreach ($manifest->files as $path => $hash) {
            $file = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (!is_file($file) || is_link($file) || hash_file('sha256', $file) !== $hash) {
                throw new InvalidArgumentException('Framework proxy artifact file integrity is invalid.');
            }
        }
    }

    private function assertBuildId(string $buildId): void
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $buildId) !== 1) {
            throw new InvalidArgumentException('Application build ID is invalid.');
        }
    }
}
