<?php

declare(strict_types=1);

namespace BlackOps\Tests\Integration;

use BlackOps\Application\Application;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\DirectClaimExecutionGuard;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\SupervisedHandlerFailureException;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Internal\Runtime\ProductionRuntimeDependencies;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Logging\JsonlJournalObserver;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLifecycleStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FilesystemIterator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MvpSampleEndToEndTest extends TestCase
{
    private const SCHEMA = 'blackops_mvp_sample';
    private const BUILD_ID = 'mvp-sample-e2e';
    private const GENERATE_REPORT = 'App\\Feature\\Report\\GenerateReport\\GenerateReport';
    private const REPORT_GENERATED = 'App\\Feature\\Report\\GenerateReport\\ReportGenerated';
    private const WELCOME_SHOWN = 'App\\Feature\\Welcome\\ShowWelcome\\WelcomeShown';

    public function testCompiledSampleRunsInlineAndDeferredAcrossWorkerRestart(): void
    {
        $paths = $this->compileArtifacts();
        $migrationConnection = $this->connection();
        $migrationConnection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new DatabaseMigrationRunner($migrationConnection, self::SCHEMA)->migrate();

        $httpConnection = $this->connection();
        $httpArtifacts = $this->loadArtifacts($paths);
        self::assertCount(2, $httpArtifacts->operations->all());
        self::assertSame(
            self::GENERATE_REPORT,
            $httpArtifacts->operations->findByTypeId('report.generate')?->definition,
        );
        self::assertSame('/reports', array_key_first($httpArtifacts->http->routes['POST']));

        $clock = new MvpSampleClock(new DateTimeImmutable('2026-07-12T00:00:00.123456Z'));
        $journal = new PostgreSqlCanonicalJournalStore($httpConnection, self::SCHEMA);
        $jsonl = fopen('php://temp', 'w+b');
        self::assertIsResource($jsonl);
        $observations = new JournalObservationPipeline(
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            new JournalObserverAggregator([
                new JournalObserverBinding('mvp-jsonl', new JsonlJournalObserver($jsonl)),
            ]),
        );
        $psr17 = new Psr17Factory();
        $inline = new ProductionRuntimeComposer()->composeWithDependencies(
            $httpArtifacts,
            new ProductionRuntimeDependencies($clock, $journal, $psr17, $psr17, journalObservations: $observations),
        );
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);
        $codec = new ReflectionJsonOperationCodec();
        $sender = new PostgreSqlDeferredOperationSender($httpConnection, self::SCHEMA, $clock->now());
        $acceptor = new DeferredHttpOperationAcceptor(
            $httpArtifacts->operations,
            new ExecutionContextFactory($identifiers, $clock),
            $codec,
            new DeferredAcceptanceOrchestrator(
                $httpConnection,
                $sender,
                $journal,
                new JournalRecordFactory($identifiers, $clock),
            ),
        );
        $http = new OperationRequestHandler(
            $inline->httpRoutes,
            new OperationValueBinder(),
            $inline->dispatcher,
            new JsonOperationResponder($psr17, $psr17),
            $psr17,
            $acceptor,
        );

        $sensitiveToken = 'inline-secret-token';
        $welcome = $http->handle($psr17->createServerRequest('GET', '/welcome')->withHeader(
            'X-Sample-Token',
            $sensitiveToken,
        ));
        self::assertSame(200, $welcome->getStatusCode());
        self::assertSame('{"message":"Welcome to BlackOps"}', (string) $welcome->getBody());
        $welcomeOperationId = $this->operationIdForType($httpConnection, 'welcome.show');
        $welcomeRecords = $this->records($journal, $welcomeOperationId);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $welcomeRecords),
        );
        self::assertInstanceOf(OperationReceivedData::class, $welcomeRecords[0]->data);
        self::assertSame($sensitiveToken, $welcomeRecords[0]->data->value->sampleToken);
        self::assertInstanceOf(self::WELCOME_SHOWN, $welcomeRecords[3]->data->outcome);
        rewind($jsonl);
        $observedJsonl = stream_get_contents($jsonl);
        self::assertIsString($observedJsonl);
        self::assertStringNotContainsString($sensitiveToken, $observedJsonl);
        self::assertStringContainsString('[masked]', $observedJsonl);

        $reportToken = 'deferred-secret-token';
        $reportResponse = $http->handle(
            $psr17
                ->createServerRequest('POST', '/reports')
                ->withBody($psr17->createStream(json_encode([
                    'reportName' => 'weekly',
                    'apiToken' => $reportToken,
                ], JSON_THROW_ON_ERROR))),
        );
        self::assertSame(202, $reportResponse->getStatusCode());
        /** @var array{status: string, operationId: string, acceptedAt: string} $acknowledgement */
        $acknowledgement = json_decode((string) $reportResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('accepted', $acknowledgement['status']);
        $reportOperationId = OperationId::fromString($acknowledgement['operationId']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted],
            $this->events($journal, $reportOperationId),
        );

        $clock->set(new DateTimeImmutable('2026-07-12T00:01:00.000000Z'));
        $firstWorkerConnection = $this->connection();
        $firstWorkerArtifacts = $this->loadArtifacts($paths);
        $firstReceiver = new PostgreSqlDeferredOperationReceiver(
            $firstWorkerConnection,
            self::SCHEMA,
            'mvp-worker-first',
            30,
        );
        $firstClaim = $firstReceiver->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($firstClaim);

        try {
            $this->workerRuntime($firstWorkerConnection, $firstWorkerArtifacts, $clock)->run($firstClaim);
            self::fail('The first report attempt must request a retry.');
        } catch (SupervisedHandlerFailureException $exception) {
            self::assertStringContainsString('temporarily unavailable', $exception->getPrevious()?->getMessage() ?? '');
        }

        self::assertSame('retry_scheduled', $this->operationState($firstWorkerConnection, $reportOperationId));
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::AttemptRetryScheduled,
            ],
            $this->events(
                new PostgreSqlCanonicalJournalStore($firstWorkerConnection, self::SCHEMA),
                $reportOperationId,
            ),
        );

        $clock->set(new DateTimeImmutable('2026-07-12T00:01:02.000000Z'));
        $secondWorkerConnection = $this->connection();
        $secondWorkerArtifacts = $this->loadArtifacts($paths);
        self::assertNotSame($firstWorkerArtifacts->container, $secondWorkerArtifacts->container);
        $secondReceiver = new PostgreSqlDeferredOperationReceiver(
            $secondWorkerConnection,
            self::SCHEMA,
            'mvp-worker-restarted',
            30,
        );
        $secondClaim = $secondReceiver->claim(new ClaimRequest($clock->now()));
        self::assertNotNull($secondClaim);
        $result = $this->workerRuntime($secondWorkerConnection, $secondWorkerArtifacts, $clock)->run($secondClaim);

        self::assertTrue($result->isCompleted());
        self::assertInstanceOf(self::REPORT_GENERATED, $result->outcome());
        self::assertSame('weekly', $result->outcome()->reportName);
        self::assertSame('completed', $this->operationState($secondWorkerConnection, $reportOperationId));
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::AttemptRetryScheduled,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            $this->events(
                new PostgreSqlCanonicalJournalStore($secondWorkerConnection, self::SCHEMA),
                $reportOperationId,
            ),
        );

        $outcome = new PostgreSqlOutcomeStore($this->connection(), self::SCHEMA)->find($reportOperationId);
        self::assertInstanceOf(OutcomeRecord::class, $outcome);
        self::assertInstanceOf(self::REPORT_GENERATED, $outcome->outcome());
        self::assertSame('/reports/generated/weekly.json', $outcome->outcome()->location);
    }

    /** @return array{operation: string, http: string, container: string, class: string, namespace: string} */
    private function compileArtifacts(): array
    {
        $root = dirname(__DIR__, levels: 2);
        $this->requireQuickstartSource($root);
        $directory = sys_get_temp_dir() . '/blackops-mvp-sample-' . bin2hex(random_bytes(8));
        if (!mkdir($directory) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the MVP sample artifact directory.');
        }
        $class = 'MvpSampleContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $paths = [
            'operation' => $directory . '/operations.php',
            'http' => $directory . '/http.php',
            'container' => $directory . '/container.php',
            'class' => $class,
            'namespace' => $namespace,
        ];
        $config = $directory . '/config';
        mkdir($config);
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
        $status = Application::configure($directory)
            ->withConfiguration()
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'blackops:build:compile']), new BufferedOutput());

        self::assertSame(0, $status);

        return $paths;
    }

    /** @param array<array-key, mixed> $configuration */
    private function writeConfig(string $directory, string $name, array $configuration): void
    {
        file_put_contents(
            $directory . '/' . $name . '.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($configuration, return: true) . ";\n",
        );
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

    /**
     * @param array{operation: string, http: string, container: string, class: string, namespace: string} $paths
     */
    private function loadArtifacts(array $paths): \BlackOps\Internal\Runtime\ProductionRuntimeArtifacts
    {
        return new ProductionRuntimeArtifactLoader()->load(
            $paths['operation'],
            $paths['http'],
            $paths['container'],
            $paths['class'],
            $paths['namespace'],
        );
    }

    private function workerRuntime(
        Connection $connection,
        \BlackOps\Internal\Runtime\ProductionRuntimeArtifacts $artifacts,
        ClockInterface $clock,
    ): DeferredWorkerRuntime {
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);

        return new DeferredWorkerRuntime(
            new DeferredWorkerRuntimeServices(
                $artifacts->operations,
                new ReflectionJsonOperationCodec(),
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver($artifacts->container),
                new ExponentialBackoffSupervisionPolicy(jitterRatio: 0.0),
            ),
            new DeferredWorkerRuntimeStorage(
                $connection,
                new JournalRecordFactory($identifiers, $clock),
                new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA),
                new PostgreSqlDeferredOperationLifecycleStore($connection, self::SCHEMA),
                $clock,
                new PostgreSqlOutcomeStore($connection, self::SCHEMA),
            ),
            new DirectClaimExecutionGuard(),
        );
    }

    private function operationIdForType(Connection $connection, string $type): OperationId
    {
        $operationId = $connection->fetchOne(
            'SELECT operation_id::text
            FROM ' . self::SCHEMA . '.journal
            WHERE operation_id IN (
                SELECT operation_id
                FROM ' . self::SCHEMA . '.journal
                WHERE convert_from(encoded_record, \'UTF8\') LIKE :type
            )
            LIMIT 1',
            ['type' => '%"type":"' . $type . '"%'],
        );
        self::assertIsString($operationId);

        return OperationId::fromString($operationId);
    }

    private function operationState(Connection $connection, OperationId $operationId): string
    {
        $state = $connection->fetchOne('SELECT state FROM '
        . self::SCHEMA
        . '.operations WHERE operation_id = :operation_id', ['operation_id' => $operationId->toString()]);
        self::assertIsString($state);

        return $state;
    }

    /** @return list<JournalEvent> */
    private function events(PostgreSqlCanonicalJournalStore $journal, OperationId $operationId): array
    {
        return array_map(
            static fn(JournalRecord $record): JournalEvent => $record->event,
            $this->records($journal, $operationId),
        );
    }

    /** @return list<JournalRecord> */
    private function records(PostgreSqlCanonicalJournalStore $journal, OperationId $operationId): array
    {
        return array_values(iterator_to_array($journal->records($operationId)));
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }
}

final class MvpSampleClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
