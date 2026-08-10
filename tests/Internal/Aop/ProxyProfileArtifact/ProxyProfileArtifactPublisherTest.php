<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\ProxyProfileArtifact;

require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactManifest;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactPublisher;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ComplexTypeService;
use PHPUnit\Framework\TestCase;

final class ProxyProfileArtifactPublisherTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-proxy-unit-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testZeroTargetFrameworkUnitIsPublished(): void
    {
        $manifest = new ProxyProfileArtifactPublisher()->publishFramework(
            $this->root . '/units',
            'zero-framework',
            null,
            null,
        );
        self::assertSame(FrameworkProxyProfile::FRAMEWORK, $manifest->profile->value);
        self::assertFileExists($this->root . '/units/zero-framework-' . $manifest->contentHash . '/manifest.json');
    }

    public function testRejectsUnsafeBuildId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProxyProfileArtifactPublisher()->publishFramework($this->root . '/units', '../unsafe', null, null);
    }

    public function testRejectsSymlinkedExistingTargetUnit(): void
    {
        $publisher = new ProxyProfileArtifactPublisher();
        $manifest = $publisher->publishFramework($this->root . '/units', 'symlink-target', null, null);
        $unit = $this->root . '/units/symlink-target-' . $manifest->contentHash;
        $saved = $this->root . '/units/symlink-target-saved';
        rename($unit, $saved);
        symlink($saved, $unit);

        $this->expectExceptionMessage('Proxy profile artifact unit is invalid.');
        $publisher->publishFramework($this->root . '/units', 'symlink-target', null, null);
    }

    public function testFrameworkFirstPublishRequiresExactSiblingAndManifestHash(): void
    {
        $frameworkRoot = $this->root . '/framework-proxies';
        mkdir($frameworkRoot, 0o755, true);
        $generation = new FrameworkProxyGenerator()->generate(
            ComplexTypeService::class,
            'framework-publish',
            $frameworkRoot,
        );
        $publisher = new ProxyProfileArtifactPublisher();
        $manifest = $publisher->publishFramework(
            $this->root . '/proxy-profiles',
            'framework-publish',
            $generation->directory,
            $generation->manifest,
        );
        self::assertSame(FrameworkProxyProfile::FRAMEWORK, $manifest->profile->value);
        $badManifest = new FrameworkProxyArtifactManifest(
            $generation->manifest->schemaVersion,
            $generation->manifest->applicationBuildId,
            $generation->manifest->profile,
            $generation->manifest->generatorVersion,
            $generation->manifest->phpVersion,
            $generation->manifest->inputHash,
            $generation->manifest->proxies,
            $generation->manifest->files,
            $generation->manifest->classMap,
            $generation->manifest->sourceInputs,
            str_repeat('0', 64),
        );
        try {
            $publisher->publishFramework(
                $this->root . '/proxy-profiles-bad',
                'framework-publish',
                $generation->directory,
                $badManifest,
            );
            self::fail('Expected manifest canonical hash rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Framework proxy artifact manifest identity is invalid.', $exception->getMessage());
        }
        mkdir($this->root . '/alt', 0o755, true);
        $wrongRoot = $this->root . '/alt/wrong-profiles';
        mkdir($wrongRoot, 0o755, true);
        $this->expectExceptionMessage('Framework proxy artifact directory location is invalid.');
        $publisher->publishFramework($wrongRoot, 'framework-publish', $generation->directory, $generation->manifest);
    }

    private function remove(string $directory): void
    {
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
