<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyArtifact;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerationResult;
use ReflectionClass;
use RuntimeException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final readonly class FrameworkProxyArtifactBuilder
{
    public const SCHEMA_VERSION = 1;
    public const ABI_VERSION = 'framework-proxy-invocation-1';
    public const INITIALIZER = '__blackopsInitialize';

    /**
     * @mago-expect lint:excessive-parameter-list
     * @param array<string,string> $inputHashes
     * @param array<string,array{0:string,1:string,2:string}> $sources
     * @param array<string,string> $contexts
     * @param array<string,string> $sourceInputs
     */
    public static function inputHash(
        string $buildId,
        FrameworkProxyProfile $profile,
        string $generatorVersion,
        array $inputHashes,
        array $sources,
        array $contexts = [],
        array $sourceInputs = [],
    ): string {
        ksort($inputHashes);
        ksort($sources);
        ksort($contexts);
        ksort($sourceInputs);

        return hash(
            'sha256',
            (string) json_encode([
                'build_id' => $buildId,
                'profile' => $profile->value,
                'generator' => $generatorVersion,
                'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                'abi' => self::ABI_VERSION,
                'initializer' => self::INITIALIZER,
                'inputs' => $inputHashes,
                'contexts' => $contexts,
                'sources' => $sources,
                'source_inputs' => $sourceInputs,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     * @mago-expect lint:halstead
     * @param list<array{0:ReflectionClass<object>,1:FrameworkProxyMetadata,2:string,3:string}> $items
     * @param array<string,string> $inputHashes
     * @param array<string,string> $contexts
     */
    public function publishBatch(
        string $root,
        string $buildId,
        FrameworkProxyProfile $profile,
        string $generatorVersion,
        array $items,
        array $inputHashes = [],
        array $contexts = [],
    ): FrameworkProxyGenerationResult {
        $this->assertBuildId($buildId);
        $this->assertInputHashes($inputHashes);
        $this->assertInputHashes($contexts);
        ksort($inputHashes);
        ksort($contexts);
        $this->makeDirectory($root);
        $entries = [];
        $files = [];
        $classMap = [];
        $sources = [];
        $sourceInputs = [];
        foreach ($items as [$source, $metadata, $proxyClass, $sourceCode]) {
            $sourcePath = $source->getFileName();
            if (!is_string($sourcePath) || !is_file($sourcePath)) {
                throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
            }
            $sourceHash = hash_file('sha256', $sourcePath);
            if (!is_string($sourceHash)) {
                throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SOURCE_HASH);
            }
            $relative = 'proxies/' . $this->fileName($proxyClass);
            if (
                array_key_exists($source->getName(), $classMap)
                || in_array($proxyClass, $classMap, true)
                || array_key_exists($relative, $files)
            ) {
                throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::DUPLICATE_IDENTITY);
            }
            $proxyHash = hash('sha256', $sourceCode);
            $signatureHash = hash(
                'sha256',
                (string) json_encode(
                    array_map(static fn($method): string => $method->signature, $metadata->methods),
                    JSON_THROW_ON_ERROR,
                ),
            );
            $metadataHash = hash(
                'sha256',
                (string) json_encode([
                    'class' => $metadata->sourceClass,
                    'ownership' => $metadata->ownership->value,
                    'methods' => array_map(static fn($method): array => [
                        $method->name,
                        $method->signature,
                        $method->transactionalConnection,
                        $method->transactional,
                        $method->afterCommit,
                    ], $metadata->methods),
                ], JSON_THROW_ON_ERROR),
            );
            $sources[$source->getName()] = [$sourceHash, $signatureHash, $metadataHash];
            $sourceInputs[$source->getName()] = $sourceHash;
            foreach ($metadata->methods as $method) {
                try {
                    $declaringPath = $source->getMethod($method->name)->getFileName();
                } catch (\Throwable) {
                    throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
                }
                if (!is_string($declaringPath) || !is_file($declaringPath)) {
                    throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
                }
                $declaringHash = hash_file('sha256', $declaringPath);
                if (!is_string($declaringHash)) {
                    throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SOURCE_HASH);
                }
                $sourceInputs[$method->declaringClass . '::' . $method->name] = $declaringHash;
            }
            $entries[] = [
                'source_class' => $source->getName(),
                'source_path' => null,
                'source_hash' => $sourceHash,
                'proxy_class' => $proxyClass,
                'path' => $relative,
                'hash' => $proxyHash,
                'signature_hash' => $signatureHash,
                'metadata_hash' => $metadataHash,
            ];
            $files[$relative] = $proxyHash;
            $classMap[$source->getName()] = $proxyClass;
        }
        ksort($sources);
        ksort($sourceInputs);
        ksort($files);
        ksort($classMap);
        usort($entries, static fn(array $left, array $right): int => strcmp(
            $left['source_class'],
            $right['source_class'],
        ));
        $inputHash = self::inputHash(
            $buildId,
            $profile,
            $generatorVersion,
            $inputHashes,
            $sources,
            $contexts,
            $sourceInputs,
        );
        $directory = $root . '/' . $buildId . '-' . $inputHash;
        $existing = is_dir($directory);
        $staging = null;
        try {
            $staging = $root . '/.staging-' . $buildId . '-' . bin2hex(random_bytes(8));
            $this->makeDirectory($staging . '/proxies');
            foreach ($items as [$source, $metadata, $proxyClass, $sourceCode]) {
                $path = $staging . '/proxies/' . $this->fileName($proxyClass);
                $this->write($path, $sourceCode);
                $sourceFile = $source->getFileName();
                if (!is_string($sourceFile) || !is_file($sourceFile)) {
                    throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SOURCE_UNAVAILABLE);
                }
                $this->verifySource($path, $proxyClass, $source->getName(), $sourceFile);
            }
            $manifest = new FrameworkProxyArtifactManifest(
                self::SCHEMA_VERSION,
                $buildId,
                $profile,
                $generatorVersion,
                PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                $inputHash,
                $entries,
                $files,
                $classMap,
                $sourceInputs,
                '',
            );
            $manifest = new FrameworkProxyArtifactManifest(
                $manifest->schemaVersion,
                $manifest->applicationBuildId,
                $manifest->profile,
                $manifest->generatorVersion,
                $manifest->phpVersion,
                $manifest->inputHash,
                $manifest->proxies,
                $manifest->files,
                $manifest->classMap,
                $sourceInputs,
                $manifest->canonicalHash(),
                self::ABI_VERSION,
                self::INITIALIZER,
            );
            $this->write(
                $staging . '/manifest.json',
                (string) json_encode(
                    $manifest->toArray(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ) . "\n",
            );
            $this->verifyHashes($staging, $manifest);
            if ($existing) {
                $loaded = new \BlackOps\Internal\Runtime\FrameworkProxyArtifactLoader()->load(
                    $directory,
                    $buildId,
                    $manifest->manifestHash,
                    $profile,
                );
                if ($loaded->manifestHash !== $manifest->manifestHash)
                    throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::IDENTITY_INCONSISTENT);
                $this->removeTree($staging);
                $this->writeIndex($root, basename($directory));
                return new FrameworkProxyGenerationResult($directory, $manifest, $classMap);
            }
            $this->rename($staging, $directory);
            $this->writeIndex($root, basename($directory));
            $this->cleanupOldBuilds($root);
            return new FrameworkProxyGenerationResult($directory, $manifest, $classMap);
        } catch (\Throwable $exception) {
            if (is_string($staging) && is_dir($staging))
                $this->removeTree($staging);
            throw $exception;
        }
    }

    private function verifySource(string $path, string $proxyClass, string $sourceClass, string $sourcePath): void
    {
        if ($this->runSubprocess([PHP_BINARY, '-l', $path]) !== 0) {
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::SYNTAX_INVALID);
        }
        $autoloadPath = null;
        try {
            $loaderPath = new ReflectionClass('Composer\\Autoload\\ClassLoader')->getFileName();
            if (is_string($loaderPath)) {
                $candidate = dirname(dirname($loaderPath)) . '/autoload.php';
                if (is_file($candidate))
                    $autoloadPath = $candidate;
            }
        } catch (\Throwable) {
            $autoloadPath = null;
        }
        $script = <<<'PHP'
            $autoload = $argv[1] ?? '';
            $source = $argv[2] ?? '';
            $proxyPath = $argv[3] ?? '';
            $proxyClass = $argv[4] ?? '';
            $sourceClass = $argv[5] ?? '';
            if ($autoload !== '') {
                require_once $autoload;
            }
            require_once $source;
            require_once $proxyPath;
            $reflection = new ReflectionClass($proxyClass);
            $parent = $reflection->getParentClass();
            if (
                $reflection->getName() !== $proxyClass
                || $parent === false
                || $parent->getName() !== $sourceClass
                || realpath((string) $reflection->getFileName()) !== realpath($proxyPath)
            ) {
                exit(1);
            }
            PHP;
        if (
            $this->runSubprocess([
                PHP_BINARY,
                '-r',
                $script,
                $autoloadPath ?? '',
                $sourcePath,
                $path,
                $proxyClass,
                $sourceClass,
            ]) !== 0
        ) {
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::CLASS_MISMATCH);
        }
    }

    /** @param list<string> $command */
    private function runSubprocess(array $command): int
    {
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (
            !is_resource($process)
            || !isset($pipes[1], $pipes[2])
            || !is_resource($pipes[1])
            || !is_resource($pipes[2])
        ) {
            return -1;
        }
        foreach ([1, 2] as $index) {
            stream_set_blocking($pipes[$index], false);
        }
        $open = [1 => true, 2 => true];
        while ($open[1] || $open[2]) {
            $read = [];
            foreach ([1, 2] as $index) {
                if ($open[$index]) {
                    $read[] = $pipes[$index];
                }
            }
            if ($read !== []) {
                $write = null;
                $except = null;
                $selected = stream_select($read, $write, $except, 0, 100_000);
                if ($selected === false) {
                    $read = [];
                }
                foreach ($read as $pipe) {
                    if (feof($pipe)) {
                        foreach ([1, 2] as $index) {
                            if ($pipes[$index] === $pipe) {
                                $open[$index] = false;
                                break;
                            }
                        }
                        continue;
                    }
                    fread($pipe, 8192);
                }
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                foreach ([1, 2] as $index) {
                    while (!feof($pipes[$index])) {
                        fread($pipes[$index], 8192);
                    }
                    $open[$index] = false;
                }
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_get_status($process);
        $exitCode = proc_close($process);
        return $status['exitcode'] >= 0 ? $status['exitcode'] : $exitCode;
    }

    private function verifyHashes(string $directory, FrameworkProxyArtifactManifest $manifest): void
    {
        foreach ($manifest->files as $relative => $hash)
            if (!is_file($directory . '/' . $relative) || hash_file('sha256', $directory . '/' . $relative) !== $hash)
                throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::HASH_VERIFICATION);
    }

    private function assertInputHashes(array $hashes): void
    {
        foreach ($hashes as $name => $hash)
            if (
                !is_string($name)
                || preg_match('/^[A-Za-z0-9._-]+$/D', $name) !== 1
                || !is_string($hash)
                || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1
            )
                throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::INPUT_INVALID);
        ksort($hashes);
    }

    private function assertBuildId(string $buildId): void
    {
        if ($buildId === '' || preg_match('/^[A-Za-z0-9._-]+$/D', $buildId) !== 1)
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::BUILD_ID_INVALID);
    }

    private function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o755, true) && !is_dir($path))
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::DIRECTORY_IO);
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents, LOCK_EX) === false)
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::WRITE_IO);
    }

    private function rename(string $from, string $to): void
    {
        if (!rename($from, $to))
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::PUBLISH_IO);
    }

    private function writeIndex(string $root, string $active): void
    {
        if (!$this->validIndexName($active)) {
            throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::INDEX_INVALID);
        }
        $previous = null;
        $indexPath = $root . '/index.json';
        if (is_file($indexPath)) {
            $old = json_decode((string) file_get_contents($indexPath), true);
            if (
                !is_array($old)
                || array_diff(array_keys($old), ['schema_version', 'active', 'previous']) !== []
                || array_diff(['schema_version', 'active', 'previous'], array_keys($old)) !== []
                || $old['schema_version'] !== 1
                || !is_string($old['active'])
                || !$this->validIndexName($old['active'])
                || $old['previous'] !== null
                && (!is_string($old['previous']) || !$this->validIndexName($old['previous']))
            ) {
                throw new RuntimeException(FrameworkProxyArtifactDiagnosticCode::INDEX_INVALID);
            }
            $previous = $old['active'] !== $active ? $old['active'] : $old['previous'];
        }
        $temporary = $indexPath . '.staging-' . bin2hex(random_bytes(6));
        try {
            $this->write(
                $temporary,
                (string) json_encode([
                    'schema_version' => 1,
                    'active' => $active,
                    'previous' => $previous,
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
            $this->rename($temporary, $indexPath);
        } catch (\Throwable $exception) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw $exception;
        }
    }

    private function cleanupOldBuilds(string $root): void
    {
        $index = json_decode((string) file_get_contents($root . '/index.json'), true);
        $keep = is_array($index)
            ? array_filter([$index['active'] ?? null, $index['previous'] ?? null], 'is_string')
            : [];
        foreach (glob($root . '/*') ?: [] as $path)
            if (is_dir($path) && is_file($path . '/manifest.json') && !in_array(basename($path), $keep, true))
                $this->removeTree($path);
    }

    private function fileName(string $class): string
    {
        return str_replace('\\', '_', ltrim($class, '\\')) . '.php';
    }

    private function validIndexName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+-[a-f0-9]{64}$/D', $name) === 1;
    }

    private function removeTree(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..')
                continue;
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($directory);
    }
}
