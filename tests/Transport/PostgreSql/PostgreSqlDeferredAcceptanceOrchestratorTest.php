<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class PostgreSqlDeferredAcceptanceOrchestratorTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_006';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const CORRELATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687698';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlCanonicalJournalStore $journal;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.123456Z'),
        );
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
        $this->sender->migrate();
        $this->journal->migrate();
    }

    public function testAcceptStoresOperationStateAndAcceptanceJournalInOneTransaction(): void
    {
        $acknowledgement = $this->orchestrator()->accept($this->message(), $this->envelope(), $this->metadata());

        $operationRow = $this->operationRow();
        $records = $this->records();

        self::assertSame(self::OPERATION_ID, $acknowledgement->operationId()->toString());
        self::assertSame('accepted', $operationRow['state']);
        self::assertSame(2, (int) $operationRow['state_version']);
        self::assertSame(3, (int) $operationRow['next_sequence']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertSame('deferred', $records[0]->operation->strategy);
        self::assertInstanceOf(OperationReceivedData::class, $records[0]->data);
        self::assertInstanceOf(DeferredAcceptedValue::class, $records[0]->data->value);
        self::assertSame('report-1', $records[0]->data->value->reportId);
        self::assertInstanceOf(EmptyJournalData::class, $records[1]->data);
    }

    public function testDuplicateOperationRollsBackWithoutAdditionalJournalRecords(): void
    {
        $this->orchestrator()->accept($this->message(), $this->envelope(), $this->metadata());

        try {
            $this->orchestrator()->accept($this->message(), $this->envelope(), $this->metadata());
            self::fail('Expected duplicate operation to fail.');
        } catch (DeferredTransportException) {
        }

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertCount(2, $this->records());
    }

    public function testAuthorizationRejectionCommitsReceivedAndRejectedWithoutTransportRow(): void
    {
        $policy = new DeferredAcceptancePolicy(AuthorizationDecision::forbid('authorization.report_forbidden'));
        $actor = new ActorRef('user-123', 'user');
        $result = $this->orchestrator($policy)->accept(
            $this->message(),
            $this->envelope(new ActorContext($actor, $actor, $actor)),
            $this->metadata(DeferredAcceptancePolicy::class),
        );

        self::assertInstanceOf(\BlackOps\Core\OperationResult::class, $result);
        self::assertSame('authorization.report_forbidden', $result->rejectionReason()->code());
        self::assertSame(self::OPERATION_ID, $result->operationId()?->toString());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationRejected],
            array_column($this->records(), 'event'),
        );
        self::assertSame([1, 2], array_column($this->records(), 'sequence'));
    }

    private function orchestrator(?AuthorizationPolicy $policy = null): DeferredAcceptanceOrchestrator
    {
        $clock = new FixedDeferredAcceptanceClock();
        $identifiers = new IdentifierFactory(new DeferredAcceptanceUuidv7Generator(), $clock);

        return new DeferredAcceptanceOrchestrator(
            $this->connection,
            $this->sender,
            $this->journal,
            new JournalRecordFactory($identifiers, $clock),
            authorization: $policy === null
                ? null
                : new AuthorizationEvaluator(new AuthorizationPolicyResolver(
                    new DeferredAcceptancePolicyContainer($policy),
                )),
        );
    }

    private function message(): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            1,
            '{"reportId":"report-1"}',
            '{"correlationId":"' . self::CORRELATION_ID . '"}',
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
        );
    }

    private function envelope(?ActorContext $actorContext = null): OperationEnvelope
    {
        return new OperationEnvelope(
            new DeferredAcceptedOperation(),
            new DeferredAcceptedValue('report-1'),
            new ExecutionContext(
                OperationId::fromString(self::OPERATION_ID),
                new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: $actorContext,
            ),
            new Deferred(),
        );
    }

    /** @param class-string<AuthorizationPolicy>|null $policy */
    private function metadata(?string $policy = null): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            DeferredAcceptedOperation::class,
            DeferredAcceptedValue::class,
            DeferredAcceptedHandler::class,
            EmptyOutcome::class,
            Deferred::class,
            authorizationPolicy: $policy,
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

final readonly class DeferredAcceptedOperation implements Operation {}

final readonly class DeferredAcceptedValue implements OperationValue
{
    public function __construct(
        public string $reportId,
    ) {}
}

abstract class DeferredAcceptedHandler implements OperationHandler {}

final readonly class DeferredAcceptancePolicy implements AuthorizationPolicy
{
    public function __construct(
        private AuthorizationDecision $decision,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return $this->decision;
    }
}

final readonly class DeferredAcceptancePolicyContainer implements ContainerInterface
{
    public function __construct(
        private AuthorizationPolicy $policy,
    ) {}

    public function get(string $id): mixed
    {
        return $this->policy;
    }

    public function has(string $id): bool
    {
        return true;
    }
}

final readonly class FixedDeferredAcceptanceClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:00:02.000000Z');
    }
}

final class DeferredAcceptanceUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687699',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
