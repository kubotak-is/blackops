<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Registry;

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
use BlackOps\Internal\Registry\OperationProviderCompiler;
use BlackOps\Internal\Registry\OperationProviderConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationProviderConfigLoaderTest extends TestCase
{
    public function testLoadsProviderInstancesFromConfigFile(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [new \\' . InstanceOperationProvider::class . '()];');

        $providers = new OperationProviderConfigLoader()->load($path);

        self::assertCount(1, $providers);
        self::assertInstanceOf(InstanceOperationProvider::class, $providers[0]);
    }

    public function testLoadsProviderClassNamesFromConfigFile(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [\\' . ClassNameOperationProvider::class . '::class];');

        $providers = new OperationProviderConfigLoader()->load($path);

        self::assertCount(1, $providers);
        self::assertInstanceOf(ClassNameOperationProvider::class, $providers[0]);
    }

    public function testLoadsSingleProviderFromConfigFile(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return new \\' . InstanceOperationProvider::class . '();');

        $providers = new OperationProviderConfigLoader()->load($path);

        self::assertCount(1, $providers);
        self::assertInstanceOf(InstanceOperationProvider::class, $providers[0]);
    }

    public function testLoadedProvidersCanBuildOperationRegistry(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [\\' . ClassNameOperationProvider::class . '::class];');

        $registry = new OperationProviderCompiler()->compile(new OperationProviderConfigLoader()->load($path));

        self::assertSame('config.operation', $registry->findByDefinition(ConfigOperation::class)?->typeId);
    }

    public function testRejectsMissingConfigFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationProviderConfigLoader()->load($this->configPath());
    }

    public function testRejectsInvalidReturnValue(): void
    {
        $path = $this->configPath();
        file_put_contents($path, "<?php return 'invalid';");

        $this->expectException(InvalidArgumentException::class);

        new OperationProviderConfigLoader()->load($path);
    }

    public function testRejectsInvalidProviderEntry(): void
    {
        $path = $this->configPath();
        file_put_contents($path, "<?php return [new \\stdClass()];");

        $this->expectException(InvalidArgumentException::class);

        new OperationProviderConfigLoader()->load($path);
    }

    public function testRejectsProviderClassWithRequiredConstructorArguments(): void
    {
        $path = $this->configPath();
        file_put_contents($path, '<?php return [\\' . OperationProviderWithRequiredArgument::class . '::class];');

        $this->expectException(InvalidArgumentException::class);

        new OperationProviderConfigLoader()->load($path);
    }

    private function configPath(): string
    {
        return sys_get_temp_dir() . '/blackops-operation-provider-config-' . bin2hex(random_bytes(8)) . '.php';
    }
}

final readonly class InstanceOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ConfigOperation::class];
    }
}

final readonly class ClassNameOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        yield ConfigOperation::class;
    }
}

final readonly class OperationProviderWithRequiredArgument implements OperationProvider
{
    public function __construct(
        private string $value,
    ) {}

    public function definitions(): iterable
    {
        return [ConfigOperation::class];
    }
}

#[OperationType('config.operation')]
#[Accepts(ConfigValue::class)]
#[HandledBy(ConfigHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class ConfigOperation implements Operation {}

final readonly class ConfigValue implements OperationValue {}

final readonly class ConfigHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
