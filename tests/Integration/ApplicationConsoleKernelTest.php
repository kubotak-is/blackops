<?php

declare(strict_types=1);

namespace BlackOps\Tests\Integration;

use BlackOps\Application\Application;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
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

        $operations = $this->runCommand($kernel, 'blackops:operation:list');
        self::assertStringContainsString('welcome.show', $operations);
        self::assertStringContainsString('report.generate', $operations);
        $this->runCommand($kernel, 'blackops:build:compile');
        self::assertFileExists($directory . '/var/build/operations.php');
        self::assertFileExists($directory . '/var/build/http.php');
        self::assertFileExists($directory . '/var/build/container.php');
        self::assertFalse($this->schemaExists($connection));

        $status = $this->runCommand($kernel, 'blackops:database:status');
        self::assertStringContainsString('pending: 2', $status);
        self::assertFalse($this->schemaExists($connection));
        $this->runCommand($kernel, 'blackops:database:migrate');
        self::assertTrue($this->schemaExists($connection));

        $psr17 = new Psr17Factory();
        $response = $application
            ->http()
            ->handle(
                $psr17
                    ->createServerRequest('POST', '/reports')
                    ->withBody($psr17->createStream(json_encode([
                        'reportName' => 'weekly',
                        'apiToken' => 'console-worker-token',
                    ], JSON_THROW_ON_ERROR))),
            );
        self::assertSame(202, $response->getStatusCode());
        /** @var array{operationId: string} $acknowledgement */
        $acknowledgement = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        $operationId = OperationId::fromString($acknowledgement['operationId']);

        $this->assertWorkerComposition($application);
        $this->runCommand($kernel, 'blackops:worker:run', ['--iterations' => '1', '--idle-sleep-milliseconds' => '1']);
        self::assertSame('retry_scheduled', $connection->fetchOne('SELECT state FROM '
        . self::SCHEMA
        . '.operations WHERE operation_id = :operation_id', ['operation_id' => $operationId->toString()]));

        self::assertStringContainsString('Total: 0', $this->runCommand($kernel, 'blackops:retention:plan'));
        self::assertStringContainsString('Retention purge dry run', $this->runCommand(
            $kernel,
            'blackops:retention:purge',
            [
                '--dry-run' => true,
            ],
        ));
        self::assertStringContainsString('Scheduler run completed', $this->runCommand(
            $kernel,
            'blackops:scheduler:run',
        ));
    }

    private function application(string $directory): Application
    {
        $root = dirname(__DIR__, levels: 2);
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
                'container_class' => 'ConsoleIntegrationContainer',
                'container_namespace' => __NAMESPACE__ . '\\Generated',
            ],
        ]);
        $this->writeConfig($config, 'database', [
            'connection' => $this->connectionParameters(),
            'schema' => self::SCHEMA,
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

        return Application::configure($directory)->withConfiguration()->create();
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
