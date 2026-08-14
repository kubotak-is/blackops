<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

require_once __DIR__ . '/../../Fixtures/Aop/FrameworkProxyContract/ContractFixtures.php';

use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactPublisher;
use BlackOps\Internal\Runtime\ProxyProfileArtifactLoader;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyContract\ComplexTypeService;
use PHPUnit\Framework\TestCase;

final class ProxyProfileArtifactLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-proxy-loader-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testLoadsTheImmutableFrameworkUnitAndRejectsIdentityMismatch(): void
    {
        $frameworkRoot = $this->root . '/framework-proxies';
        mkdir($frameworkRoot, 0o755, true);
        $generation = new FrameworkProxyGenerator()->generate(
            ComplexTypeService::class,
            'loader-build',
            $frameworkRoot,
        );
        $manifest = new ProxyProfileArtifactPublisher()->publishFramework(
            $this->root . '/proxy-profiles',
            'loader-build',
            $generation->directory,
            $generation->manifest,
        );
        $unit = $this->root . '/proxy-profiles/loader-build-' . $manifest->contentHash;
        $loaded = new ProxyProfileArtifactLoader()->load($unit, 'loader-build', $manifest->contentHash);
        self::assertSame($manifest->contentHash, $loaded->contentHash);

        $this->expectExceptionMessage('Proxy profile artifact identity is invalid.');
        new ProxyProfileArtifactLoader()->load($unit, 'other-build', $manifest->contentHash);
    }

    public function testPreviousAndCurrentFrameworkUnitsRemainIndependent(): void
    {
        $frameworkRoot = $this->root . '/framework-proxies';
        mkdir($frameworkRoot, 0o755, true);
        $generator = new FrameworkProxyGenerator();
        $previous = $generator->generate(ComplexTypeService::class, 'previous-build', $frameworkRoot);
        $current = $generator->generate(ComplexTypeService::class, 'current-build', $frameworkRoot);
        $publisher = new ProxyProfileArtifactPublisher();
        $previousUnit = $publisher->publishFramework(
            $this->root . '/proxy-profiles',
            'previous-build',
            $previous->directory,
            $previous->manifest,
        );
        $currentUnit = $publisher->publishFramework(
            $this->root . '/proxy-profiles',
            'current-build',
            $current->directory,
            $current->manifest,
        );
        self::assertNotSame($previousUnit->contentHash, $currentUnit->contentHash);
        self::assertSame(
            $previousUnit->contentHash,
            new ProxyProfileArtifactLoader()->load(
                $this->root . '/proxy-profiles/previous-build-' . $previousUnit->contentHash,
                'previous-build',
                $previousUnit->contentHash,
            )->contentHash,
        );
        self::assertSame(
            $currentUnit->contentHash,
            new ProxyProfileArtifactLoader()->load(
                $this->root . '/proxy-profiles/current-build-' . $currentUnit->contentHash,
                'current-build',
                $currentUnit->contentHash,
            )->contentHash,
        );
    }

    public function testRejectsSymlinkWrongHashAndExtraInventoryBeforeDelegation(): void
    {
        $publisher = new ProxyProfileArtifactPublisher();
        $manifest = $publisher->publishFramework($this->root . '/proxy-profiles', 'loader-guards', null, null);
        $unit = $this->root . '/proxy-profiles/loader-guards-' . $manifest->contentHash;
        $loader = new ProxyProfileArtifactLoader();

        try {
            $loader->load($unit, 'other-build', $manifest->contentHash);
            self::fail('Expected build identity rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Proxy profile artifact identity is invalid.', $exception->getMessage());
        }
        try {
            $loader->load($unit, 'loader-guards', str_repeat('0', 64));
            self::fail('Expected content hash rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Proxy profile artifact identity is invalid.', $exception->getMessage());
        }

        file_put_contents($unit . '/extra.php', "<?php\n");
        try {
            $loader->load($unit, 'loader-guards', $manifest->contentHash);
            self::fail('Expected extra inventory rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Framework proxy artifact inventory is invalid.', $exception->getMessage());
        }
        unlink($unit . '/extra.php');
        mkdir($unit . '/nested', 0o755);
        try {
            $loader->load($unit, 'loader-guards', $manifest->contentHash);
            self::fail('Expected nested inventory rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Framework proxy artifact inventory is invalid.', $exception->getMessage());
        }
        rmdir($unit . '/nested');

        $saved = $this->root . '/proxy-profiles/loader-guards-saved';
        rename($unit, $saved);
        symlink($saved, $unit);
        $this->expectExceptionMessage('Proxy profile artifact unit is invalid.');
        $loader->load($unit, 'loader-guards', $manifest->contentHash);
    }

    private function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->remove($path) : unlink($path);
        }
        rmdir($directory);
    }
}
