<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Registry\OperationManifestFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationManifestFileTest extends TestCase
{
    public function testWritesAndLoadsOperationManifestFile(): void
    {
        $path = $this->manifestPath();
        $file = new OperationManifestFile();
        $registry = new OperationRegistry([$this->metadata()]);

        $file->write($registry, $path, 'build-operation-123');
        $artifact = $file->loadArtifact($path);

        self::assertFileExists($path);
        self::assertSame(OperationManifestFile::SCHEMA_VERSION, $artifact->schemaVersion);
        self::assertSame('build-operation-123', $artifact->applicationBuildId);
        self::assertSame(
            ManifestFileOperation::class,
            $artifact->operations->findByTypeId('manifest.file')?->definition,
        );
    }

    public function testRejectsMissingManifestFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->load($this->manifestPath());
    }

    public function testRejectsInvalidManifestReturnValue(): void
    {
        $path = $this->manifestPath();
        file_put_contents($path, "<?php return 'invalid';");

        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->load($path);
    }

    public function testRejectsInvalidMetadataShape(): void
    {
        $path = $this->manifestPath();
        file_put_contents(
            $path,
            "<?php return ['schemaVersion' => 1, 'applicationBuildId' => 'build-1', 'payload' => ['operations' => [['typeId' => 'broken']]]];",
        );

        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->load($path);
    }

    public function testRejectsManifestWithoutSchemaVersion(): void
    {
        $path = $this->manifestPath();
        file_put_contents(
            $path,
            "<?php return ['applicationBuildId' => 'build-1', 'payload' => ['operations' => []]];",
        );

        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->load($path);
    }

    public function testRejectsManifestWithUnsupportedSchemaVersion(): void
    {
        $path = $this->manifestPath();
        file_put_contents(
            $path,
            "<?php return ['schemaVersion' => 2, 'applicationBuildId' => 'build-1', 'payload' => ['operations' => []]];",
        );

        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->load($path);
    }

    public function testRejectsManifestWithoutApplicationBuildId(): void
    {
        $path = $this->manifestPath();
        file_put_contents($path, "<?php return ['schemaVersion' => 1, 'payload' => ['operations' => []]];");

        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->load($path);
    }

    public function testRejectsEmptyApplicationBuildIdWhenWriting(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationManifestFile()->write(new OperationRegistry([$this->metadata()]), $this->manifestPath(), '');
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'manifest.file',
            ManifestFileOperation::class,
            ManifestFileValue::class,
            ManifestFileHandler::class,
            EmptyOutcome::class,
            Inline::class,
        );
    }

    private function manifestPath(): string
    {
        return sys_get_temp_dir() . '/blackops-operation-manifest-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class ManifestFileOperation implements Operation {}

final readonly class ManifestFileValue implements OperationValue {}

final readonly class ManifestFileHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
