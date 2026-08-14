<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Aop\FrameworkProxyRuntime;

require_once __DIR__ . '/../../../Fixtures/Aop/FrameworkProxyRuntime/FrameworkProxyRuntimeFixtures.php';

use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInitializer;
use BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInvocation;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use BlackOps\Tests\Fixtures\Aop\FrameworkProxyRuntime\FrameworkRuntimeService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class FrameworkProxyRuntimeInvocationTest extends TestCase
{
    private string $root;
    private TransactionRuntimeAccessor $accessor;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-framework-runtime-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0o755, true);
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $manager = new class($this->connection) implements DatabaseManager {
            public function __construct(
                private readonly Connection $connection,
            ) {}

            public function connection(?string $name = null): Connection
            {
                return $this->connection;
            }
        };
        $runtime = new TransactionRuntime($manager, new FrameworkRuntimeNoopReporter(), new ExecutionScopeProvider());
        $this->accessor = new TransactionRuntimeAccessor();
        $this->accessor->set($runtime);
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        $this->connection->close();
        $this->remove($this->root);
    }

    public function testRuntimeInvocationSurfaceIsInstantiable(): void
    {
        self::assertTrue(is_a(
            FrameworkProxyRuntimeInvocation::class,
            'BlackOps\\Internal\\Aop\\FrameworkProxyGenerator\\FrameworkProxyInvocation',
            true,
        ));
    }

    public function testInitializerBindsGeneratedProxyToTransactionAndAfterCommitAbi(): void
    {
        $result = new FrameworkProxyGenerator()->generate(
            FrameworkRuntimeService::class,
            'runtime-build-' . bin2hex(random_bytes(3)),
            $this->root,
            serviceId: 'runtime.service',
            defaultConnection: 'app',
            connectionNames: ['app'],
        );
        new \BlackOps\Internal\Runtime\FrameworkProxyArtifactLoader()->load(
            $result->directory,
            $result->manifest->applicationBuildId,
            $result->manifest->manifestHash,
            'framework',
        );
        $metadata = new FrameworkProxyContract()->inspect(
            FrameworkRuntimeService::class,
            'framework',
            'runtime.service',
            $result->manifest->applicationBuildId,
            'app',
            ['app'],
        );
        $binding = new \BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding(
            'runtime.service',
            FrameworkRuntimeService::class,
            $result->classMap[FrameworkRuntimeService::class],
            $metadata,
            $metadata->marker(),
        );
        try {
            new FrameworkProxyRuntimeInitializer(new \BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInvocationFactory($this->accessor))->initialize(
                new FrameworkRuntimeService(),
                $binding,
            );
            self::fail('Expected proxy identity conflict.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('BO_PROXY_OWNERSHIP_CONFLICT', $exception->getMessage());
        }
        $proxy = new $result->classMap[FrameworkRuntimeService::class]();
        new FrameworkProxyRuntimeInitializer(new \BlackOps\Internal\Aop\FrameworkProxyRuntime\FrameworkProxyRuntimeInvocationFactory($this->accessor))->initialize(
            $proxy,
            $binding,
        );

        self::assertSame('value', $proxy->run('value'));
        self::assertSame(['run:value'], $proxy->events);
        $this->accessor->transactional('app', function () use ($proxy): void {
            $proxy->notify('queued');
            self::assertSame(['run:value'], $proxy->events);
        });
        self::assertSame(['run:value', 'notify:queued'], $proxy->events);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->remove($child) : unlink($child);
        }
        rmdir($path);
    }
}

final class FrameworkRuntimeNoopReporter implements AfterCommitFailureReporter
{
    public function report(\BlackOps\Database\AfterCommitFailure $failure): void {}
}
