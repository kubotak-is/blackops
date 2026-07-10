<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\DeferredWorkerRuntime;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeServices;
use BlackOps\Internal\Execution\DeferredWorkerRuntimeStorage;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationLifecycleStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class DeferredWorkerRuntimeTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_010';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687731';
    private const CORRELATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687732';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlDeferredOperationReceiver $receiver;
    private PostgreSqlCanonicalJournalStore $journal;
    private ReflectionJsonOperationCodec $codec;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->receiver = new PostgreSqlDeferredOperationReceiver($this->connection, self::SCHEMA, 'worker-a', 30);
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->codec = new ReflectionJsonOperationCodec();
        $this->sender->migrate();
        $this->receiver->migrate();
        $this->journal->migrate();
    }

    public function testWorkerRunsClaimedOperationToCompletion(): void
    {
        $handler = new CompletingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $result = $this->runtime($handler)->run($claim);

        $row = $this->operationRow();
        $records = $this->records();

        self::assertTrue($result->isCompleted());
        self::assertInstanceOf(WorkerReportDone::class, $result->outcome());
        self::assertSame('done-weekly', $result->outcome()->message);
        self::assertSame('completed', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(6, (int) $row['next_sequence']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4, 5], array_column($records, 'sequence'));
        self::assertNotNull($records[2]->attempt);
        self::assertSame(1, $records[2]->attempt?->number);
    }

    public function testWorkerRecordsBusinessRejection(): void
    {
        $handler = new RejectingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $result = $this->runtime($handler)->run($claim);

        $row = $this->operationRow();
        $records = $this->records();

        self::assertTrue($result->isRejected());
        self::assertSame('rejected', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(5, (int) $row['next_sequence']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::OperationRejected,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4], array_column($records, 'sequence'));
    }

    public function testWorkerRecordsAttemptFailureAndRethrowsHandlerException(): void
    {
        $handler = new ThrowingWorkerReportHandler();
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        try {
            $this->runtime($handler)->run($claim);
            self::fail('Expected handler exception to be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        $row = $this->operationRow();
        $records = $this->records();

        self::assertSame('supervising', $row['state']);
        self::assertSame(1, (int) $row['attempt_number']);
        self::assertSame(5, (int) $row['next_sequence']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::OperationAccepted,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4], array_column($records, 'sequence'));
        self::assertInstanceOf(AttemptFailedData::class, $records[3]->data);
        self::assertSame(RuntimeException::class, $records[3]->data->errorType);
        self::assertSame('boom', $records[3]->data->errorMessage);
        self::assertFalse($records[3]->data->retryable);
    }

    public function testFailureReservationRejectsStaleFencingToken(): void
    {
        $this->accept();
        $claim = $this->receiver->claim(new \BlackOps\Core\Execution\ClaimRequest(
            new DateTimeImmutable('2026-07-10T00:01:00.000000Z'),
        ));

        self::assertNotNull($claim);

        $stale = new OperationClaim($claim->message(), self::OPERATION_ID . ':999');

        $this->expectException(DeferredTransportException::class);

        new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA)->reserveFailed(
            $stale,
            new DateTimeImmutable('2026-07-10T00:02:00.000000Z'),
        );
    }

    private function accept(): void
    {
        $metadata = $this->metadata();
        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
        );
        $value = new WorkerReportValue('weekly');
        $encoded = $this->codec->encode($metadata, $value, $context);
        $envelope = new OperationEnvelope(new WorkerReportOperation(), $value, $context, new Deferred());
        $identifiers = new IdentifierFactory(new DeferredWorkerAcceptanceUuidv7Generator(), new DeferredWorkerClock());
        $orchestrator = new DeferredAcceptanceOrchestrator(
            $this->connection,
            $this->sender,
            $this->journal,
            new JournalRecordFactory($identifiers, new DeferredWorkerClock()),
        );

        $orchestrator->accept(
            new DeferredOperationMessage(
                $context->operationId(),
                $encoded->operationType(),
                $encoded->schemaVersion(),
                $encoded->encodedPayload(),
                $encoded->encodedContext(),
                $context->receivedAt(),
            ),
            $envelope,
            $metadata,
        );
    }

    private function runtime(OperationHandler $handler): DeferredWorkerRuntime
    {
        $clock = new DeferredWorkerClock();
        $identifiers = new IdentifierFactory(new DeferredWorkerRuntimeUuidv7Generator(), $clock);

        return new DeferredWorkerRuntime(
            new DeferredWorkerRuntimeServices(
                new OperationRegistry([$this->metadata()]),
                $this->codec,
                new ExecutionContextFactory($identifiers, $clock),
                new HandlerResolver(new DeferredWorkerContainer($handler)),
            ),
            new DeferredWorkerRuntimeStorage(
                $this->connection,
                new JournalRecordFactory($identifiers, $clock),
                $this->journal,
                new PostgreSqlDeferredOperationLifecycleStore($this->connection, self::SCHEMA),
                $clock,
            ),
        );
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            WorkerReportOperation::class,
            WorkerReportValue::class,
            WorkerReportHandler::class,
            WorkerReportDone::class,
            Deferred::class,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::SCHEMA . '.operations');

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<JournalRecord>
     */
    private function records(): array
    {
        return array_values(iterator_to_array($this->journal->records(OperationId::fromString(self::OPERATION_ID))));
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

final readonly class WorkerReportOperation implements Operation {}

final readonly class WorkerReportValue implements OperationValue
{
    public function __construct(
        public string $reportName,
    ) {}
}

final readonly class WorkerReportDone implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

/** @implements OperationHandler<WorkerReportValue, WorkerReportDone> */
abstract class WorkerReportHandler implements OperationHandler {}

final class CompletingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        $value = $operation->value();

        if (!$value instanceof WorkerReportValue) {
            throw new \LogicException('Worker report handler requires WorkerReportValue.');
        }

        return OperationResult::completed(new WorkerReportDone('done-' . $value->reportName));
    }
}

final class RejectingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::rejected(RejectionReason::businessRule('report_rejected'));
    }
}

final class ThrowingWorkerReportHandler extends WorkerReportHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new RuntimeException('boom');
    }
}

final readonly class DeferredWorkerContainer implements ContainerInterface
{
    public function __construct(
        private OperationHandler $handler,
    ) {}

    public function get(string $id): mixed
    {
        return $this->handler;
    }

    public function has(string $id): bool
    {
        return $id === WorkerReportHandler::class;
    }
}

final readonly class DeferredWorkerClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:02:00.000000Z');
    }
}

final class DeferredWorkerAcceptanceUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687733',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687734',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}

final class DeferredWorkerRuntimeUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687735',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687736',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687737',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687738',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687739',
        '019f32ab-2be0-7b38-a0a7-1ab2f968773a',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
