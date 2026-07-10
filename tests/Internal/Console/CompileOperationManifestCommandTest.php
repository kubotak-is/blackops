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
use BlackOps\Tests\Internal\Console\Fixture\DevelopmentDeferredOperation;
use BlackOps\Tests\Internal\Console\Fixture\DevelopmentInlineOperation;
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

    public function testMergesProviderAndDiscoveryDefinitionsAndDeduplicatesTheSameDefinition(): void
    {
        $config = $this->configPath();
        $output = $this->manifestPath();
        file_put_contents($config, '<?php return [\\' . DevelopmentCommandOperationProvider::class . '::class];');

        $status = new CommandTester(new CompileOperationManifestCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--application-build-id' => 'build-development-operation-command',
            ...$this->discoveryOptions(),
        ]);

        $artifact = new OperationManifestFile()->loadArtifact($output);

        self::assertSame(0, $status);
        self::assertCount(3, $artifact->operations->all());
        self::assertSame(
            DevelopmentInlineOperation::class,
            $artifact->operations->findByTypeId('development.inline')?->definition,
        );
        self::assertSame(
            DevelopmentDeferredOperation::class,
            $artifact->operations->findByTypeId('development.deferred')?->definition,
        );
        self::assertSame(
            CommandOperation::class,
            $artifact->operations->findByTypeId('command.operation')?->definition,
        );
    }

    public function testRejectsDuplicateTypeIdAcrossProviderAndDiscovery(): void
    {
        $config = $this->configPath();
        file_put_contents($config, '<?php return [\\' . DuplicateDevelopmentTypeProvider::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileOperationManifestCommand())->execute([
            'config' => $config,
            'output' => $this->manifestPath(),
            '--application-build-id' => 'build-development-operation-command',
            ...$this->discoveryOptions(),
        ]);
    }

    public function testRejectsInvalidOperationAttributesDuringCompile(): void
    {
        $config = $this->configPath();
        file_put_contents($config, '<?php return [\\' . InvalidCommandOperationProvider::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new CompileOperationManifestCommand())->execute([
            'config' => $config,
            'output' => $this->manifestPath(),
            '--application-build-id' => 'build-invalid-operation-command',
        ]);
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function discoveryOptions(): array
    {
        $directory = sys_get_temp_dir() . '/blackops-operation-command-discovery-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $psr4 = $directory . '/autoload_psr4.php';
        $classmap = $directory . '/autoload_classmap.php';
        file_put_contents($psr4, '<?php return [];');
        file_put_contents($classmap, '<?php return [];');

        return [
            '--discovery-root' => [$this->fixtureRoot()],
            '--composer-base' => dirname(__DIR__, 3),
            '--composer-psr4' => $psr4,
            '--composer-classmap' => $classmap,
        ];
    }

    private function fixtureRoot(): string
    {
        return __DIR__ . '/Fixture';
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

final readonly class DevelopmentCommandOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [CommandOperation::class, DevelopmentInlineOperation::class];
    }
}

final readonly class DuplicateDevelopmentTypeProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [DuplicateDevelopmentTypeOperation::class];
    }
}

final readonly class InvalidCommandOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [InvalidCommandOperation::class];
    }
}

#[OperationType('command.operation')]
#[Accepts(CommandValue::class)]
#[HandledBy(CommandHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class CommandOperation implements Operation {}

#[OperationType('development.inline')]
#[Accepts(CommandValue::class)]
#[HandledBy(CommandHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class DuplicateDevelopmentTypeOperation implements Operation {}

final readonly class InvalidCommandOperation implements Operation {}

final readonly class CommandValue implements OperationValue {}

final readonly class CommandHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
