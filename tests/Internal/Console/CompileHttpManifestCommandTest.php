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
use BlackOps\Tests\Internal\Console\Fixture\DevelopmentDeferredOperation;
use BlackOps\Tests\Internal\Console\Fixture\DevelopmentInlineOperation;
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
        self::assertSame(3, $artifact->schemaVersion);
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

    public function testCompilesProviderAndDiscoveryDefinitionsIntoFastRouteData(): void
    {
        $config = $this->configPath();
        $output = $this->manifestPath();
        file_put_contents($config, '<?php return [\\' . DevelopmentHttpOperationProvider::class . '::class];');

        $status = new CommandTester(new CompileHttpManifestCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--application-build-id' => 'build-development-http-command',
            ...$this->discoveryOptions(),
        ]);

        $artifact = new HttpOperationManifestFile()->loadArtifact($output);
        $registry = $artifact->manifest->toRegistry([
            new HttpCommandOperation(),
            new DevelopmentInlineOperation(),
            new DevelopmentDeferredOperation(),
        ]);

        self::assertSame(0, $status);
        self::assertCount(3, $artifact->manifest->operations);
        self::assertSame('command.http', $artifact->manifest->dispatcherData[0]['GET']['/command-http']);
        self::assertSame('development.inline', $artifact->manifest->dispatcherData[0]['GET']['/discovered-inline']);
        self::assertSame(
            'development.deferred',
            $artifact->manifest->dispatcherData[0]['POST']['/discovered-deferred'],
        );
        self::assertNotNull($registry->match('GET', '/discovered-inline'));
        self::assertNotNull($registry->match('POST', '/discovered-deferred'));
    }

    public function testCompilesRequiredConstructorSelfHandledProviderWithoutInstantiatingDefinition(): void
    {
        $config = $this->configPath();
        $output = $this->manifestPath();
        file_put_contents($config, '<?php return [\\' . RequiredHttpOperationProvider::class . '::class];');

        $status = new CommandTester(new CompileHttpManifestCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--application-build-id' => 'build-required-http-provider',
        ]);

        $operation = new HttpOperationManifestFile()
            ->loadArtifact($output)
            ->manifest
            ->operations['command.required.provider'];

        self::assertSame(0, $status);
        self::assertSame(RequiredHttpCommandOperation::class, $operation['definition']);
        self::assertSame(RequiredHttpCommandOperation::class, $operation['handler']);
    }

    public function testCompilesRequiredConstructorSelfHandledDiscoveryWithoutInstantiatingDefinition(): void
    {
        $config = $this->configPath();
        $output = $this->manifestPath();
        $root = sys_get_temp_dir() . '/blackops-required-http-discovery-' . bin2hex(random_bytes(8));
        mkdir($root);
        file_put_contents($config, '<?php return [];');
        file_put_contents($root . '/RequiredDiscoveredOperation.php', implode("\n", [
            '<?php',
            'declare(strict_types=1);',
            'namespace BlackOps\\Tests\\Internal\\Console\\DynamicStandalone;',
            '#[\\BlackOps\\Http\\Attribute\\Route(\'GET\', \'/required-discovered\')]',
            '#[\\BlackOps\\Core\\Attribute\\OperationType(\'standalone.discovery.required\')]',
            '#[\\BlackOps\\Core\\Attribute\\Accepts(RequiredDiscoveredValue::class)]',
            '#[\\BlackOps\\Core\\Attribute\\Returns(\\BlackOps\\Core\\EmptyOutcome::class)]',
            'final readonly class RequiredDiscoveredOperation implements \\BlackOps\\Core\\Operation, \\BlackOps\\Core\\OperationHandler {',
            'public function __construct(private \\stdClass $dependency) {}',
            'public function handle(\\BlackOps\\Core\\OperationEnvelope $operation): \\BlackOps\\Core\\OperationResult {',
            'return \\BlackOps\\Core\\OperationResult::completed();',
            '}',
            '}',
            'final readonly class RequiredDiscoveredValue implements \\BlackOps\\Core\\OperationValue {}',
        ]));

        $status = new CommandTester(new CompileHttpManifestCommand())->execute([
            'config' => $config,
            'output' => $output,
            '--application-build-id' => 'build-required-http-discovery',
            ...$this->discoveryOptions($root),
        ]);

        $operation = new HttpOperationManifestFile()
            ->loadArtifact($output)
            ->manifest
            ->operations['standalone.discovery.required'];

        self::assertSame(0, $status);
        self::assertSame($operation['definition'], $operation['handler']);
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function discoveryOptions(?string $root = null): array
    {
        $directory = sys_get_temp_dir() . '/blackops-http-command-discovery-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $psr4 = $directory . '/autoload_psr4.php';
        $classmap = $directory . '/autoload_classmap.php';
        file_put_contents($psr4, '<?php return [];');
        file_put_contents($classmap, '<?php return [];');

        return [
            '--discovery-root' => [$root ?? $this->fixtureRoot()],
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

final readonly class DevelopmentHttpOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [HttpCommandOperation::class, DevelopmentInlineOperation::class];
    }
}

final readonly class RequiredHttpOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [RequiredHttpCommandOperation::class];
    }
}

#[Route('GET', '/command-http')]
#[OperationType('command.http')]
#[Accepts(HttpCommandValue::class)]
#[HandledBy(HttpCommandHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class HttpCommandOperation implements Operation {}

#[Route('GET', '/required-provider')]
#[OperationType('command.required.provider')]
#[Accepts(HttpCommandValue::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RequiredHttpCommandOperation implements Operation, OperationHandler
{
    public function __construct(
        private \stdClass $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class HttpCommandValue implements OperationValue {}

final readonly class HttpCommandHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
