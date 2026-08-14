<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactBuilder;
use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactManifest;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrameworkProxyArtifactLoader
{
    /** @mago-expect lint:halstead */
    public function load(
        string $directory,
        string $applicationBuildId,
        string $expectedManifestHash,
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
    ): FrameworkProxyArtifactManifest {
        if (is_link($directory) || !is_dir($directory)) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        $manifestPath = $directory . '/manifest.json';
        if (is_link($manifestPath) || !is_file($manifestPath)) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        try {
            $decoded = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        if (!is_array($decoded)) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        /** @var array<string,mixed> $payload */
        $payload = $decoded;
        $manifest = $this->decode($payload);
        if ($manifest->applicationBuildId !== $applicationBuildId) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::BUILD_MISMATCH);
        }

        try {
            $matches = $manifest->profile->equals($profile);
        } catch (Throwable) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::PROFILE_MISMATCH);
        }
        if (!$matches) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::PROFILE_MISMATCH);
        }

        if (
            preg_match('/^[a-f0-9]{64}$/D', $expectedManifestHash) !== 1
            || $manifest->manifestHash !== $expectedManifestHash
            || $manifest->manifestHash !== $manifest->canonicalHash()
        ) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_HASH);
        }

        if (basename($directory) !== $applicationBuildId . '-' . $manifest->inputHash) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        $this->verifyInventory($directory, $manifest);
        $resolved = $this->resolve($directory, $manifest);

        // Validate every class before requiring any file, so a later map error
        // cannot be hidden behind execution of an earlier proxy file.
        foreach ($resolved as [$path, $proxy, $source]) {
            if (class_exists($proxy, false)) {
                $reflection = new ReflectionClass($proxy);
                $parent = $reflection->getParentClass();
                if (
                    $parent === false
                    || $parent->getName() !== $source
                    || realpath((string) $reflection->getFileName()) !== realpath($path)
                ) {
                    throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
                }
            }
        }

        foreach ($resolved as [$path, $proxy, $source]) {
            if (!class_exists($proxy, false)) {
                require_once $path;
            }
            if (!class_exists($proxy, false)) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
            }

            $reflection = new ReflectionClass($proxy);
            $parent = $reflection->getParentClass();
            if (
                $parent === false
                || $parent->getName() !== $source
                || realpath((string) $reflection->getFileName()) !== realpath($path)
            ) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
            }
        }

        return $manifest;
    }

    /** @param array<string,mixed> $payload */
    /** @mago-expect lint:halstead */
    private function decode(array $payload): FrameworkProxyArtifactManifest
    {
        $keys = [
            'schema_version',
            'application_build_id',
            'profile',
            'generator_version',
            'php_version',
            'input_hash',
            'proxies',
            'files',
            'class_map',
            'source_inputs',
            'abi_version',
            'initializer',
            'manifest_hash',
        ];
        if (array_diff(array_keys($payload), $keys) !== [] || array_diff($keys, array_keys($payload)) !== []) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        if (
            !is_int($payload['schema_version'])
            || $payload['schema_version'] !== FrameworkProxyArtifactBuilder::SCHEMA_VERSION
        ) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::VERSION_MISMATCH);
        }

        $stringFields = [
            'application_build_id',
            'profile',
            'generator_version',
            'php_version',
            'input_hash',
            'abi_version',
            'initializer',
            'manifest_hash',
        ];
        foreach ($stringFields as $field) {
            if (!is_string($payload[$field]) || $payload[$field] === '') {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
            }
        }

        if (!is_array($payload['proxies']) || !array_is_list($payload['proxies'])) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }
        if (!is_array($payload['files']) || $payload['files'] !== [] && array_is_list($payload['files'])) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }
        if (!is_array($payload['class_map']) || $payload['class_map'] !== [] && array_is_list($payload['class_map'])) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }
        if (
            !is_array($payload['source_inputs'])
            || $payload['source_inputs'] !== [] && array_is_list($payload['source_inputs'])
        ) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        $applicationBuildId = $this->requiredString($payload, 'application_build_id');
        $profileValue = $this->requiredString($payload, 'profile');
        $generatorVersion = $this->requiredString($payload, 'generator_version');
        $phpVersion = $this->requiredString($payload, 'php_version');
        $inputHash = $this->requiredString($payload, 'input_hash');
        $abiVersion = $this->requiredString($payload, 'abi_version');
        $initializer = $this->requiredString($payload, 'initializer');
        $manifestHash = $this->requiredString($payload, 'manifest_hash');
        if (
            $generatorVersion !== 'framework-proxy-generator-1'
            || $phpVersion !== PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
            || $abiVersion !== FrameworkProxyArtifactBuilder::ABI_VERSION
            || $initializer !== FrameworkProxyArtifactBuilder::INITIALIZER
        ) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::VERSION_MISMATCH);
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $inputHash) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $manifestHash) !== 1) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        $files = [];
        foreach ($payload['files'] as $path => $hash) {
            if (!is_string($path) || !is_string($hash) || !$this->validPath($path) || !$this->validHash($hash)) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
            }
            $files[$path] = $hash;
        }

        $classMap = [];
        foreach ($payload['class_map'] as $source => $proxy) {
            if (
                !is_string($source)
                || !is_string($proxy)
                || !$this->validClass($source)
                || !$this->validClass($proxy)
            ) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
            }
            if (isset($classMap[$source])) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
            }
            $classMap[$source] = $proxy;
        }

        $sourceInputs = [];
        foreach ($payload['source_inputs'] as $key => $hash) {
            if (
                !is_string($key)
                || !is_string($hash)
                || !$this->validSourceInputKey($key)
                || !$this->validHash($hash)
            ) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
            }
            $sourceInputs[$key] = $hash;
        }

        $proxies = [];
        $seenSources = [];
        $seenProxies = [];
        $seenPaths = [];
        $entryKeys = [
            'source_class',
            'source_path',
            'source_hash',
            'proxy_class',
            'path',
            'hash',
            'signature_hash',
            'metadata_hash',
        ];
        foreach ($payload['proxies'] as $entry) {
            if (
                !is_array($entry)
                || array_diff(array_keys($entry), $entryKeys) !== []
                || array_diff($entryKeys, array_keys($entry)) !== []
            ) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
            }
            if (
                !is_string($entry['source_class'])
                || !$this->validClass($entry['source_class'])
                || $entry['source_path'] !== null
                || !is_string($entry['source_hash'])
                || !is_string($entry['proxy_class'])
                || !$this->validClass($entry['proxy_class'])
                || !is_string($entry['path'])
                || !$this->validPath($entry['path'])
                || !is_string($entry['hash'])
                || !is_string($entry['signature_hash'])
                || !is_string($entry['metadata_hash'])
                || !$this->validHash($entry['source_hash'])
                || !$this->validHash($entry['hash'])
                || !$this->validHash($entry['signature_hash'])
                || !$this->validHash($entry['metadata_hash'])
                || ($sourceInputs[$entry['source_class']] ?? null) !== $entry['source_hash']
            ) {
                throw $this->fail(
                    ($sourceInputs[$entry['source_class']] ?? null) === $entry['source_hash']
                        ? FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID
                        : FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH,
                );
            }
            if (
                isset($seenSources[$entry['source_class']])
                || isset($seenProxies[$entry['proxy_class']])
                || isset($seenPaths[$entry['path']])
            ) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
            }
            $seenSources[$entry['source_class']] = true;
            $seenProxies[$entry['proxy_class']] = true;
            $seenPaths[$entry['path']] = true;
            $proxies[] = [
                'source_class' => $entry['source_class'],
                'source_path' => null,
                'source_hash' => $entry['source_hash'],
                'proxy_class' => $entry['proxy_class'],
                'path' => $entry['path'],
                'hash' => $entry['hash'],
                'signature_hash' => $entry['signature_hash'],
                'metadata_hash' => $entry['metadata_hash'],
            ];
        }

        if (count($proxies) !== count($files) || count($proxies) !== count($classMap)) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
        }

        try {
            $manifestProfile = FrameworkProxyProfile::from($profileValue);
        } catch (Throwable) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::PROFILE_MISMATCH);
        }

        return new FrameworkProxyArtifactManifest(
            FrameworkProxyArtifactBuilder::SCHEMA_VERSION,
            $applicationBuildId,
            $manifestProfile,
            $generatorVersion,
            $phpVersion,
            $inputHash,
            $proxies,
            $files,
            $classMap,
            $sourceInputs,
            $manifestHash,
            $abiVersion,
            $initializer,
        );
    }

    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }
        return $value;
    }

    private function verifyInventory(string $directory, FrameworkProxyArtifactManifest $manifest): void
    {
        $expected = ['manifest.json' => true];
        foreach ($manifest->files as $path => $_hash) {
            $expected[$path] = true;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS,
            ));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo) {
                    throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
                }
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                if ($file->isLink() || !isset($expected[$relative])) {
                    throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
                }
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MANIFEST_INVALID);
        }

        foreach ($manifest->files as $relative => $hash) {
            $path = $directory . '/' . $relative;
            if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== $hash) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::FILE_HASH);
            }
        }
    }

    /** @return list<array{0:string,1:string,2:string}> */
    private function resolve(string $directory, FrameworkProxyArtifactManifest $manifest): array
    {
        $resolved = [];
        $seenSources = [];
        $seenProxies = [];
        $seenPaths = [];
        foreach ($manifest->proxies as $entry) {
            $source = $entry['source_class'];
            $proxy = $entry['proxy_class'];
            $path = $entry['path'];
            if (
                !isset($manifest->files[$path])
                || $manifest->files[$path] !== $entry['hash']
                || isset($seenSources[$source])
                || isset($seenProxies[$proxy])
                || isset($seenPaths[$path])
                || ($manifest->classMap[$source] ?? null) !== $proxy
            ) {
                throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
            }
            $seenSources[$source] = true;
            $seenProxies[$proxy] = true;
            $seenPaths[$path] = true;
            $resolved[] = [$directory . '/' . $path, $proxy, $source];
        }
        if (count($seenPaths) !== count($manifest->files) || count($seenSources) !== count($manifest->classMap)) {
            throw $this->fail(FrameworkProxyArtifactDiagnosticCode::MAP_MISMATCH);
        }
        return $resolved;
    }

    private function validHash(string $hash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $hash) === 1;
    }

    private function validClass(string $class): bool
    {
        return (
            preg_match('~^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$~D', $class) === 1
            && !str_contains($class, "\0")
        );
    }

    private function validPath(string $path): bool
    {
        return preg_match('/^proxies\/[A-Za-z_][A-Za-z0-9_]*\.php$/D', $path) === 1 && !str_contains($path, "\0");
    }

    private function validSourceInputKey(string $key): bool
    {
        return (
            preg_match('~^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*(?:::[A-Za-z_][A-Za-z0-9_]*)?$~D', $key)
            === 1
        );
    }

    private function fail(string $code): InvalidArgumentException
    {
        return new InvalidArgumentException($code);
    }
}
