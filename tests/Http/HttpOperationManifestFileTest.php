<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HttpOperationManifestFileTest extends TestCase
{
    public function testWritesAndLoadsManifestFile(): void
    {
        $path = $this->manifestPath();
        $manifest = $this->manifest();
        $file = new HttpOperationManifestFile();

        $file->write($manifest, $path);

        self::assertFileExists($path);
        self::assertStringStartsWith('<?php', (string) file_get_contents($path));
        self::assertSame($manifest->toArray(), $file->load($path)->toArray());
    }

    public function testLoadedManifestCanRebuildRouteRegistry(): void
    {
        $path = $this->manifestPath();
        $file = new HttpOperationManifestFile();
        $file->write($this->manifest(), $path);

        $registry = $file->load($path)->toRegistry([new ManifestFileOperation()]);
        $match = $registry->match('GET', '/manifest');

        self::assertNotNull($match);
        self::assertSame('/manifest', $match->route->path);
    }

    public function testRejectsMissingManifestFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpOperationManifestFile()->load($this->manifestPath());
    }

    public function testRejectsManifestFileWithInvalidReturnValue(): void
    {
        $path = $this->manifestPath();
        file_put_contents($path, "<?php\n\nreturn 'invalid';\n");

        $this->expectException(InvalidArgumentException::class);

        new HttpOperationManifestFile()->load($path);
    }

    private function manifest(): HttpOperationManifest
    {
        return new HttpOperationManifest([
            'GET' => [
                '/manifest' => 'manifest.show',
            ],
        ], [
            'manifest.show' => [
                'definition' => ManifestFileOperation::class,
                'value' => ManifestFileValue::class,
                'handler' => ManifestFileHandler::class,
                'outcome' => EmptyOutcome::class,
                'strategy' => Inline::class,
            ],
        ]);
    }

    private function manifestPath(): string
    {
        return sys_get_temp_dir() . '/blackops-http-manifest-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final class ManifestFileOperation implements Operation {}

final readonly class ManifestFileValue implements OperationValue {}

final class ManifestFileHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
