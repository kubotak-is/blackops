<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend;

use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrontendContractManifestFileTest extends TestCase
{
    public function testWritesAndLoadsVersionedArtifactAtomically(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-frontend-file-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/frontend.php';
        $file = new FrontendContractManifestFile();
        $file->write(new FrontendContractManifest([]), $path, 'frontend-file-build');

        $artifact = $file->loadArtifact($path);
        self::assertSame(FrontendContractManifestFile::SCHEMA_VERSION, $artifact->schemaVersion);
        self::assertSame('frontend-file-build', $artifact->applicationBuildId);
        self::assertSame([], $artifact->manifest->operations);
        self::assertSame([], glob($directory . '/frontend-manifest-*') ?: []);
    }

    public function testRejectsUnsupportedSchema(): void
    {
        $path = sys_get_temp_dir() . '/blackops-frontend-invalid-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents(
            $path,
            "<?php return ['schemaVersion' => 999, 'applicationBuildId' => 'build', 'payload' => ['operations' => []]];",
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('schema version');
        new FrontendContractManifestFile()->loadArtifact($path);
    }

    public function testInvalidWritePreservesExistingArtifactAndCleansTemporaryFile(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-frontend-preserve-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/frontend.php';
        $file = new FrontendContractManifestFile();
        $file->write(new FrontendContractManifest([]), $path, 'preserved-build');
        $before = file_get_contents($path);

        try {
            $file->write(new FrontendContractManifest([]), $path, '');
            self::fail('Expected invalid frontend build ID.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('build ID', $exception->getMessage());
        }

        self::assertSame($before, file_get_contents($path));
        self::assertSame([], glob($directory . '/frontend-manifest-*') ?: []);
        self::assertSame('preserved-build', $file->loadArtifact($path)->applicationBuildId);
    }
}
