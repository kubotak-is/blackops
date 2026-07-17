<?php

declare(strict_types=1);

namespace BlackOps\Tests\Integration;

use BlackOps\Application\Application;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Supervision\ExponentialBackoffSupervisionPolicy;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Database\RuntimeDatabaseServiceInjector;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\DirectClaimExecutionGuard;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
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
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector;
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
    private const CREATE_ORDER = 'App\\Feature\\Order\\CreateOrder\\CreateOrder';
    private const GENERATE_REPORT = 'App\\Feature\\Report\\GenerateReport\\GenerateReport';
    private const REPORT_GENERATED = 'App\\Feature\\Report\\GenerateReport\\ReportGenerated';
    private const WELCOME_SHOWN = 'App\\Feature\\Welcome\\ShowWelcome\\WelcomeShown';

    public function testCompiledSampleRunsInlineAndDeferredAcrossWorkerRestart(): void
    {
        $paths = $this->compileArtifacts();
        $migrationConnection = $this->connection();
        $migrationConnection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new DatabaseMigrationRunner($migrationConnection, self::SCHEMA)->migrate();

        $databases = new DoctrineDatabaseManager('app', ['app' => $this->connectionParameters()]);
        $httpConnection = $databases->connection();
        $httpArtifacts = $this->loadArtifacts($paths);
        new RuntimeDatabaseServiceInjector()->inject($httpArtifacts->container, $databases);
        $executionScope = new ExecutionScopeProvider();
        $transactionRuntime = new RuntimeTransactionServiceInjector()->inject(
            $httpArtifacts->container,
            $databases,
            $executionScope,
        );
        self::assertCount(3, $httpArtifacts->operations->all());
        self::assertSame(self::CREATE_ORDER, $httpArtifacts->operations->findByTypeId('order.create')?->definition);
        self::assertSame(
            self::GENERATE_REPORT,
            $httpArtifacts->operations->findByTypeId('report.generate')?->definition,
        );
        self::assertSame('order.create', $httpArtifacts->http->routes['POST']['/orders']);
        self::assertSame('report.generate', $httpArtifacts->http->routes['POST']['/reports']);

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
            new ProductionRuntimeDependencies(
                $clock,
                $journal,
                $psr17,
                $psr17,
                executionScope: $executionScope,
                journalObservations: $observations,
                operationTransactions: new OperationTransactionCoordinator(
                    $transactionRuntime,
                    $databases,
                    $httpConnection,
                ),
            ),
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
                authorization: new AuthorizationEvaluator(new AuthorizationPolicyResolver($httpArtifacts->container)),
            ),
        );
        $http = new OperationRequestHandler(
            $inline->httpRoutes,
            new OperationValueBinder(),
            $inline->dispatcher,
            new JsonOperationResponder($psr17, $psr17),
            $psr17,
            $inline->dispatcher,
            $acceptor,
        );

        $actor = new ActorRef('mvp-authenticated-user', 'user');
        $welcome = $http->handle($psr17->createServerRequest('GET', '/welcome')->withAttribute(
            ActorRef::class,
            $actor,
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
        foreach ($welcomeRecords as $record) {
            self::assertEquals($actor, $record->operation->actorContext?->origin());
            self::assertEquals($actor, $record->operation->actorContext?->authorization());
            self::assertEquals($actor, $record->operation->actorContext?->execution());
        }
        self::assertInstanceOf(OperationReceivedData::class, $welcomeRecords[0]->data);
        self::assertSame([], get_object_vars($welcomeRecords[0]->data->value));
        self::assertInstanceOf(self::WELCOME_SHOWN, $welcomeRecords[3]->data->outcome);
        rewind($jsonl);
        $observedJsonl = stream_get_contents($jsonl);
        self::assertIsString($observedJsonl);
        self::assertStringNotContainsString($actor->id(), $observedJsonl);
        self::assertStringContainsString('[masked]', $observedJsonl);
        foreach (array_filter(explode("\n", $observedJsonl)) as $line) {
            /** @var array<string, mixed> $observed */
            $observed = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('[masked]', $observed['operation']['actors']['origin']['id']);
            self::assertSame('[masked]', $observed['operation']['actors']['authorization']['id']);
            self::assertSame('[masked]', $observed['operation']['actors']['execution']['id']);
        }

        $reportRecipient = 'deferred-recipient@example.com';
        $reportResponse = $http->handle(
            $psr17
                ->createServerRequest('POST', '/reports')
                ->withBody($psr17->createStream(json_encode([
                    'reportName' => 'weekly',
                    'recipientEmail' => $reportRecipient,
                ], JSON_THROW_ON_ERROR)))
                ->withAttribute(ActorRef::class, $actor),
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
        foreach ($this->records($journal, $reportOperationId) as $record) {
            self::assertEquals($actor, $record->operation->actorContext?->origin());
            self::assertEquals($actor, $record->operation->actorContext?->authorization());
            self::assertEquals($actor, $record->operation->actorContext?->execution());
        }
        $acceptedRecords = $this->records($journal, $reportOperationId);
        self::assertInstanceOf(OperationReceivedData::class, $acceptedRecords[0]->data);
        self::assertSame($reportRecipient, $acceptedRecords[0]->data->value->recipientEmail);

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
            $this->workerRuntime($firstWorkerConnection, $firstWorkerArtifacts, $clock, 'mvp-worker-first')->run(
                $firstClaim,
            );
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
        $firstAttemptRecords = $this->records(
            new PostgreSqlCanonicalJournalStore($firstWorkerConnection, self::SCHEMA),
            $reportOperationId,
        );
        foreach (array_slice($firstAttemptRecords, 2) as $record) {
            self::assertEquals($actor, $record->operation->actorContext?->origin());
            self::assertEquals($actor, $record->operation->actorContext?->authorization());
            self::assertSame('mvp-worker-first', $record->operation->actorContext?->execution()->id());
            self::assertSame('system', $record->operation->actorContext?->execution()->type());
        }

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
        $result = $this->workerRuntime(
            $secondWorkerConnection,
            $secondWorkerArtifacts,
            $clock,
            'mvp-worker-restarted',
        )->run($secondClaim);

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
        $completedRecords = $this->records(
            new PostgreSqlCanonicalJournalStore($secondWorkerConnection, self::SCHEMA),
            $reportOperationId,
        );
        foreach (array_slice($completedRecords, 5) as $record) {
            self::assertEquals($actor, $record->operation->actorContext?->origin());
            self::assertEquals($actor, $record->operation->actorContext?->authorization());
            self::assertSame('mvp-worker-restarted', $record->operation->actorContext?->execution()->id());
            self::assertSame('system', $record->operation->actorContext?->execution()->type());
        }

        $outcome = new PostgreSqlOutcomeStore($this->connection(), self::SCHEMA)->find($reportOperationId);
        self::assertInstanceOf(OutcomeRecord::class, $outcome);
        self::assertInstanceOf(self::REPORT_GENERATED, $outcome->outcome());
        self::assertSame('/reports/generated/weekly.json', $outcome->outcome()->location);
        $observedReport = new ObservedJournalRecordProjector(new SensitiveProjectionFilter())->project(
            $completedRecords[0],
        );
        self::assertSame('[masked]', $observedReport->data['value']['recipientEmail']);
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
        $this->writeConfig($config, 'database', [
            'default' => 'app',
            'connections' => [
                'app' => $this->connectionParameters(),
                'framework' => $this->connectionParameters(),
            ],
            'framework' => [
                'connection' => 'framework',
                'schema' => self::SCHEMA,
            ],
        ]);
        $status = Application::configure($directory)
            ->withConfiguration()
            ->withServices([\App\ApplicationServiceProvider::class])
            ->create()
            ->console()
            ->run(new ArrayInput(['command' => 'build:compile']), new BufferedOutput());

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
        require_once $root . '/examples/quickstart/app/Feature/Order/OrderRepository.php';
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
        string $workerId,
    ): DeferredWorkerRuntime {
        $identifiers = new IdentifierFactory(new SymfonyUuidv7Generator(), $clock);

        return new DeferredWorkerRuntime(
            new DeferredWorkerRuntimeServices(
                $artifacts->operations,
                new ReflectionJsonOperationCodec(),
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver($artifacts->container),
                new ActorRef($workerId, 'system'),
                new AuthorizationEvaluator(new AuthorizationPolicyResolver($artifacts->container)),
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
