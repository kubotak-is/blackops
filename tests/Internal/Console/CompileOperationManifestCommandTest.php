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
use BlackOps\Internal\Console\CompileOperationManifestCommand;
use BlackOps\Internal\Registry\OperationManifestFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CompileOperationManifestCommandTest extends TestCase
{
    public function testCompilesAndDumpsOperationManifestFromProviderConfig(): void
    {
        $config = $this->configPath();
        $output = $this->manifestPath();
        file_put_contents($config, '<?php return [\\' . CommandOperationProvider::class . '::class];');

        $status = new CommandTester(new CompileOperationManifestCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--application-build-id' => 'build-operation-command',
        ]);

        $artifact = new OperationManifestFile()->loadArtifact($output);

        self::assertSame(0, $status);
        self::assertFileExists($output);
        self::assertSame('build-operation-command', $artifact->applicationBuildId);
        self::assertSame(
            CommandOperation::class,
            $artifact->operations->findByTypeId('command.operation')?->definition,
        );
    }

    public function testRejectsMissingProviderConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileOperationManifestCommand())->execute([
            'config' => $this->configPath(),
            'output' => $this->manifestPath(),
            '--application-build-id' => 'build-operation-command',
        ]);
    }

    public function testRejectsMissingApplicationBuildId(): void
    {
        $config = $this->configPath();
        file_put_contents($config, '<?php return [\\' . CommandOperationProvider::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileOperationManifestCommand())->execute([
            'config' => $config,
            'output' => $this->manifestPath(),
        ]);
    }

    private function configPath(): string
    {
        return sys_get_temp_dir() . '/blackops-operation-manifest-config-' . bin2hex(random_bytes(8)) . '.php';
    }

    private function manifestPath(): string
    {
        return sys_get_temp_dir() . '/blackops-operation-manifest-command-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class CommandOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [CommandOperation::class];
    }
}

#[OperationType('command.operation')]
#[Accepts(CommandValue::class)]
#[HandledBy(CommandHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class CommandOperation implements Operation {}

final readonly class CommandValue implements OperationValue {}

final readonly class CommandHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
