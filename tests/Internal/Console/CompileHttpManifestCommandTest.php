<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Console\CompileHttpManifestCommand;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CompileHttpManifestCommandTest extends TestCase
{
    public function testCompilesAndDumpsHttpManifestFromProviderConfig(): void
    {
        $config = $this->configPath();
        $output = $this->manifestPath();
        file_put_contents($config, '<?php return [\\' . HttpCommandOperationProvider::class . '::class];');

        $status = new CommandTester(new CompileHttpManifestCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--application-build-id' => 'build-http-command',
        ]);

        $artifact = new HttpOperationManifestFile()->loadArtifact($output);
        $match = $artifact->manifest->toRegistry([new HttpCommandOperation()])->match('GET', '/command-http');

        self::assertSame(0, $status);
        self::assertFileExists($output);
        self::assertSame(2, $artifact->schemaVersion);
        self::assertSame('build-http-command', $artifact->applicationBuildId);
        self::assertSame('command.http', $artifact->manifest->dispatcherData[0]['GET']['/command-http']);
        self::assertNotNull($match);
        self::assertSame(HttpCommandValue::class, $match->route->value);
    }

    public function testRejectsMissingProviderConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileHttpManifestCommand())->execute([
            'config' => $this->configPath(),
            'output' => $this->manifestPath(),
            '--application-build-id' => 'build-http-command',
        ]);
    }

    public function testRejectsMissingApplicationBuildId(): void
    {
        $config = $this->configPath();
        file_put_contents($config, '<?php return [\\' . HttpCommandOperationProvider::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileHttpManifestCommand())->execute([
            'config' => $config,
            'output' => $this->manifestPath(),
        ]);
    }

    private function configPath(): string
    {
        return sys_get_temp_dir() . '/blackops-http-manifest-config-' . bin2hex(random_bytes(8)) . '.php';
    }

    private function manifestPath(): string
    {
        return sys_get_temp_dir() . '/blackops-http-manifest-command-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class HttpCommandOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [HttpCommandOperation::class];
    }
}

#[Route('GET', '/command-http')]
#[OperationType('command.http')]
#[Accepts(HttpCommandValue::class)]
#[HandledBy(HttpCommandHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class HttpCommandOperation implements Operation {}

final readonly class HttpCommandValue implements OperationValue {}

final readonly class HttpCommandHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
