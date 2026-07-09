<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class PostgreSqlInlineDispatcherIntegrationTest extends TestCase
{
    private const SCHEMA = 'blackops_p1_016';

    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $store;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->store = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->store->migrate();
    }

    public function testCompletedInlineDispatchPersistsLifecycleJournalToPostgreSql(): void
    {
        $result = $this->dispatcher(new PostgreSqlCompletingHandler())->dispatch(
            new PostgreSqlDispatchOperation(),
            new PostgreSqlDispatchValue('hello'),
        );

        $records = $this->recordsForOnlyOperation();

        self::assertTrue($result->isCompleted());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4], array_column($records, 'sequence'));
        self::assertSame(
            ['postgres.inline', 'postgres.inline', 'postgres.inline', 'postgres.inline'],
            array_map(static fn(JournalRecord $record): string => $record->operation->type, $records),
        );
        self::assertInstanceOf(OperationReceivedData::class, $records[0]->data);
        self::assertInstanceOf(PostgreSqlDispatchValue::class, $records[0]->data->value);
        self::assertSame('hello', $records[0]->data->value->message);
        self::assertInstanceOf(OperationCompletedData::class, $records[3]->data);
        self::assertInstanceOf(EmptyOutcome::class, $records[3]->data->outcome);
    }

    public function testRejectedInlineDispatchPersistsLifecycleJournalToPostgreSql(): void
    {
        $result = $this->dispatcher(new PostgreSqlRejectingHandler())->dispatch(
            new PostgreSqlDispatchOperation(),
            new PostgreSqlDispatchValue('hello'),
        );

        $records = $this->recordsForOnlyOperation();

        self::assertTrue($result->isRejected());
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::OperationRejected],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3], array_column($records, 'sequence'));
        self::assertInstanceOf(OperationRejectedData::class, $records[2]->data);
        self::assertSame(RejectionCategory::Conflict, $records[2]->data->reason->category());
        self::assertSame('postgres_inline_rejected', $records[2]->data->reason->code());
    }

    private function dispatcher(OperationHandler $handler): InlineDispatcher
    {
        $metadata = new OperationMetadata(
            'postgres.inline',
            PostgreSqlDispatchOperation::class,
            PostgreSqlDispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
        );
        $container = new class($handler) implements ContainerInterface {
            public function __construct(
                private readonly object $service,
            ) {}

            public function get(string $id): mixed
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-08T00:00:00.000000Z');
            }
        };
        $identifiers = new IdentifierFactory(new SequentialUuidv7Generator(), $clock);

        return new InlineDispatcher(
            new OperationRegistry([$metadata]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new JournalRecordFactory($identifiers, $clock),
            $this->store,
        );
    }

    /**
     * @return list<JournalRecord>
     */
    private function recordsForOnlyOperation(): array
    {
        $operationId = $this->connection->fetchOne(
            'SELECT operation_id::text FROM ' . self::SCHEMA . '.journal LIMIT 1',
        );

        self::assertIsString($operationId);

        return array_values(iterator_to_array($this->store->records(OperationId::fromString($operationId))));
    }

    private function connection(): Connection
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (int) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => $port,
            'dbname' => $db,
            'user' => $user,
            'password' => $password,
        ]);
    }
}

final readonly class PostgreSqlDispatchOperation implements Operation {}

final readonly class PostgreSqlDispatchValue implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}

/** @implements OperationHandler<PostgreSqlDispatchValue, EmptyOutcome> */
final readonly class PostgreSqlCompletingHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

/** @implements OperationHandler<PostgreSqlDispatchValue, EmptyOutcome> */
final readonly class PostgreSqlRejectingHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::rejected(RejectionReason::conflict('postgres_inline_rejected'));
    }
}

final class SequentialUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687698',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687699',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769b',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769c',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
