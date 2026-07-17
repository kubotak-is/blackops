<?php

declare(strict_types=1);

namespace BlackOps\Tests\Integration;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Database\DatabaseManager;
use BlackOps\Http\Attribute\Route;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FilesystemIterator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationHttpRuntimeTest extends TestCase
{
    private const SCHEMA = 'blackops_application_http';
    private const BUILD_ID = 'application-http-runtime';

    /** @var list<string> */
    private array $directories = [];

    private ?string $journalPath = null;

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

    public function testComposesAndReusesInlineAndDeferredHttpRuntimeWithoutImplicitMigration(): void
    {
        $paths = $this->compileArtifacts();
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $application = $this->application($paths);

        $http = $application->http();

        self::assertSame($http, $application->http());
        self::assertFalse($this->schemaExists($connection));

        new DatabaseMigrationRunner($connection, self::SCHEMA)->migrate();
        $psr17 = new Psr17Factory();
        $actor = new ActorRef('application-http-user', 'user');
        $welcome = $http->handle($psr17->createServerRequest('GET', '/welcome')->withAttribute(
            ActorRef::class,
            $actor,
        ));
        self::assertSame(200, $welcome->getStatusCode());
        self::assertSame('{"message":"Welcome to BlackOps"}', (string) $welcome->getBody());
        self::assertNotNull($this->journalPath);
        $observed = (string) file_get_contents($this->journalPath);
        self::assertStringContainsString('[masked]', $observed);
        self::assertStringNotContainsString($actor->id(), $observed);

        $report = $http->handle(
            $psr17
                ->createServerRequest('POST', '/reports')
                ->withBody($psr17->createStream(json_encode([
                    'reportName' => 'weekly',
                    'recipientEmail' => 'runtime-reports@example.com',
                ], JSON_THROW_ON_ERROR)))
                ->withAttribute(ActorRef::class, $actor),
        );
        self::assertSame(202, $report->getStatusCode());
        /** @var array{status: string, operationId: string, acceptedAt: string} $acknowledgement */
        $acknowledgement = json_decode((string) $report->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('accepted', $acknowledgement['status']);
        $operationId = OperationId::fromString($acknowledgement['operationId']);
        $operation = $connection->fetchAssociative('SELECT state, next_sequence FROM '
        . self::SCHEMA
        . '.operations WHERE operation_id = :operation_id', ['operation_id' => $operationId->toString()]);
        self::assertIsArray($operation);
        self::assertSame('accepted', $operation['state']);
        self::assertSame(3, (int) $operation['next_sequence']);
        self::assertSame(
            2,
            (int) $connection->fetchOne('SELECT count(*) FROM '
            . self::SCHEMA
            . '.journal WHERE operation_id = :operation_id', ['operation_id' => $operationId->toString()]),
        );
    }

    public function testCompiledPolicyReceivesAuthenticatedActorInApplicationHttpRuntime(): void
    {
        $paths = $this->compileArtifacts(withAuthorizationFixture: true);
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new DatabaseMigrationRunner($connection, self::SCHEMA)->migrate();
        $http = $this->application($paths)->http();
        $actor = new ActorRef('integration-user', 'user');
        ApplicationRuntimeAuthorizationPolicy::$request = null;

        $response = $http->handle(
            new Psr17Factory()
                ->createServerRequest('GET', '/authorized')
                ->withAttribute(ActorRef::class, $actor),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"message":"authorized"}', (string) $response->getBody());
        $request = ApplicationRuntimeAuthorizationPolicy::$request;
        self::assertNotNull($request);
        self::assertSame($actor, $request->actor());
        self::assertSame($actor, $request->context()->actorContext()?->origin());
        self::assertSame($actor, $request->context()->actorContext()?->authorization());
        self::assertSame($actor, $request->context()->actorContext()?->execution());
        $policy = ApplicationRuntimeAuthorizationPolicy::$instance;
        self::assertNotNull($policy);
        self::assertSame($policy->dependency->connection, $policy->dependency->databases->connection());
        self::assertSame('blackops_application_http_unused', $policy->dependency->connection->getParams()['dbname']);
    }

    public function testMissingArtifactFailsWithoutFallbackOrCredentialExposure(): void
    {
        $credential = 'database-credential-that-must-not-appear';
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig($config, 'app', [
            'build' => [
                'operation_manifest' => $directory . '/missing-operations.php',
                'http_manifest' => $directory . '/missing-http.php',
                'container' => $directory . '/missing-container.php',
                'container_class' => 'MissingContainer',
                'container_namespace' => '',
            ],
        ]);
        $this->writeConfig($config, 'database', [
            'connection' => ['driver' => 'pdo_pgsql', 'password' => $credential],
            'schema' => self::SCHEMA,
        ]);

        try {
            Application::configure($directory)->withConfiguration()->create()->http();
            self::fail('Expected missing production artifact to fail.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('manifest file does not exist', $exception->getMessage());
            self::assertStringNotContainsString($credential, $exception->getMessage());
        }
    }

    /** @return array{operation: string, http: string, container: string, class: string, namespace: string} */
    private function compileArtifacts(bool $withAuthorizationFixture = false): array
    {
        $directory = $this->directory();
        $class = 'ApplicationHttpContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $paths = [
            'operation' => $directory . '/operations.php',
            'http' => $directory . '/http.php',
            'container' => $directory . '/container.php',
            'class' => $class,
            'namespace' => $namespace,
        ];
        $root = dirname(__DIR__, levels: 2);
        $this->requireQuickstartSource($root);
        $config = $directory . '/config';
        mkdir($config);
        mkdir($directory . '/log');
        $this->journalPath = $directory . '/log/journal.jsonl';
        $this->writeConfig($config, 'app', ['build' => [
            'application_build_id' => self::BUILD_ID,
            'operation_manifest' => $paths['operation'],
            'http_manifest' => $paths['http'],
            'container' => $paths['container'],
            'container_class' => $class,
            'container_namespace' => $namespace,
        ]]);
        $this->writeConfig($config, 'operations', [
            'discovery' => [$root . '/examples/quickstart/app/Feature'],
            'providers' => [],
        ]);
        $builder = Application::configure($directory)
            ->withConfiguration()
            ->withOperations($withAuthorizationFixture ? [ApplicationRuntimeOperationProvider::class] : [])
            ->withServices($withAuthorizationFixture ? [ApplicationRuntimeServiceProvider::class] : []);
        $status = $builder->create()->console()->run(new ArrayInput([
            'command' => 'build:compile',
        ]), new BufferedOutput());
        self::assertSame(0, $status);

        return $paths;
    }

    /** @param array{operation: string, http: string, container: string, class: string, namespace: string} $paths */
    private function application(array $paths): Application
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig($config, 'app', [
            'build' => [
                'operation_manifest' => $paths['operation'],
                'http_manifest' => $paths['http'],
                'container' => $paths['container'],
                'container_class' => $paths['class'],
                'container_namespace' => $paths['namespace'],
            ],
        ]);
        $applicationConnection = $this->connectionParameters();
        $applicationConnection['dbname'] = 'blackops_application_http_unused';
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
        $this->writeConfig($config, 'journal', ['jsonl' => [
            'enabled' => true,
            'path' => $this->journalPath,
            'delivery' => 'best_effort',
        ]]);

        return Application::configure($directory)->withConfiguration()->create();
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-application-http-' . bin2hex(random_bytes(8));
        if (!mkdir($directory) && !is_dir($directory)) {
            throw new RuntimeException('Could not create application HTTP test directory.');
        }
        $this->directories[] = $directory;

        return $directory;
    }

    private function requireQuickstartSource(string $root): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root . '/examples/quickstart/app',
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        ));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
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

final readonly class ApplicationRuntimeOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ApplicationRuntimeAuthorizedOperation::class];
    }
}

final readonly class ApplicationRuntimeServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ApplicationRuntimePolicyDependency::class);
    }
}

final readonly class ApplicationRuntimePolicyDependency
{
    public function __construct(
        public DatabaseManager $databases,
        public Connection $connection,
    ) {}
}

final readonly class ApplicationRuntimeAuthorizedValue implements OperationValue {}

final readonly class ApplicationRuntimeAuthorizedOutcome implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

#[Route(method: 'GET', path: '/authorized')]
#[OperationType('application.runtime.authorized')]
#[Authorize(ApplicationRuntimeAuthorizationPolicy::class)]
final readonly class ApplicationRuntimeAuthorizedOperation implements Operation
{
    public function handle(ApplicationRuntimeAuthorizedValue $value): ApplicationRuntimeAuthorizedOutcome
    {
        return new ApplicationRuntimeAuthorizedOutcome('authorized');
    }
}

final class ApplicationRuntimeAuthorizationPolicy implements AuthorizationPolicy
{
    public static ?AuthorizationRequest $request = null;
    public static ?self $instance = null;

    public function __construct(
        public readonly ApplicationRuntimePolicyDependency $dependency,
    ) {
        self::$instance = $this;
    }

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        self::$request = $request;

        return AuthorizationDecision::allow();
    }
}
