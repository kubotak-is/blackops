<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Logging;

use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\RuntimeLoggingServiceInjector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

final class RuntimeLoggingServiceInjectorTest extends TestCase
{
    public function testInjectsSameExecutionScopedLoggerIntoAutowiredApplicationService(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerHandlers($builder, new OperationRegistry([]));
        $builder->register(RuntimeLoggerConsumer::class)->setAutowired(true)->setPublic(true);
        $container = $compiler->compile($builder);
        $scope = new ExecutionScopeProvider();
        $logger = new RuntimeLoggingServiceInjector()->inject($container, $scope, new RuntimeLoggerBackend());
        $consumer = $container->get(RuntimeLoggerConsumer::class);

        self::assertInstanceOf(ExecutionScopedLogger::class, $logger);
        self::assertInstanceOf(RuntimeLoggerConsumer::class, $consumer);
        self::assertSame($logger, $consumer->logger);
        self::assertSame($logger, $container->get(LoggerInterface::class));
    }

    public function testRejectsContainerWithoutRuntimeLoggerDefinition(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \LogicException('Service unavailable.');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $this->expectException(InvalidArgumentException::class);

        new RuntimeLoggingServiceInjector()->inject($container, new ExecutionScopeProvider());
    }
}

final readonly class RuntimeLoggerConsumer
{
    public function __construct(
        public LoggerInterface $logger,
    ) {}
}

final class RuntimeLoggerBackend extends AbstractLogger
{
    public function log(mixed $level, string|Stringable $message, array $context = []): void {}
}
