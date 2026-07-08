<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Console\DumpHttpManifestCommand;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DumpHttpManifestCommandTest extends TestCase
{
    public function testDumpsHttpManifestFile(): void
    {
        $path = $this->manifestPath();
        $definition = new CliManifestOperation();
        $file = new HttpOperationManifestFile();
        $command = new DumpHttpManifestCommand(new OperationRegistry([$this->metadata()]), [$definition], $file);

        $status = new CommandTester($command)->execute(['output' => $path]);

        self::assertSame(0, $status);
        self::assertFileExists($path);
        self::assertSame('cli.manifest', $file->load($path)->toArray()['routes']['GET']['/cli-manifest']);
    }

    public function testDumpedManifestCanRebuildRouteRegistry(): void
    {
        $path = $this->manifestPath();
        $definition = new CliManifestOperation();
        $file = new HttpOperationManifestFile();
        $command = new DumpHttpManifestCommand(new OperationRegistry([$this->metadata()]), [$definition], $file);

        new CommandTester($command)->execute(['output' => $path]);

        $match = $file->load($path)->toRegistry([$definition])->match('GET', '/cli-manifest');

        self::assertNotNull($match);
        self::assertSame(CliManifestValue::class, $match->route->value);
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'cli.manifest',
            CliManifestOperation::class,
            CliManifestValue::class,
            CliManifestHandler::class,
            EmptyOutcome::class,
            Inline::class,
        );
    }

    private function manifestPath(): string
    {
        return sys_get_temp_dir() . '/blackops-cli-http-manifest-' . bin2hex(random_bytes(8)) . '.php';
    }
}

#[Route('GET', '/cli-manifest')]
#[OperationType('cli.manifest')]
#[Accepts(CliManifestValue::class)]
#[HandledBy(CliManifestHandler::class)]
#[Returns(EmptyOutcome::class)]
final class CliManifestOperation implements Operation {}

final readonly class CliManifestValue implements OperationValue {}

final class CliManifestHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
