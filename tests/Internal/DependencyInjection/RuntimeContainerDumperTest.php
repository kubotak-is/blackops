<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Aop\RuntimeAopCompiler;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
use BlackOps\Tests\Fixtures\Aop\TransactionalService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Ray\Aop\WeavedInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RuntimeContainerDumperTest extends TestCase
{
    public function testDumpsCompiledContainerToPhpFile(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $this->compiledBuilder($compiler);
        $path = $this->dumpPath();
        $class = $this->className();

        new RuntimeContainerDumper()->dump($builder, $path, $class, __NAMESPACE__ . '\\Generated');

        self::assertFileExists($path);
        self::assertStringContainsString('class ' . $class, (string) file_get_contents($path));
    }

    public function testDumpedContainerResolvesHandlerThroughPsrContainer(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $this->compiledBuilder($compiler);
        $path = $this->dumpPath();
        $class = $this->className();
        $namespace = __NAMESPACE__ . '\\Generated';

        new RuntimeContainerDumper()->dump($builder, $path, $class, $namespace);
        require_once $path;

        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();

        self::assertInstanceOf(ContainerInterface::class, $container);

        $handler = new HandlerResolver($container)->resolve(DumpedContainerHandler::class);

        self::assertInstanceOf(DumpedContainerHandler::class, $handler);
        self::assertSame('dumped-ready', $handler->dependency->value);
    }

    public function testDumpedContainerRequiresAndInitializesGeneratedAopProxy(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerDatabaseServices($builder);
        $builder->register(TransactionalService::class)->setPublic(true);
        $directory = sys_get_temp_dir() . '/blackops-runtime-aop-' . bin2hex(random_bytes(8));
        $path = $directory . '/container.php';
        $class = $this->className();
        $namespace = __NAMESPACE__ . '\\Generated';
        $aop = new RuntimeAopCompiler()->compile($builder, $path, 'app', ['app']);
        $compiler->compile($builder);

        new RuntimeContainerDumper()->dump($builder, $path, $class, $namespace, $aop->proxyFiles);

        $source = (string) file_get_contents($path);
        self::assertStringContainsString("require_once __DIR__ . '/aop/", $source);
        require_once $path;
        $containerClass = $namespace . '\\' . $class;
        $container = new $containerClass();
        $connection = $this->transactionConnection();
        $databases = $this->createStub(DatabaseManager::class);
        $databases->method('connection')->willReturn($connection);
        new RuntimeDatabaseServiceInjector()->inject($container, $databases);
        new RuntimeTransactionServiceInjector()->inject($container, $databases, new ExecutionScopeProvider());
        $service = $container->get(TransactionalService::class);

        self::assertInstanceOf(WeavedInterface::class, $service);
        self::assertSame('dumped-aop', $service->execute('dumped-aop'));
        self::assertSame(1, $service->calls);
    }

    private function compiledBuilder(RuntimeContainerCompiler $compiler): ContainerBuilder
    {
        $builder = $compiler->builder();
        $builder->register(DumpedContainerDependency::class)->setAutowired(true)->setPublic(true);
        $builder->register(DumpedContainerHandler::class)->setAutowired(true)->setPublic(true);
        $compiler->compile($builder);

        return $builder;
    }

    private function dumpPath(): string
    {
        return sys_get_temp_dir() . '/blackops-runtime-container-' . bin2hex(random_bytes(8)) . '.php';
    }

    private function className(): string
    {
        return 'DumpedContainer' . bin2hex(random_bytes(8));
    }

    private function transactionConnection(): Connection
    {
        $active = false;
        $level = 0;
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(static function () use (&$active): bool {
                return $active;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(static function () use (&$level): int {
                return $level;
            });
        $connection
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = true;
                $level = 1;
            });
        $connection
            ->method('commit')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });
        $connection
            ->method('rollBack')
            ->willReturnCallback(static function () use (&$active, &$level): void {
                $active = false;
                $level = 0;
            });

        return $connection;
    }
}

final readonly class DumpedContainerDependency
{
    public function __construct(
        public string $value = 'dumped-ready',
    ) {}
}

final readonly class DumpedContainerHandler implements OperationHandler
{
    public function __construct(
        public DumpedContainerDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new EmptyOutcome());
    }
}
