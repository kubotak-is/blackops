<?php

declare(strict_types=1);

namespace BlackOps\Tests\Integration;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Console\CompileBuildArtifactsCommand;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FilesystemIterator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplicationHttpRuntimeTest extends TestCase
{
    private const SCHEMA = 'blackops_application_http';
    private const BUILD_ID = 'application-http-runtime';

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
        $welcome = $http->handle($psr17->createServerRequest('GET', '/welcome')->withHeader(
            'X-Sample-Token',
            'sample-token',
        ));
        self::assertSame(200, $welcome->getStatusCode());
        self::assertSame('{"message":"Welcome to BlackOps"}', (string) $welcome->getBody());

        $report = $http->handle(
            $psr17
                ->createServerRequest('POST', '/reports')
                ->withBody($psr17->createStream(json_encode([
                    'reportName' => 'weekly',
                    'apiToken' => 'sample-api-token',
                ], JSON_THROW_ON_ERROR))),
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
    private function compileArtifacts(): array
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
        $status = new CommandTester(new CompileBuildArtifactsCommand())->execute([
            'operation-providers' => $root . '/examples/mvp/operation-providers.php',
            'service-providers' => $root . '/examples/mvp/service-providers.php',
            'operation-manifest' => $paths['operation'],
            'http-manifest' => $paths['http'],
            'container' => $paths['container'],
            '--application-build-id' => self::BUILD_ID,
            '--container-class' => $class,
            '--container-namespace' => $namespace,
        ]);
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
        $this->writeConfig($config, 'database', [
            'connection' => $this->connectionParameters(),
            'schema' => self::SCHEMA,
        ]);

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
