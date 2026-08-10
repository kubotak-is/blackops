<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Runtime\FrameworkProxyProfileLoader;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ComplexTypeService;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyProfileLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-profile-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testLoadsTheExactBuildManifestUnit(): void
    {
        $generated = new FrameworkProxyGenerator()->generate(ComplexTypeService::class, 'build-profile', $this->root);
        $manifest = new FrameworkProxyProfileLoader()->loadFramework(
            $generated->directory,
            'build-profile',
            $generated->manifest->manifestHash,
        );
        self::assertSame($generated->manifest->manifestHash, $manifest->manifestHash);
    }

    public function testRejectsAProfileMismatchBeforeLoading(): void
    {
        $generated = new FrameworkProxyGenerator()->generate(
            ComplexTypeService::class,
            'build-profile-mismatch',
            $this->root,
        );
        $this->expectExceptionMessage('BO_PROXY_ARTIFACT_PROFILE_MISMATCH');
        new FrameworkProxyProfileLoader()->load(
            $generated->directory,
            'build-profile-mismatch',
            $generated->manifest->manifestHash,
            FrameworkProxyProfile::RAY,
        );
    }

    public function testPreviousCompleteBuildRemainsLoadableWithoutCrossingIdentity(): void
    {
        $generator = new FrameworkProxyGenerator();
        $previous = $generator->generate(ComplexTypeService::class, 'previous-complete-build', $this->root);
        $current = $generator->generate(ComplexTypeService::class, 'current-complete-build', $this->root);
        $loader = new FrameworkProxyProfileLoader();
        self::assertNotSame($previous->directory, $current->directory);
        self::assertNotSame(
            $previous->manifest->classMap[ComplexTypeService::class],
            $current->manifest->classMap[ComplexTypeService::class],
        );
        self::assertSame(
            $previous->manifest->manifestHash,
            $loader->loadFramework(
                $previous->directory,
                'previous-complete-build',
                $previous->manifest->manifestHash,
            )->manifestHash,
        );
        self::assertSame(
            $current->manifest->manifestHash,
            $loader->loadFramework(
                $current->directory,
                'current-complete-build',
                $current->manifest->manifestHash,
            )->manifestHash,
        );
        $this->expectExceptionMessage('BO_PROXY_ARTIFACT_BUILD_MISMATCH');
        $loader->loadFramework($previous->directory, 'current-complete-build', $previous->manifest->manifestHash);
    }

    public function testCrossBuildManifestHashIsRejected(): void
    {
        $generator = new FrameworkProxyGenerator();
        $previous = $generator->generate(ComplexTypeService::class, 'previous-hash-build', $this->root);
        $current = $generator->generate(ComplexTypeService::class, 'current-hash-build', $this->root);
        $this->expectExceptionMessage('BO_PROXY_ARTIFACT_MANIFEST_HASH');
        new FrameworkProxyProfileLoader()->loadFramework(
            $current->directory,
            'current-hash-build',
            $previous->manifest->manifestHash,
        );
    }

    private function remove(string $directory): void
    {
        if (!is_dir($directory))
            return;
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->remove($path) : unlink($path);
        }
        rmdir($directory);
    }
}
