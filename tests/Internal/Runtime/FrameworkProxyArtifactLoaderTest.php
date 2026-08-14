<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Runtime\FrameworkProxyArtifactLoader;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ComplexTypeService;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ReadonlyService;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyArtifactLoaderTest extends TestCase
{
    public function testRejectsBuildAndProfileMismatch(): void
    {
        $root = sys_get_temp_dir() . '/blackops-loader-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $result = new FrameworkProxyGenerator()->generate(ComplexTypeService::class, 'loader-build', $root);
            $this->expectException(\InvalidArgumentException::class);
            new FrameworkProxyArtifactLoader()->load(
                $result->directory,
                'other-build',
                $result->manifest->manifestHash,
                'framework',
            );
        } finally {
            foreach (glob($root . '/*') ?: [] as $directory) {
                if (is_file($directory)) {
                    unlink($directory);
                    continue;
                }
                foreach (glob($directory . '/proxies/*') ?: [] as $file)
                    unlink($file);
                if (is_dir($directory . '/proxies'))
                    rmdir($directory . '/proxies');
                if (is_file($directory . '/manifest.json'))
                    unlink($directory . '/manifest.json');
                rmdir($directory);
            }
            rmdir($root);
        }
    }

    public function testRepeatedLoadReusesExactLoadedIdentityAndRejectsAnotherPath(): void
    {
        [$root, $result] = $this->artifact('loader-idempotent');
        try {
            $loader = new FrameworkProxyArtifactLoader();
            $loader->load($result->directory, 'loader-idempotent', $result->manifest->manifestHash, 'framework');
            $loader->load($result->directory, 'loader-idempotent', $result->manifest->manifestHash, 'framework');

            $copyRoot = $root . '/copy';
            $copyDirectory = $copyRoot . '/' . basename($result->directory);
            mkdir($copyDirectory . '/proxies', 0o755, true);
            copy($result->directory . '/manifest.json', $copyDirectory . '/manifest.json');
            copy(
                (string) glob($result->directory . '/proxies/*.php')[0],
                $copyDirectory . '/proxies/' . basename((string) glob($result->directory . '/proxies/*.php')[0]),
            );
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MAP_MISMATCH',
                $copyDirectory,
                'loader-idempotent',
                $result->manifest->manifestHash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsWrongExpectedManifestHashWithStableCode(): void
    {
        $root = sys_get_temp_dir() . '/blackops-loader-hash-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $result = new FrameworkProxyGenerator()->generate(ComplexTypeService::class, 'hash-build', $root);
            try {
                new FrameworkProxyArtifactLoader()->load(
                    $result->directory,
                    'hash-build',
                    str_repeat('0', 64),
                    'framework',
                );
                self::fail('Expected manifest hash rejection.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('BO_PROXY_ARTIFACT_MANIFEST_HASH', $exception->getMessage());
            }
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsProfileMismatchWithStableCode(): void
    {
        [$root, $result] = $this->artifact('profile-code');
        try {
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $payload['profile'] = 'ray';
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_PROFILE_MISMATCH',
                $result->directory,
                'profile-code',
                $hash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsVersionAndSchemaTypesWithStableCodes(): void
    {
        [$root, $result] = $this->artifact('version-code');
        try {
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $payload['generator_version'] = 'other-generator';
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_VERSION_MISMATCH',
                $result->directory,
                'version-code',
                $hash,
                'framework',
            );
            $this->remove($root);
            [$root, $result] = $this->artifact('schema-code');
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $payload['schema_version'] = '1';
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_VERSION_MISMATCH',
                $result->directory,
                'schema-code',
                $hash,
                'framework',
            );
            $this->remove($root);
            [$root, $result] = $this->artifact('input-code');
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $payload['input_hash'] = str_repeat('f', 64);
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                $result->directory,
                'input-code',
                $hash,
                'framework',
            );
            $this->remove($root);
            [$root, $result] = $this->artifact('extra-key-code');
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $payload['unexpected'] = true;
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                $result->directory,
                'extra-key-code',
                $hash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsSourcePathAndClassMapMutationsWithStableCodes(): void
    {
        [$root, $result] = $this->artifact('map-code');
        try {
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $payload['proxies'][0]['source_path'] = 'source.php';
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                $result->directory,
                'map-code',
                $hash,
                'framework',
            );
            $this->remove($root);
            [$root, $result] = $this->artifact('map-code-2');
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $source = $payload['proxies'][0]['source_class'];
                $payload['class_map'][$source] = 'Invalid\\MappedProxy';
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MAP_MISMATCH',
                $result->directory,
                'map-code-2',
                $hash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsSourceInputTargetHashMismatch(): void
    {
        [$root, $result] = $this->artifact('source-input-code');
        try {
            $hash = $this->rewriteManifest($result->directory, static function (array $payload): array {
                $source = $payload['proxies'][0]['source_class'];
                $payload['source_inputs'][$source] = str_repeat('f', 64);
                return $payload;
            });
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MAP_MISMATCH',
                $result->directory,
                'source-input-code',
                $hash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsDirectoryAndGeneratedFileIdentityMutations(): void
    {
        [$root, $result] = $this->artifact('identity-code');
        try {
            $renamed = $root . '/identity-code-wrong-directory';
            rename($result->directory, $renamed);
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                $renamed,
                'identity-code',
                $result->manifest->manifestHash,
                'framework',
            );
            rename($renamed, $result->directory);
            $proxy = (string) glob($result->directory . '/proxies/*.php')[0];
            file_put_contents($proxy, "<?php\n");
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_FILE_HASH',
                $result->directory,
                'identity-code',
                $result->manifest->manifestHash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsUnexpectedInventoryEntriesAndSymlinks(): void
    {
        foreach (['hidden' => '.hidden', 'nested' => 'proxies/nested/extra.php'] as $build => $extra) {
            [$root, $result] = $this->artifact('inventory-' . $build);
            try {
                $path = $result->directory . '/' . $extra;
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0o755, true);
                }
                file_put_contents($path, 'extra');
                $this->expectLoaderCode(
                    'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                    $result->directory,
                    'inventory-' . $build,
                    $result->manifest->manifestHash,
                    'framework',
                );
            } finally {
                $this->remove($root);
            }
        }
    }

    public function testRejectsProxyAndManifestSymlinks(): void
    {
        [$root, $result] = $this->artifact('symlink-proxy');
        try {
            $proxy = (string) glob($result->directory . '/proxies/*.php')[0];
            $target = $root . '/proxy-target.php';
            file_put_contents($target, (string) file_get_contents($proxy));
            unlink($proxy);
            symlink($target, $proxy);
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                $result->directory,
                'symlink-proxy',
                $result->manifest->manifestHash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }

        [$root, $result] = $this->artifact('symlink-manifest');
        try {
            $target = $root . '/manifest-target.json';
            copy($result->directory . '/manifest.json', $target);
            unlink($result->directory . '/manifest.json');
            symlink($target, $result->directory . '/manifest.json');
            $this->expectLoaderCode(
                'BO_PROXY_ARTIFACT_MANIFEST_INVALID',
                $result->directory,
                'symlink-manifest',
                $result->manifest->manifestHash,
                'framework',
            );
        } finally {
            $this->remove($root);
        }
    }

    public function testRejectsInvalidLaterMapBeforeExecutingEarlierFile(): void
    {
        $root = sys_get_temp_dir() . '/blackops-loader-preflight-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $result = new FrameworkProxyGenerator()->generateBatch(
                [ComplexTypeService::class, ReadonlyService::class],
                'preflight-build',
                $root,
            );
            $payload = $result->manifest->toArray();
            $first = $payload['proxies'][0];
            $marker = $root . '/executed';
            $malicious = "<?php\nfile_put_contents(" . var_export($marker, true) . ", 'executed');\n";
            file_put_contents($result->directory . '/' . $first['path'], $malicious);
            $hash = hash_file('sha256', $result->directory . '/' . $first['path']);
            $payload['files'][$first['path']] = $hash;
            $payload['proxies'][0]['hash'] = $hash;
            $payload['class_map'][$payload['proxies'][1]['source_class']] = 'Invalid\\MappedProxy';
            unset($payload['manifest_hash']);
            $payload['manifest_hash'] = hash(
                'sha256',
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            file_put_contents(
                $result->directory . '/manifest.json',
                (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );

            try {
                new FrameworkProxyArtifactLoader()->load(
                    $result->directory,
                    'preflight-build',
                    $payload['manifest_hash'],
                    'framework',
                );
                self::fail('Expected invalid map to be rejected.');
            } catch (\InvalidArgumentException) {
                self::assertFileDoesNotExist($marker);
            }
        } finally {
            $this->remove($root);
        }
    }

    public function testLoadsEmptyArtifactWithoutSourceScan(): void
    {
        $root = sys_get_temp_dir() . '/blackops-loader-empty-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        try {
            $result = new FrameworkProxyGenerator()->generateBatch([], 'empty-build', $root);
            $manifest = new FrameworkProxyArtifactLoader()->load(
                $result->directory,
                'empty-build',
                $result->manifest->manifestHash,
                'framework',
            );
            self::assertSame([], $manifest->proxies);
            self::assertSame([], $manifest->files);
            self::assertSame([], $manifest->classMap);
        } finally {
            $this->remove($root);
        }
    }

    public function testRuntimeLoadDoesNotScanRemovedApplicationSource(): void
    {
        $root = sys_get_temp_dir() . '/blackops-loader-noscan-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        $source = $root . '/NoScanService.php';
        file_put_contents(
            $source,
            "<?php\n#[\\BlackOps\\Database\\Attribute\\Transactional]\nclass NoScanService { public function run(): void {} }\n",
        );
        require_once $source;
        try {
            $result = new FrameworkProxyGenerator()->generate('NoScanService', 'noscan-build', $root);
            unlink($source);
            $manifest = new FrameworkProxyArtifactLoader()->load(
                $result->directory,
                'noscan-build',
                $result->manifest->manifestHash,
                'framework',
            );
            self::assertSame($result->manifest->manifestHash, $manifest->manifestHash);
        } finally {
            $this->remove($root);
        }
    }

    /** @return array{0:string,1:\BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerationResult} */
    private function artifact(string $build): array
    {
        $root = sys_get_temp_dir() . '/blackops-loader-matrix-' . bin2hex(random_bytes(5));
        mkdir($root, 0o755, true);
        return [$root, new FrameworkProxyGenerator()->generate(ComplexTypeService::class, $build, $root)];
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $mutate */
    private function rewriteManifest(string $directory, callable $mutate): string
    {
        $payload = json_decode(
            (string) file_get_contents($directory . '/manifest.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $payload = $mutate($payload);
        unset($payload['manifest_hash']);
        $payload['manifest_hash'] = hash(
            'sha256',
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $directory . '/manifest.json',
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
        return $payload['manifest_hash'];
    }

    private function expectLoaderCode(
        string $code,
        string $directory,
        string $build,
        string $manifestHash,
        string $profile,
    ): void {
        try {
            new FrameworkProxyArtifactLoader()->load($directory, $build, $manifestHash, $profile);
            self::fail('Expected loader rejection with ' . $code);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($code, $exception->getMessage());
        }
    }

    private function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->remove($path) : unlink($path);
        }
        rmdir($directory);
    }
}
