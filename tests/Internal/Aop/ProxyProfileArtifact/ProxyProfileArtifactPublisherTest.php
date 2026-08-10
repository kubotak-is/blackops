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

    public function testRayUnitCopiesOnlyReturnedFilesAndHasStableIdentity(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php\nclass ProxyUnitFixture {}\n");
        $publisher = new ProxyProfileArtifactPublisher();
        $first = $publisher->publishRay($this->root . '/units', 'build-ray', [$source]);
        $second = $publisher->publishRay($this->root . '/units', 'build-ray', [$source]);
        self::assertSame($first->contentHash, $second->contentHash);
        self::assertSame(FrameworkProxyProfile::RAY, $first->profile->value);
        self::assertFileExists($this->root . '/units/build-ray-' . $first->contentHash . '/manifest.json');
        self::assertFileExists($this->root . '/units/build-ray-' . $first->contentHash . '/aop/generated.php');
    }

    public function testZeroTargetUnitsArePublishedForBothProfiles(): void
    {
        $publisher = new ProxyProfileArtifactPublisher();
        $ray = $publisher->publishRay($this->root . '/units', 'zero-ray', []);
        $framework = $publisher->publishFramework($this->root . '/units', 'zero-framework', null, null);
        self::assertSame(FrameworkProxyProfile::RAY, $ray->profile->value);
        self::assertSame(FrameworkProxyProfile::FRAMEWORK, $framework->profile->value);
        self::assertFileExists($this->root . '/units/zero-ray-' . $ray->contentHash . '/manifest.json');
        self::assertFileDoesNotExist($this->root . '/units/zero-ray-' . $ray->contentHash . '/aop');
        self::assertFileDoesNotExist($this->root . '/units/zero-framework-' . $framework->contentHash . '/aop');
    }

    public function testUnsafeBuildIdAndSymlinkSourceAreRejected(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php class UnsafeFixture {}\n");
        $publisher = new ProxyProfileArtifactPublisher();
        $this->expectException(\InvalidArgumentException::class);
        $publisher->publishRay($this->root . '/units', '../unsafe', [$source]);
    }

    public function testRejectsSymlinkSourceAndTargetUnit(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php class ProxySymlinkFixture {}\n");
        $sourceLink = $this->root . '/source-link.php';
        symlink($source, $sourceLink);
        $publisher = new ProxyProfileArtifactPublisher();
        try {
            $publisher->publishRay($this->root . '/units', 'symlink-source', [$sourceLink]);
            self::fail('Expected source symlink rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Ray proxy artifact file is invalid.', $exception->getMessage());
        }
        $manifest = $publisher->publishRay($this->root . '/units', 'symlink-target', [$source]);
        $unit = $this->root . '/units/symlink-target-' . $manifest->contentHash;
        $alias = $this->root . '/units/symlink-target-alias';
        symlink($unit, $alias);
        self::assertTrue(is_link($alias));
        self::assertFileExists($unit . '/manifest.json');
        self::assertNotSame(realpath($alias), $alias);
        $saved = $this->root . '/units/symlink-target-saved';
        rename($unit, $saved);
        symlink($saved, $unit);
        $this->expectExceptionMessage('Proxy profile artifact unit is invalid.');
        $publisher->publishRay($this->root . '/units', 'symlink-target', [$source]);
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
            if ($entry === '.' || $entry === '..')
                continue;
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->remove($path) : unlink($path);
        }
        rmdir($directory);
    }
}
