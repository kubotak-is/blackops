<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop;

use BlackOps\Database\AfterCommitFailure;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Aop\RuntimeAopCompiler;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use BlackOps\Tests\Fixtures\Aop\AfterCommitService;
use BlackOps\Tests\Fixtures\Aop\ClassTransactionalService;
use BlackOps\Tests\Fixtures\Aop\FoundationTransactionalOperation;
use BlackOps\Tests\Fixtures\Aop\PlainService;
use BlackOps\Tests\Fixtures\Aop\ReadonlyTransactionalService;
use BlackOps\Tests\Fixtures\Aop\TransactionalService;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ray\Aop\WeavedInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RuntimeAopCompilerTest extends TestCase
{
    public function testCompilesAttributedDefinitionsAndLeavesPlainServiceUntouched(): void
    {
        $builder = $this->builder();
        $this->register($builder, TransactionalService::class);
        $this->register($builder, ClassTransactionalService::class);
        $this->register($builder, AfterCommitService::class);
        $this->register($builder, ReadonlyTransactionalService::class);
        $this->register($builder, PlainService::class);
        $containerPath = $this->containerPath();

        $compilation = new RuntimeAopCompiler()->compile($builder, $containerPath, 'app', ['app', 'analytics']);
        $builder->compile();
        $this->injectRuntime($builder);

        $transactional = $builder->get(TransactionalService::class);
        $classTransactional = $builder->get(ClassTransactionalService::class);
        $afterCommit = $builder->get(AfterCommitService::class);
        $readonly = $builder->get(ReadonlyTransactionalService::class);
        $plain = $builder->get(PlainService::class);

        self::assertInstanceOf(WeavedInterface::class, $transactional);
        self::assertInstanceOf(WeavedInterface::class, $classTransactional);
        self::assertInstanceOf(WeavedInterface::class, $afterCommit);
        self::assertInstanceOf(WeavedInterface::class, $readonly);
        self::assertNotInstanceOf(WeavedInterface::class, $plain);
        self::assertSame('value', $transactional->execute('value'));
        self::assertSame(1, $transactional->calls);
        self::assertSame('default', $classTransactional->inheritedDefault('default'));
        self::assertSame('named', $classTransactional->namedOverride('named'));
        $afterCommit->record('recorded');
        self::assertSame(['recorded'], $afterCommit->values);
        self::assertSame('readonly', $readonly->execute('readonly'));
        self::assertNotEmpty($compilation->proxyFiles);

        foreach ($compilation->proxyFiles as $file) {
            self::assertFileExists($file);
            self::assertSame(dirname($containerPath) . '/aop', dirname($file));
        }
    }

    public function testDirectInstanceIsNotInterceptedAndContainerInstanceIsProxy(): void
    {
        $builder = $this->builder();
        $this->register($builder, TransactionalService::class);
        new RuntimeAopCompiler()->compile($builder, $this->containerPath(), 'app', ['app']);
        $builder->compile();
        $this->injectRuntime($builder);

        self::assertNotInstanceOf(WeavedInterface::class, new TransactionalService());
        self::assertInstanceOf(WeavedInterface::class, $builder->get(TransactionalService::class));
    }

    public function testOperationTransactionalBindingRemainsPassThrough(): void
    {
        $builder = $this->builder();
        $this->register($builder, FoundationTransactionalOperation::class);
        new RuntimeAopCompiler()->compile($builder, $this->containerPath(), 'app', ['app']);
        $builder->compile();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('beginTransaction');
        $this->injectRuntime($builder, $connection, configureConnection: false);

        $operation = $builder->get(FoundationTransactionalOperation::class);

        self::assertInstanceOf(FoundationTransactionalOperation::class, $operation);
        self::assertSame('operation', $operation->execute());
    }

    public function testAfterCommitProxyQueuesUntilTransactionCommit(): void
    {
        $builder = $this->builder();
        $this->register($builder, AfterCommitService::class);
        new RuntimeAopCompiler()->compile($builder, $this->containerPath(), 'app', ['app']);
        $builder->compile();
        $runtime = $this->injectRuntime($builder);
        $service = $builder->get(AfterCommitService::class);

        $runtime->transactional('app', function () use ($service): void {
            $service->record('queued');
            self::assertSame([], $service->values);
        });

        self::assertSame(['queued'], $service->values);
    }

    public function testProxyNameIsDeterministicAndStaleArtifactsAreRemoved(): void
    {
        $containerPath = $this->containerPath();
        $compiler = new RuntimeAopCompiler();
        $builder = $this->builder();
        $this->register($builder, TransactionalService::class);
        $first = $compiler->compile($builder, $containerPath, 'app', ['app']);
        $stale = dirname($containerPath) . '/aop/stale.php';
        file_put_contents($stale, '<?php');
        $secondBuilder = $this->builder();
        $this->register($secondBuilder, TransactionalService::class);
        $second = $compiler->compile($secondBuilder, $containerPath, 'app', ['app']);

        self::assertSame(array_map('basename', $first->proxyFiles), array_map('basename', $second->proxyFiles));
        self::assertFileDoesNotExist($stale);
    }

    public function testOriginalThrowableIsPropagatedWithoutAdditionalInvocation(): void
    {
        $builder = $this->builder();
        $this->register($builder, TransactionalService::class);
        new RuntimeAopCompiler()->compile($builder, $this->containerPath(), 'app', ['app']);
        $builder->compile();
        $this->injectRuntime($builder);
        $service = $builder->get(TransactionalService::class);

        try {
            $service->execute('throw');
            self::fail('Expected service failure.');
        } catch (\RuntimeException $throwable) {
            self::assertSame('expected failure', $throwable->getMessage());
            self::assertSame(1, $service->calls);
        }
    }

    public function testRejectsFinalClassAndCleansArtifacts(): void
    {
        $containerPath = $this->containerPath();
        $builder = $this->builder();
        $this->register($builder, InvalidFinalTransactionalService::class);

        try {
            new RuntimeAopCompiler()->compile($builder, $containerPath, 'app', ['app']);
            self::fail('Expected final class rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(InvalidFinalTransactionalService::class, $exception->getMessage());
            self::assertStringContainsString('must not be final', $exception->getMessage());
            self::assertSame([], glob(dirname($containerPath) . '/aop/*'));
        }
    }

    public function testRejectsInvalidTransactionalMethodTargets(): void
    {
        $this->assertInvalid(InvalidFinalMethodService::class, 'must not be final');
        $this->assertInvalid(InvalidPrivateMethodService::class, 'requires a public method');
        $this->assertInvalid(InvalidStaticMethodService::class, 'requires an instance method');
    }

    public function testRejectsInvalidAfterCommitSignatures(): void
    {
        $this->assertInvalid(InvalidAfterCommitReturnService::class, 'explicit void return type');
        $this->assertInvalid(InvalidAfterCommitReferenceService::class, 'reference parameters');
        $this->assertInvalid(InvalidAfterCommitGeneratorService::class, 'cannot be a generator');
    }

    public function testRejectsAttributesOnUnsupportedTargets(): void
    {
        $this->assertInvalid(InvalidAfterCommitClassService::class, 'cannot declare AfterCommit');
        $this->assertInvalid(InvalidTransactionalPropertyService::class, 'cannot target property');
        $this->assertInvalid(InvalidAfterCommitParameterService::class, 'cannot target parameter');
    }

    public function testRejectsUnknownConnectionWithoutExposingConfigurationValues(): void
    {
        $builder = $this->builder();
        $this->register($builder, UnknownConnectionService::class);

        try {
            new RuntimeAopCompiler()->compile($builder, $this->containerPath(), 'app', ['app']);
            self::fail('Expected connection rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('missing', $exception->getMessage());
            self::assertStringNotContainsString('database-password', $exception->getMessage());
        }
    }

    public function testRejectsTransactionalServiceWhenDatabaseConfigurationIsMissing(): void
    {
        $builder = $this->builder();
        $this->register($builder, TransactionalService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('require application database configuration');

        new RuntimeAopCompiler()->compile($builder, $this->containerPath(), null, []);
    }

    /** @param class-string $class */
    private function assertInvalid(string $class, string $message): void
    {
        $builder = $this->builder();
        $this->register($builder, $class);

        try {
            new RuntimeAopCompiler()->compile($builder, $this->containerPath(), 'app', ['app']);
            self::fail('Expected invalid AOP target rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString($class, $exception->getMessage());
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    /** @param class-string $class */
    private function register(ContainerBuilder $builder, string $class): void
    {
        $builder->register($class)->setPublic(true);
    }

    private function builder(): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->register(TransactionRuntime::class)->setSynthetic(true)->setPublic(true);

        return $builder;
    }

    private function injectRuntime(
        ContainerBuilder $builder,
        ?Connection $connection = null,
        bool $configureConnection = true,
    ): TransactionRuntime {
        $active = false;
        $level = 0;
        $connection ??= $this->createStub(Connection::class);

        if ($configureConnection && $connection instanceof Stub) {
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
                    $level++;
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
                    $level = max(0, $level - 1);
                    $active = $level > 0;
                });
        }
        $databases = new class($connection) implements DatabaseManager {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function connection(?string $name = null): Connection
            {
                return $this->connection;
            }
        };
        $reporter = new class implements AfterCommitFailureReporter {
            public function report(AfterCommitFailure $failure): void {}
        };

        $runtime = new TransactionRuntime($databases, $reporter, new ExecutionScopeProvider());
        $builder->set(TransactionRuntime::class, $runtime);
        $accessor = $builder->get(TransactionRuntimeAccessor::class);

        if (!$accessor instanceof TransactionRuntimeAccessor) {
            self::fail('Expected transaction runtime accessor.');
        }

        $accessor->set($runtime);

        return $runtime;
    }

    private function containerPath(): string
    {
        return sys_get_temp_dir() . '/blackops-aop-' . bin2hex(random_bytes(8)) . '/container.php';
    }
}

#[Transactional]
final class InvalidFinalTransactionalService
{
    public function execute(): void {}
}

class InvalidFinalMethodService
{
    #[Transactional]
    final public function execute(): void {}
}

class InvalidPrivateMethodService
{
    #[Transactional]
    private function execute(): void {}
}

class InvalidStaticMethodService
{
    #[Transactional]
    public static function execute(): void {}
}

class InvalidAfterCommitReturnService
{
    #[AfterCommit]
    public function execute(): string
    {
        return 'invalid';
    }
}

class InvalidAfterCommitReferenceService
{
    #[AfterCommit]
    public function execute(string &$value): void {}
}

class InvalidAfterCommitGeneratorService
{
    #[AfterCommit]
    public function execute(): \Generator
    {
        yield 'invalid';
    }
}

class UnknownConnectionService
{
    #[Transactional('missing')]
    public function execute(): void {}
}

#[AfterCommit]
class InvalidAfterCommitClassService
{
    public function execute(): void {}
}

class InvalidTransactionalPropertyService
{
    #[Transactional]
    public string $value = '';
}

class InvalidAfterCommitParameterService
{
    public function execute(#[AfterCommit] string $value): void {}
}
