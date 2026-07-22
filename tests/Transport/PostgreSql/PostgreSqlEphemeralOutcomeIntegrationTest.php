<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Attribute\Route;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Internal\Status\DefaultOperationStatusQuery;
use BlackOps\Internal\Status\PostgreSqlOperationStatusSource;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\Status\OperationStatusUnavailable;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class PostgreSqlEphemeralOutcomeIntegrationTest extends TestCase
{
    private const string SCHEMA = 'blackops_p18_006b_ephemeral';
    private const string INPUT_SECRET = 'input-value-must-not-persist';
    public const string OUTPUT_SECRET = 'output-value-must-not-persist';

    public function testPostgreSqlJournalObserverOutcomeStoreAndStatusRemainEphemeral(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new PostgreSqlDeferredOperationSender($connection, self::SCHEMA)->migrate();
        $journal = new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA);
        $journal->migrate();
        $clock = new EphemeralIntegrationClock();
        $identifiers = new IdentifierFactory(new EphemeralSequentialUuidGenerator(), $clock);
        $operation = new PostgreSqlEphemeralOperation();
        $metadata = new OperationMetadataCompiler()->compile($operation::class);
        $registry = new OperationRegistry([$metadata]);
        $observer = new EphemeralRecordingObserver();
        $container = new EphemeralOperationContainer($operation);
        $dispatcher = new InlineDispatcher(
            $registry,
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new JournalRecordFactory($identifiers, $clock),
            $journal,
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                new JournalObserverAggregator([new JournalObserverBinding('ephemeral', $observer)]),
            ),
            scope: new ExecutionScopeProvider(),
        );

        $result = $dispatcher->dispatch($operation, new PostgreSqlEphemeralValue(self::INPUT_SECRET));
        $outcome = $result->outcome();
        self::assertInstanceOf(PostgreSqlEphemeralTokenIssued::class, $outcome);
        self::assertSame(self::OUTPUT_SECRET, $outcome->token);

        $operationIdValue = $connection->fetchOne(
            'SELECT operation_id::text FROM ' . self::SCHEMA . '.journal ORDER BY sequence LIMIT 1',
        );
        self::assertIsString($operationIdValue);
        $operationId = OperationId::fromString($operationIdValue);
        $records = array_values(iterator_to_array($journal->records($operationId)));
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_column($records, 'event'),
        );
        self::assertInstanceOf(EmptyJournalData::class, $records[0]->data);
        self::assertInstanceOf(OperationCompletedData::class, $records[3]->data);
        self::assertInstanceOf(\BlackOps\Core\EmptyOutcome::class, $records[3]->data->outcome);

        $databaseDump = $connection->fetchOne(
            "SELECT string_agg(convert_from(encoded_record, 'UTF8'), '') FROM " . self::SCHEMA . '.journal',
        );
        self::assertIsString($databaseDump);
        foreach ([self::INPUT_SECRET, self::OUTPUT_SECRET, '"password"', '"token"'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $databaseDump);
        }
        $observed = serialize($observer->records);
        self::assertStringNotContainsString(self::INPUT_SECRET, $observed);
        self::assertStringNotContainsString(self::OUTPUT_SECRET, $observed);
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM ' . self::SCHEMA . '.outcomes'));

        $authorizer = new EphemeralStatusAuthorizer();
        $status = new DefaultOperationStatusQuery(
            new PostgreSqlOperationStatusSource($connection, $registry, self::SCHEMA),
            $authorizer,
        )->find($operationId);
        self::assertInstanceOf(OperationStatusUnavailable::class, $status);
        self::assertSame(1, $authorizer->calls);
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

final readonly class PostgreSqlEphemeralValue implements OperationValue
{
    public function __construct(
        #[Sensitive]
        public string $password,
    ) {}
}

final readonly class PostgreSqlEphemeralTokenIssued implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
    ) {}
}

#[OperationType('identity.issue')]
#[Route('POST', '/identity/issue')]
#[ExecuteWith(Inline::class)]
final readonly class PostgreSqlEphemeralOperation implements Operation
{
    public function handle(PostgreSqlEphemeralValue $value): PostgreSqlEphemeralTokenIssued
    {
        return new PostgreSqlEphemeralTokenIssued(PostgreSqlEphemeralOutcomeIntegrationTest::OUTPUT_SECRET);
    }
}

final readonly class EphemeralOperationContainer implements ContainerInterface
{
    public function __construct(
        private object $operation,
    ) {}

    public function get(string $id): mixed
    {
        return $this->operation;
    }

    public function has(string $id): bool
    {
        return $id === $this->operation::class;
    }
}

final class EphemeralRecordingObserver implements JournalObserver
{
    /** @var list<ObservedJournalRecord> */
    public array $records = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final class EphemeralStatusAuthorizer implements OperationStatusAuthorizer
{
    public int $calls = 0;

    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        ++$this->calls;

        return OperationStatusAuthorizationDecision::allow();
    }
}

final readonly class EphemeralIntegrationClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-22T00:00:00.000000Z');
    }
}

final class EphemeralSequentialUuidGenerator implements Uuidv7Generator
{
    private int $index = 0;

    public function generate(DateTimeImmutable $time): string
    {
        $suffix = str_pad((string) ++$this->index, 12, '0', STR_PAD_LEFT);

        return '019f32ab-2be0-7b38-a0a7-' . $suffix;
    }
}
