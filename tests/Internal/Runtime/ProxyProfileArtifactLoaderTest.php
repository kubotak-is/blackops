<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\ProxyProfileArtifact\ProxyProfileArtifactPublisher;
use BlackOps\Internal\Runtime\ProxyProfileArtifactLoader;
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

    public function testRejectsTamperedInventoryBeforeExecutingProxyCode(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php\nclass ProxyLoaderFixture {}\n");
        $manifest = new ProxyProfileArtifactPublisher()->publishRay($this->root . '/units', 'build-loader', [$source]);
        $unit = $this->root . '/units/build-loader-' . $manifest->contentHash;
        file_put_contents($unit . '/aop/generated.php', "<?php\nthrow new RuntimeException('executed');\n");
        $this->expectExceptionMessage('Ray proxy artifact file integrity is invalid.');
        new ProxyProfileArtifactLoader()->load(
            $unit,
            'build-loader',
            $manifest->contentHash,
            FrameworkProxyProfile::RAY,
        );
    }

    public function testRejectsAnExtraInventoryEntry(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php\nclass ProxyLoaderExtraFixture {}\n");
        $manifest = new ProxyProfileArtifactPublisher()->publishRay($this->root . '/units', 'build-extra', [$source]);
        $unit = $this->root . '/units/build-extra-' . $manifest->contentHash;
        file_put_contents($unit . '/aop/extra.php', "<?php class ExtraFixture {}\n");
        $this->expectExceptionMessage('Ray proxy artifact inventory is invalid.');
        new ProxyProfileArtifactLoader()->load(
            $unit,
            'build-extra',
            $manifest->contentHash,
            FrameworkProxyProfile::RAY,
        );
    }

    public function testPreviousAndCurrentUnitsRemainIndependent(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php\nclass ProxyLoaderRollbackFixture {}\n");
        $publisher = new ProxyProfileArtifactPublisher();
        $previous = $publisher->publishRay($this->root . '/units', 'previous-build', [$source]);
        file_put_contents($source, "<?php\nclass ProxyLoaderRollbackFixtureCurrent {}\n");
        $current = $publisher->publishRay($this->root . '/units', 'current-build', [$source]);
        self::assertNotSame($previous->contentHash, $current->contentHash);
    }

    public function testRejectsUnitSymlinkAndBuildProfileHashMismatches(): void
    {
        $source = $this->root . '/generated.php';
        file_put_contents($source, "<?php\nclass ProxyLoaderMismatchFixture {}\n");
        $manifest = new ProxyProfileArtifactPublisher()->publishRay(
            $this->root . '/units',
            'mismatch-build',
            [$source],
        );
        $unit = $this->root . '/units/mismatch-build-' . $manifest->contentHash;
        $loader = new ProxyProfileArtifactLoader();
        try {
            $loader->load($unit, 'other-build', $manifest->contentHash, FrameworkProxyProfile::RAY);
            self::fail('Expected build mismatch.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Proxy profile artifact identity is invalid.', $exception->getMessage());
        }
        try {
            $loader->load($unit, 'mismatch-build', $manifest->contentHash, FrameworkProxyProfile::FRAMEWORK);
            self::fail('Expected profile mismatch.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Proxy profile artifact identity is invalid.', $exception->getMessage());
        }
        try {
            $loader->load($unit, 'mismatch-build', str_repeat('0', 64), FrameworkProxyProfile::RAY);
            self::fail('Expected hash mismatch.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Proxy profile artifact hash is invalid.', $exception->getMessage());
        }
        $saved = $this->root . '/units/mismatch-saved';
        rename($unit, $saved);
        symlink($saved, $unit);
        try {
            $loader->load($unit, 'mismatch-build', $manifest->contentHash, FrameworkProxyProfile::RAY);
            self::fail('Expected unit symlink rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Proxy profile artifact unit is invalid.', $exception->getMessage());
        }
    }

    public function testRejectsPreloadedNamedIdentityFromAnotherFile(): void
    {
        $first = $this->root . '/first.php';
        $second = $this->root . '/second.php';
        file_put_contents($first, "<?php\nclass ProxyLoaderCollisionFixture {}\n");
        file_put_contents($second, "<?php\nclass ProxyLoaderCollisionFixture {}\n");
        $publisher = new ProxyProfileArtifactPublisher();
        $firstManifest = $publisher->publishRay($this->root . '/units', 'collision-first', [$first]);
        new ProxyProfileArtifactLoader()->load(
            $this->root . '/units/collision-first-' . $firstManifest->contentHash,
            'collision-first',
            $firstManifest->contentHash,
            FrameworkProxyProfile::RAY,
        );
        $secondManifest = $publisher->publishRay($this->root . '/units', 'collision-second', [$second]);
        $this->expectExceptionMessage('Ray proxy artifact class identity collides.');
        new ProxyProfileArtifactLoader()->load(
            $this->root . '/units/collision-second-' . $secondManifest->contentHash,
            'collision-second',
            $secondManifest->contentHash,
            FrameworkProxyProfile::RAY,
        );
    }

    public function testTokenIdentityPreflightAllowsClassConstantAndRejectsAnonymousOnlyFile(): void
    {
        $valid = $this->root . '/class-constant.php';
        file_put_contents($valid, "<?php\nclass ProxyLoaderTokenFixture {}\n" . '$name = SomeClass::class;' . "\n");
        $manifest = new ProxyProfileArtifactPublisher()->publishRay($this->root . '/units', 'token-valid', [$valid]);
        $loaded = new ProxyProfileArtifactLoader()->load(
            $this->root . '/units/token-valid-' . $manifest->contentHash,
            'token-valid',
            $manifest->contentHash,
            FrameworkProxyProfile::RAY,
        );
        self::assertSame($manifest->contentHash, $loaded->contentHash);

        $anonymous = $this->root . '/anonymous.php';
        file_put_contents($anonymous, "<?php\n" . '$object = new class {};' . "\n");
        $anonymousManifest = new ProxyProfileArtifactPublisher()->publishRay(
            $this->root . '/units',
            'token-anonymous',
            [$anonymous],
        );
        $this->expectExceptionMessage('Ray proxy artifact class identity is missing.');
        new ProxyProfileArtifactLoader()->load(
            $this->root . '/units/token-anonymous-' . $anonymousManifest->contentHash,
            'token-anonymous',
            $anonymousManifest->contentHash,
            FrameworkProxyProfile::RAY,
        );
    }

    public function testLaterSyntaxInvalidFileIsPreflightedBeforeEarlierCodeExecutes(): void
    {
        $marker = $this->root . '/executed.marker';
        $first = $this->root . '/first.php';
        $second = $this->root . '/second.php';
        file_put_contents(
            $first,
            "<?php\nclass ProxyLoaderPreflightFixture {}\nfile_put_contents("
            . var_export($marker, true)
            . ", 'executed');\n",
        );
        file_put_contents($second, "<?php\nclass ProxyLoaderSyntaxFixture {\n");
        $manifest = new ProxyProfileArtifactPublisher()->publishRay(
            $this->root . '/units',
            'preflight-invalid',
            [$first, $second],
        );
        try {
            new ProxyProfileArtifactLoader()->load(
                $this->root . '/units/preflight-invalid-' . $manifest->contentHash,
                'preflight-invalid',
                $manifest->contentHash,
                FrameworkProxyProfile::RAY,
            );
            self::fail('Expected syntax preflight rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Ray proxy artifact syntax is invalid.', $exception->getMessage());
        }
        self::assertFileDoesNotExist($marker);
    }

    public function testRejectsNestedDirectoryAndSymlinkInventory(): void
    {
        $source = $this->root . '/inventory.php';
        file_put_contents($source, "<?php\nclass ProxyLoaderInventoryFixture {}\n");
        $manifest = new ProxyProfileArtifactPublisher()->publishRay($this->root . '/units', 'inventory', [$source]);
        $unit = $this->root . '/units/inventory-' . $manifest->contentHash;
        mkdir($unit . '/aop/nested', 0o755, true);
        try {
            new ProxyProfileArtifactLoader()->load(
                $unit,
                'inventory',
                $manifest->contentHash,
                FrameworkProxyProfile::RAY,
            );
            self::fail('Expected nested inventory rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Ray proxy artifact inventory is invalid.', $exception->getMessage());
        }
        rmdir($unit . '/aop/nested');
        unlink($unit . '/aop/inventory.php');
        symlink($source, $unit . '/aop/inventory.php');
        try {
            new ProxyProfileArtifactLoader()->load(
                $unit,
                'inventory',
                $manifest->contentHash,
                FrameworkProxyProfile::RAY,
            );
            self::fail('Expected symlink inventory rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Ray proxy artifact file integrity is invalid.', $exception->getMessage());
        }
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
