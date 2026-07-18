<?php

declare(strict_types=1);

namespace BlackOps\Tests\Integration;

use BlackOps\Application\Application;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Database\DatabaseManager;
use BlackOps\Http\Attribute\Route;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Internal\Application\ApplicationWorkerComposer;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FilesystemIterator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationConsoleKernelTest extends TestCase
{
    private const SCHEMA = 'blackops_application_console';

    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        $this->connection()->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');

        foreach ($this->directories as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
                ),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }

    public function testApplicationConsoleBuildsMigratesProcessesAndMaintainsExplicitly(): void
    {
        $directory = $this->directory();
        $application = $this->application($directory);
        $kernel = $application->console();
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');

        $viewerDisabled = new BufferedOutput();
        self::assertSame(1, $kernel->run(new ArrayInput(['command' => 'operation:viewer']), $viewerDisabled));
        self::assertSame("viewer.disabled\n", $viewerDisabled->fetch());

        $operations = $this->runCommand($kernel, 'operation:list');
        self::assertStringContainsString('welcome.show', $operations);
        self::assertStringContainsString('report.generate', $operations);
        self::assertStringContainsString('order.create', $operations);
        $this->runCommand($kernel, 'build:compile');
        self::assertFileExists($directory . '/var/build/operations.php');
        self::assertFileExists($directory . '/var/build/http.php');
        self::assertFileExists($directory . '/var/build/container.php');
        self::assertFalse($this->schemaExists($connection));

        $status = $this->runCommand($kernel, 'database:status');
        self::assertStringContainsString('pending: 2', $status);
        self::assertFalse($this->schemaExists($connection));
        $this->runCommand($kernel, 'database:migrate');
        self::assertStringContainsString('No pending migrations.', $this->runCommand($kernel, 'database:migrate'));
        self::assertTrue($this->schemaExists($connection));

        $psr17 = new Psr17Factory();
        $actor = new ActorRef('console-report-user', 'user');
        $response = $application
            ->http()
            ->handle(
                $psr17
                    ->createServerRequest('POST', '/reports')
                    ->withBody($psr17->createStream(json_encode([
                        'reportName' => 'weekly',
                        'recipientEmail' => 'console-reports@example.com',
                    ], JSON_THROW_ON_ERROR)))
                    ->withAttribute(ActorRef::class, $actor),
            );
        self::assertSame(202, $response->getStatusCode());
        /** @var array{operationId: string} $acknowledgement */
        $acknowledgement = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        $operationId = OperationId::fromString($acknowledgement['operationId']);

        $inspect = $this->runCommand($kernel, 'operation:inspect', [
            'operation-id' => $operationId->toString(),
            '--json' => true,
        ]);
        /** @var array<string, mixed> $diagnostics */
        $diagnostics = json_decode($inspect, associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('found', $diagnostics['status']);
        self::assertSame($operationId->toString(), $diagnostics['operation']['operationId']);
        self::assertSame('accepted', $diagnostics['state']['current']);
        self::assertSame('[masked]', $diagnostics['operation']['actors']['origin']['id']);

        $this->assertWorkerComposition($application);
        $this->runCommand($kernel, 'worker:run', ['--iterations' => '1', '--idle-sleep-milliseconds' => '1']);
        self::assertStringContainsString('Worker stopped.', $this->runCommand($kernel, 'worker:run', [
            '--iterations' => '1',
            '--idle-sleep-milliseconds' => '1',
        ]));
        self::assertSame('retry_scheduled', $connection->fetchOne('SELECT state FROM '
        . self::SCHEMA
        . '.operations WHERE operation_id = :operation_id', ['operation_id' => $operationId->toString()]));

        self::assertStringContainsString('Total: 0', $this->runCommand($kernel, 'retention:plan'));
        self::assertStringContainsString('Retention purge dry run', $this->runCommand($kernel, 'retention:purge', [
            '--dry-run' => true,
        ]));
        self::assertStringContainsString('Scheduler run completed', $this->runCommand($kernel, 'scheduler:run'));
        self::assertStringContainsString('Scheduler daemon iteration 1 completed', $this->runCommand(
            $kernel,
            'scheduler:daemon',
            ['--iterations' => '1'],
        ));
    }

    public function testApplicationWorkerUsesConfiguredSystemActorAndCompiledPolicy(): void
    {
        $directory = $this->directory();
        $application = $this->application($directory);
        $kernel = $application->console();
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->runCommand($kernel, 'build:compile');
        $this->runCommand($kernel, 'database:migrate');
        ConsoleWorkerAuthorizationPolicy::$requests = [];
        ConsoleWorkerAuthorizationPolicy::$dependencies = [];
        ConsoleWorkerAuthorizedOperation::$context = null;
        $actor = new ActorRef('console-user-123', 'user');
        $response = $application
            ->http()
            ->handle(
                new Psr17Factory()
                    ->createServerRequest('GET', '/authorized-worker')
                    ->withAttribute(ActorRef::class, $actor),
            );

        self::assertSame(202, $response->getStatusCode());
        /** @var array{operationId: string} $acknowledgement */
        $acknowledgement = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $operationId = OperationId::fromString($acknowledgement['operationId']);
        $this->runCommand($kernel, 'worker:run', ['--iterations' => '1', '--idle-sleep-milliseconds' => '1']);

        self::assertSame('completed', $connection->fetchOne('SELECT state FROM '
        . self::SCHEMA
        . '.operations WHERE operation_id = :operation_id', ['operation_id' => $operationId->toString()]));
        self::assertCount(2, ConsoleWorkerAuthorizationPolicy::$requests);
        $workerRequest = ConsoleWorkerAuthorizationPolicy::$requests[1];
        self::assertEquals($actor, $workerRequest->actor());
        self::assertEquals($actor, $workerRequest->context()->actorContext()?->origin());
        self::assertEquals($actor, $workerRequest->context()->actorContext()?->authorization());
        self::assertSame('console-worker', $workerRequest->context()->actorContext()?->execution()->id());
        self::assertSame('system', $workerRequest->context()->actorContext()?->execution()->type());
        self::assertSame(['compiled', 'compiled'], ConsoleWorkerAuthorizationPolicy::$dependencies);
        self::assertNotNull(ConsoleWorkerAuthorizedOperation::$context);
        self::assertSame(
            'console-worker',
            ConsoleWorkerAuthorizedOperation::$context->actorContext()?->execution()->id(),
        );
    }

    private function application(string $directory): Application
    {
        $root = dirname(__DIR__, levels: 2);
        $containerClass = 'ConsoleIntegrationContainer' . substr(hash('sha256', $directory), 0, 12);
        require_once $root . '/examples/quickstart/app/Feature/Order/OrderRepository.php';
        $source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root . '/examples/quickstart/app',
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        ));
        foreach ($source as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
        $config = $directory . '/config';
        mkdir($config);
        mkdir($directory . '/var');
        mkdir($directory . '/var/build');
        $this->writeConfig($config, 'app', [
            'build' => [
                'application_build_id' => 'console-integration',
                'operation_manifest' => $directory . '/var/build/operations.php',
                'http_manifest' => $directory . '/var/build/http.php',
                'container' => $directory . '/var/build/container.php',
                'container_class' => $containerClass,
                'container_namespace' => __NAMESPACE__ . '\\Generated',
            ],
        ]);
        $applicationConnection = $this->connectionParameters();
        $this->writeConfig($config, 'database', [
            'default' => 'app',
            'connections' => [
                'app' => $applicationConnection,
                'framework' => $this->connectionParameters(),
            ],
            'framework' => [
                'connection' => 'framework',
                'schema' => self::SCHEMA,
            ],
        ]);
        $this->writeConfig($config, 'execution', ['worker' => ['id' => 'console-worker']]);
        $this->writeConfig($config, 'operations', [
            'discovery' => [$root . '/examples/quickstart/app/Feature'],
            'providers' => [],
        ]);
        $this->writeConfig($config, 'retention', [
            'transport_payload_days' => 30,
            'journal_days' => 90,
            'outcome_days' => 30,
            'dead_letter_days' => 90,
            'policy_ref' => 'console-policy-v1',
            'actor' => 'console-maintenance',
        ]);

        return Application::configure($directory)
            ->withConfiguration()
            ->withOperations([ConsoleWorkerOperationProvider::class])
            ->withServices([
                \App\ApplicationServiceProvider::class,
                ConsoleWorkerServiceProvider::class,
            ])
            ->create();
    }

    /** @param array<string, mixed> $options */
    private function runCommand(
        \BlackOps\Application\ConsoleKernel $kernel,
        string $command,
        array $options = [],
    ): string {
        $output = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput(['command' => $command, ...$options]), $output));

        return $output->fetch();
    }

    private function assertWorkerComposition(Application $application): void
    {
        /** @var ApplicationConfigurationSnapshot $snapshot */
        $snapshot = new ReflectionClass($application)
            ->getProperty('_configuration')
            ->getValue($application);
        $composition = new ApplicationWorkerComposer()->compose($snapshot);
        self::assertNotSame($composition->mainConnection, $composition->heartbeatConnection);
        $loop = new ReflectionClass($composition->loop);
        self::assertSame($composition->signals, $loop->getProperty('signals')->getValue($composition->loop));
        $runtime = $loop->getProperty('runtime')->getValue($composition->loop);
        self::assertInstanceOf(DeferredWorkerRuntime::class, $runtime);
        self::assertSame(
            $composition->signals,
            new ReflectionClass($runtime)
                ->getProperty('guard')
                ->getValue($runtime),
        );
        $connections = new ReflectionClass($runtime)
            ->getProperty('connections')
            ->getValue($runtime);
        self::assertInstanceOf(ApplicationDatabaseConnectionLifecycle::class, $connections);
        $databases = new ReflectionClass($connections)
            ->getProperty('databases')
            ->getValue($connections);
        self::assertTrue(in_array($composition->mainConnection, $databases->generatedConnections(), strict: true));
        self::assertFalse(in_array(
            $composition->heartbeatConnection,
            $databases->generatedConnections(),
            strict: true,
        ));
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-application-console-' . bin2hex(random_bytes(8));
        if (!mkdir($directory) && !is_dir($directory)) {
            throw new RuntimeException('Could not create application console test directory.');
        }
        $this->directories[] = $directory;

        return $directory;
    }

    /** @param array<array-key, mixed> $configuration */
    private function writeConfig(string $directory, string $name, array $configuration): void
    {
        file_put_contents(
            $directory . '/' . $name . '.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($configuration, return: true) . ";\n",
        );
    }

    private function schemaExists(Connection $connection): bool
    {
        return (int) $connection->fetchOne('SELECT count(*) FROM information_schema.schemata WHERE schema_name = :schema', [
            'schema' => self::SCHEMA,
        ]) === 1;
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection($this->connectionParameters());
    }

    /** @return array<string, mixed> */
    private function connectionParameters(): array
    {
        return [
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ];
    }
}

final readonly class ConsoleWorkerOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ConsoleWorkerAuthorizedOperation::class];
    }
}

final readonly class ConsoleWorkerServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ConsoleWorkerPolicyDependency::class);
    }
}

final readonly class ConsoleWorkerPolicyDependency
{
    public function __construct(DatabaseManager $databases, Connection $connection)
    {
        $this->value = $databases->connection() === $connection ? 'compiled' : 'mismatch';
    }

    public string $value;
}

final readonly class ConsoleWorkerAuthorizedValue implements OperationValue {}

final readonly class ConsoleWorkerAuthorizedOutcome implements Outcome
{
    public function __construct(
        public string $status,
    ) {}
}

#[Route(method: 'GET', path: '/authorized-worker')]
#[OperationType('console.worker.authorized')]
#[ExecuteWith(Deferred::class)]
#[Authorize(ConsoleWorkerAuthorizationPolicy::class)]
final class ConsoleWorkerAuthorizedOperation implements Operation
{
    public static ?ExecutionContext $context = null;

    public function handle(
        ConsoleWorkerAuthorizedValue $value,
        ExecutionContext $context,
    ): ConsoleWorkerAuthorizedOutcome {
        self::$context = $context;

        return new ConsoleWorkerAuthorizedOutcome('completed');
    }
}

final class ConsoleWorkerAuthorizationPolicy implements AuthorizationPolicy
{
    /** @var list<AuthorizationRequest> */
    public static array $requests = [];

    /** @var list<string> */
    public static array $dependencies = [];

    public function __construct(ConsoleWorkerPolicyDependency $dependency)
    {
        self::$dependencies[] = $dependency->value;
    }

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        self::$requests[] = $request;

        return AuthorizationDecision::allow();
    }
}
