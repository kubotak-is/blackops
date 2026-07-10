<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

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
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Routing\FastRouteDispatcherDataCompiler;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ProductionRuntimeArtifactLoaderTest extends TestCase
{
    public function testLoadsProductionRuntimeArtifacts(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $container = $this->path('container');
        $class = $this->className('LoadedContainer');
        $namespace = __NAMESPACE__ . '\\Generated';
        $this->writeOperationManifest($operationManifest);
        $this->writeHttpManifest($httpManifest);
        $this->writeContainer($container, $class, $namespace);

        $artifacts = new ProductionRuntimeArtifactLoader()->load(
            $operationManifest,
            $httpManifest,
            $container,
            $class,
            $namespace,
        );

        self::assertSame(
            RuntimeArtifactOperation::class,
            $artifacts->operations->findByTypeId('runtime.artifact')?->definition,
        );
        self::assertSame(
            RuntimeArtifactOperation::class,
            $artifacts->http->operations['runtime.artifact']['definition'] ?? null,
        );
        self::assertInstanceOf(ContainerInterface::class, $artifacts->container);
        self::assertInstanceOf(
            RuntimeArtifactHandler::class,
            $artifacts->container->get(RuntimeArtifactHandler::class),
        );
    }

    public function testRejectsMissingContainerArtifact(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductionRuntimeArtifactLoader()->load(
            $this->path('missing-operation-manifest'),
            $this->path('missing-http-manifest'),
            $this->path('missing-container'),
            'MissingContainer',
        );
    }

    public function testRejectsInvalidContainerClassName(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $container = $this->path('container');
        $this->writeOperationManifest($operationManifest);
        $this->writeHttpManifest($httpManifest);
        file_put_contents($container, "<?php\n");

        $this->expectException(InvalidArgumentException::class);

        new ProductionRuntimeArtifactLoader()->load($operationManifest, $httpManifest, $container, 'Invalid-Container');
    }

    public function testRejectsContainerArtifactWithoutExpectedClass(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $container = $this->path('container');
        $this->writeOperationManifest($operationManifest);
        $this->writeHttpManifest($httpManifest);
        file_put_contents($container, "<?php\n");

        $this->expectException(InvalidArgumentException::class);

        new ProductionRuntimeArtifactLoader()->load($operationManifest, $httpManifest, $container, 'MissingContainer');
    }

    public function testRejectsContainerArtifactThatDoesNotImplementPsrContainer(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $container = $this->path('container');
        $class = $this->className('NotContainer');
        $this->writeOperationManifest($operationManifest);
        $this->writeHttpManifest($httpManifest);
        file_put_contents($container, "<?php\n\nfinal class {$class} {}\n");

        $this->expectException(InvalidArgumentException::class);

        new ProductionRuntimeArtifactLoader()->load($operationManifest, $httpManifest, $container, $class);
    }

    public function testRejectsMismatchedManifestBuildIdsBeforeLoadingContainer(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $this->writeOperationManifest($operationManifest, 'build-operation');
        $this->writeHttpManifest($httpManifest, 'build-http');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('application build IDs do not match');

        new ProductionRuntimeArtifactLoader()->load(
            $operationManifest,
            $httpManifest,
            $this->path('missing-container'),
            'MissingContainer',
        );
    }

    public function testRejectsInvalidHttpDispatcherDataBeforeLoadingContainer(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $this->writeOperationManifest($operationManifest);
        file_put_contents(
            $httpManifest,
            "<?php return ['schemaVersion' => 2, 'applicationBuildId' => 'build-runtime-artifact', 'payload' => ['routes' => [], 'operations' => [], 'dispatcherData' => ['invalid']]];",
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dispatcher data is missing or invalid');

        new ProductionRuntimeArtifactLoader()->load(
            $operationManifest,
            $httpManifest,
            $this->path('missing-container'),
            'MissingContainer',
        );
    }

    private function writeOperationManifest(string $path, string $applicationBuildId = 'build-runtime-artifact'): void
    {
        $metadata = new OperationMetadataCompiler()->compile(RuntimeArtifactOperation::class);
        new OperationManifestFile()->write(new OperationRegistry([$metadata]), $path, $applicationBuildId);
    }

    private function writeHttpManifest(string $path, string $applicationBuildId = 'build-runtime-artifact'): void
    {
        $routes = [
            'GET' => [
                '/runtime-artifact' => 'runtime.artifact',
            ],
        ];

        new HttpOperationManifestFile()->write(
            new HttpOperationManifest(
                $routes,
                [
                    'runtime.artifact' => [
                        'definition' => RuntimeArtifactOperation::class,
                        'value' => RuntimeArtifactValue::class,
                        'handler' => RuntimeArtifactHandler::class,
                        'outcome' => EmptyOutcome::class,
                        'strategy' => Inline::class,
                    ],
                ],
                new FastRouteDispatcherDataCompiler()->compile($routes),
            ),
            $path,
            $applicationBuildId,
        );
    }

    private function writeContainer(string $path, string $class, string $namespace): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(RuntimeArtifactHandler::class)->setPublic(true);
        $compiler->compile($builder);

        new RuntimeContainerDumper()->dump($builder, $path, $class, $namespace);
    }

    private function path(string $name): string
    {
        return sys_get_temp_dir() . '/blackops-production-runtime-' . $name . '-' . bin2hex(random_bytes(8)) . '.php';
    }

    private function className(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(8));
    }
}

#[OperationType('runtime.artifact')]
#[Accepts(RuntimeArtifactValue::class)]
#[HandledBy(RuntimeArtifactHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RuntimeArtifactOperation implements Operation {}

final readonly class RuntimeArtifactValue implements OperationValue {}

final readonly class RuntimeArtifactHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
