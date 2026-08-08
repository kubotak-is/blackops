<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
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
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Core\ScheduleContext;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ExecutionContextJsonCodec;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Scheduling\PostgreSqlScheduledOccurrenceLifecycle;
use BlackOps\Internal\Scheduling\PostgreSqlScheduleStore;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Telemetry\TelemetryContext;
use BlackOps\Tests\Internal\Telemetry\RecordingTracerProvider;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

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
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.123456Z'),
        );
        $this->journal = new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        );
        $this->sender->migrate();
        $this->journal->migrate();
        new PostgreSqlScheduleStore($this->connection, self::SCHEMA)->migrate();
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

    public function testAcceptsProxySubclassEnvelopeUsingOriginalMetadata(): void
    {
        $acknowledgement = $this->orchestrator()->accept(
            $this->message(),
            $this->envelope(definition: new ProxiedDeferredAcceptedOperation()),
            $this->metadata(),
        );

        self::assertSame(self::OPERATION_ID, $acknowledgement->operationId()->toString());
        self::assertSame('accepted', $this->operationRow()['state']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted],
            array_column($this->records(), 'event'),
        );
        self::assertSame(
            ['report.generate'],
            array_values(array_unique(array_map(
                static fn(JournalRecord $record): string => $record->operation->type,
                $this->records(),
            ))),
        );
    }

    public function testHttpAcceptorStoresProxySubclassMessageAndJournalUsingOriginalMetadata(): void
    {
        $clock = new FixedDeferredAcceptanceClock();
        $identifiers = new IdentifierFactory(new DeferredAcceptanceUuidv7Generator(), $clock);
        $provider = new RecordingTracerProvider();
        $tenant = new \BlackOps\Core\TenantRef('account', 'raw-http-tenant');
        $acceptor = new DeferredHttpOperationAcceptor(
            new OperationRegistry([$this->metadata()]),
            new ExecutionContextFactory($identifiers, $clock),
            new ReflectionJsonOperationCodec(),
            $this->orchestrator(),
            telemetryTracer: new TelemetryTracer($provider),
        );

        $actor = new ActorRef('raw-http-actor', 'user');
        $acknowledgement = $acceptor->accept(
            new ProxiedDeferredAcceptedOperation(),
            new DeferredAcceptedValue('report-1'),
            actorContext: new ActorContext($actor, $actor, $actor),
            tenant: $tenant,
            telemetry: new TelemetryContext('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', 'vendor=value'),
        );

        $operationRow = $this->operationRow();
        $records = $this->records($acknowledgement->operationId());

        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687699', $acknowledgement->operationId()->toString());
        self::assertSame('report.generate', $operationRow['operation_type']);
        self::assertSame('{"reportId":"report-1"}', PostgreSqlTestStorageProtection::codec()->decrypt(
            hex2bin((string) $operationRow['encoded_payload']),
            new StorageProtectionContext(
                StoragePurpose::DeferredPayload,
                $acknowledgement->operationId()->toString() . ':payload',
                $acknowledgement->operationId()->toString(),
                'report.generate',
                1,
                $tenant,
            ),
        ));
        self::assertSame('accepted', $operationRow['state']);
        self::assertStringNotContainsString('traceparent', (string) $operationRow['encoded_context']);
        $encodedContext = PostgreSqlTestStorageProtection::codec()->decrypt(
            hex2bin((string) $operationRow['encoded_context']),
            new StorageProtectionContext(
                StoragePurpose::DeferredContext,
                $acknowledgement->operationId()->toString() . ':context',
                $acknowledgement->operationId()->toString(),
                'report.generate',
                1,
                $tenant,
            ),
        );
        self::assertCount(1, $provider->spans);
        $span = $provider->spans[0];
        $decodedTelemetry = new ExecutionContextJsonCodec()
            ->decode($encodedContext)
            ->telemetry();
        self::assertNotNull($decodedTelemetry);
        self::assertSame($span->getContext()->getTraceId(), explode('-', $decodedTelemetry->traceparent())[1]);
        self::assertSame($span->getContext()->getSpanId(), explode('-', $decodedTelemetry->traceparent())[2]);
        self::assertSame(
            $span->getContext()->getTraceFlags(),
            hexdec(explode('-', $decodedTelemetry->traceparent())[3]),
        );
        self::assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $span->parent?->getTraceId());
        self::assertSame('00f067aa0ba902b7', $span->parent?->getSpanId());
        self::assertSame(
            'vendor=value',
            new ExecutionContextJsonCodec()
                ->decode($encodedContext)
                ->telemetry()
                ?->tracestate(),
        );
        self::assertSame('blackops.operation.accept', $span->name);
        self::assertSame(TelemetryTracer::KIND_PRODUCER, $span->kind);
        self::assertSame('deferred', $span->attributes['blackops.operation.strategy']);
        self::assertSame('[masked]', $span->attributes['blackops.actor.origin.id']);
        self::assertSame('[masked]', $span->attributes['blackops.actor.authorization.id']);
        self::assertSame('[masked]', $span->attributes['blackops.actor.execution.id']);
        self::assertSame('[masked]', $span->attributes['blackops.tenant.id']);
        self::assertSame('completed', $span->attributes['blackops.result']);
        self::assertTrue($span->ended);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted],
            array_column($records, 'event'),
        );
        self::assertSame(
            ['report.generate'],
            array_values(array_unique(array_map(
                static fn(JournalRecord $record): string => $record->operation->type,
                $records,
            ))),
        );
    }

    public function testHttpAcceptorRecordsRejectedProducerWhenOrchestratorRejects(): void
    {
        $clock = new FixedDeferredAcceptanceClock();
        $identifiers = new IdentifierFactory(new DeferredAcceptanceUuidv7Generator(), $clock);
        $provider = new RecordingTracerProvider();
        $actor = new ActorRef('raw-http-actor', 'user');
        $acceptor = new DeferredHttpOperationAcceptor(
            new OperationRegistry([$this->metadata(DeferredAcceptancePolicy::class)]),
            new ExecutionContextFactory($identifiers, $clock),
            new ReflectionJsonOperationCodec(),
            $this->orchestrator(new DeferredAcceptancePolicy(AuthorizationDecision::forbid(
                'authorization.report_forbidden',
            ))),
            telemetryTracer: new TelemetryTracer($provider),
        );

        $result = $acceptor->accept(
            new DeferredAcceptedOperation(),
            new DeferredAcceptedValue('report-1'),
            actorContext: new ActorContext($actor, $actor, $actor),
            telemetry: new TelemetryContext('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'),
        );

        self::assertInstanceOf(\BlackOps\Core\OperationResult::class, $result);
        self::assertSame('authorization.report_forbidden', $result->rejectionReason()->code());
        self::assertCount(1, $provider->spans);
        $span = $provider->spans[0];
        self::assertSame('blackops.operation.accept', $span->name);
        self::assertSame(TelemetryTracer::KIND_PRODUCER, $span->kind);
        self::assertSame('rejected', $span->attributes['blackops.result']);
        self::assertTrue($span->ended);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
    }

    public function testDuplicateOperationRollsBackWithoutAdditionalJournalRecords(): void
    {
        $this->orchestrator()->accept($this->message(), $this->envelope(), $this->metadata());

        try {
            $this->orchestrator()->accept($this->message(), $this->envelope(), $this->metadata());
            self::fail('Expected duplicate operation to fail.');
        } catch (OperationExecutionFailed $failure) {
            self::assertInstanceOf(\BlackOps\Journal\Exception\JournalWriteFailed::class, $failure->primaryFailure());
            self::assertNotNull($failure->recordingFailure());
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

    public function testScheduledAcceptanceUpdatesClaimedOccurrenceWithAcknowledgementInstant(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.schedule_states (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (\'reports.daily\', \'report.generate\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00+00\')',
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.schedule_occurrences (schedule_name, scheduled_at, evaluated_at, state, operation_id, created_at, updated_at) VALUES (\'reports.daily\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00.123456+00\', \'claimed\', :operation, \'2026-07-10 00:00:00.123456+00\', \'2026-07-10 00:00:00.123456+00\')',
            ['operation' => self::OPERATION_ID],
        );
        $acknowledgement = $this->orchestrator(scheduled: true)->accept(
            $this->message(),
            $this->envelope(schedule: true),
            $this->metadata(schedule: true),
        );

        self::assertSame(self::OPERATION_ID, $acknowledgement->operationId()->toString());
        self::assertSame('accepted', $this->connection->fetchOne('SELECT state FROM '
        . self::SCHEMA
        . '.schedule_occurrences WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
        self::assertSame('2026-07-10 00:00:01.123456+00', $this->connection->fetchOne('SELECT accepted_at::text FROM '
        . self::SCHEMA
        . '.schedule_occurrences WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
    }

    public function testAuthorizationFailureRollsBackAcceptanceAndRecordsAttemptlessTerminalFailure(): void
    {
        $primaryFailure = new RuntimeException('authorization backend credential detail');
        $policy = new DeferredAcceptancePolicy(AuthorizationDecision::allow(), $primaryFailure);
        $actor = new ActorRef('user-123', 'user');

        try {
            $this->orchestrator($policy)->accept(
                $this->message(),
                $this->envelope(new ActorContext($actor, $actor, $actor)),
                $this->metadata(DeferredAcceptancePolicy::class),
            );
            self::fail('Expected deferred acceptance failure.');
        } catch (OperationExecutionFailed $failure) {
            self::assertSame($primaryFailure, $failure->primaryFailure());
            self::assertSame(self::OPERATION_ID, $failure->operationId()->toString());
            self::assertNull($failure->recordingFailure());
        }

        $records = $this->records();
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationFailed],
            array_column($records, 'event'),
        );
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertNull($records[0]->attempt);
        self::assertNull($records[1]->attempt);
        self::assertInstanceOf(OperationFailedData::class, $records[1]->data);
        self::assertFalse($records[1]->data->retryable);
    }

    private function orchestrator(
        ?AuthorizationPolicy $policy = null,
        bool $scheduled = false,
    ): DeferredAcceptanceOrchestrator {
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
            scheduledOccurrences: $scheduled
                ? new PostgreSqlScheduledOccurrenceLifecycle($this->connection, self::SCHEMA)
                : null,
        );
    }

    public function testScheduledAuthorizationRejectionTerminalizesOccurrenceSafely(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.schedule_states (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (\'reports.daily\', \'report.generate\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00+00\')',
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.schedule_occurrences (schedule_name, scheduled_at, evaluated_at, state, operation_id, created_at, updated_at) VALUES (\'reports.daily\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00.123456+00\', \'claimed\', :operation, \'2026-07-10 00:00:00.123456+00\', \'2026-07-10 00:00:00.123456+00\')',
            ['operation' => self::OPERATION_ID],
        );
        $policy = new DeferredAcceptancePolicy(AuthorizationDecision::forbid('authorization.report_forbidden'));
        $actor = new ActorRef('user-123', 'user');
        $result = $this->orchestrator($policy, scheduled: true)->accept(
            $this->message(),
            $this->envelope(new ActorContext($actor, $actor, $actor), schedule: true),
            $this->metadata(DeferredAcceptancePolicy::class, schedule: true),
        );

        self::assertSame('authorization.report_forbidden', $result->rejectionReason()->code());
        self::assertSame('rejected', $this->connection->fetchOne('SELECT state FROM '
        . self::SCHEMA
        . '.schedule_occurrences WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
    }

    public function testScheduledAcceptanceRollbackLeavesNoAcceptedOperationAndCompensatesFailure(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.schedule_states (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (\'reports.daily\', \'report.generate\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00+00\')',
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.schedule_occurrences (schedule_name, scheduled_at, evaluated_at, state, operation_id, created_at, updated_at) VALUES (\'reports.daily\', \'2026-07-10 00:00:00+00\', \'2026-07-10 00:00:00.123456+00\', \'claimed\', :operation, \'2026-07-10 00:00:00.123456+00\', \'2026-07-10 00:00:00.123456+00\')',
            ['operation' => self::OPERATION_ID],
        );
        $this->connection->executeStatement(
            'CREATE FUNCTION '
            . self::SCHEMA
            . '.reject_schedule_acceptance() RETURNS trigger LANGUAGE plpgsql AS \'BEGIN IF NEW.state = \'\'accepted\'\' THEN RAISE EXCEPTION \'\'scheduled occurrence acceptance unavailable\'\'; END IF; RETURN NEW; END;\'',
        );
        $this->connection->executeStatement(
            'CREATE TRIGGER reject_schedule_acceptance BEFORE UPDATE ON '
            . self::SCHEMA
            . '.schedule_occurrences FOR EACH ROW EXECUTE FUNCTION '
            . self::SCHEMA
            . '.reject_schedule_acceptance()',
        );

        try {
            $this->orchestrator(scheduled: true)->accept(
                $this->message(),
                $this->envelope(schedule: true),
                $this->metadata(schedule: true),
            );
            self::fail('Expected scheduled acceptance failure.');
        } catch (OperationExecutionFailed) {
            self::assertSame(
                0,
                (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'),
            );
            self::assertSame(
                [JournalEvent::OperationReceived, JournalEvent::OperationFailed],
                array_column($this->records(), 'event'),
            );
            self::assertSame('failed', $this->connection->fetchOne('SELECT state FROM '
            . self::SCHEMA
            . '.schedule_occurrences WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
        }
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

    private function envelope(
        ?ActorContext $actorContext = null,
        ?Operation $definition = null,
        bool $schedule = false,
    ): OperationEnvelope {
        return new OperationEnvelope(
            $definition ?? new DeferredAcceptedOperation(),
            new DeferredAcceptedValue('report-1'),
            new ExecutionContext(
                OperationId::fromString(self::OPERATION_ID),
                new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: $actorContext,
                schedule: $schedule
                    ? new ScheduleContext('reports.daily', new DateTimeImmutable('2026-07-10T00:00:00Z'), 'UTC')
                    : null,
            ),
            new Deferred(),
        );
    }

    /** @param class-string<AuthorizationPolicy>|null $policy */
    private function metadata(?string $policy = null, bool $schedule = false): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            DeferredAcceptedOperation::class,
            DeferredAcceptedValue::class,
            DeferredAcceptedHandler::class,
            EmptyOutcome::class,
            Deferred::class,
            schedule: $schedule ? new OperationScheduleMetadata('reports.daily', '* * * * *', 'UTC') : null,
            authorizationPolicy: $policy,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT operation_id, operation_type, state, state_version, next_sequence, encode(encoded_payload, \'hex\') AS encoded_payload, encode(encoded_context, \'hex\') AS encoded_context, accepted_at, available_at FROM '
            . self::SCHEMA
            . '.operations',
        );

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<JournalRecord>
     */
    private function records(?OperationId $operationId = null): array
    {
        return array_values(iterator_to_array($this->journal->records(
            $operationId ?? OperationId::fromString(self::OPERATION_ID),
        )));
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

readonly class DeferredAcceptedOperation implements Operation {}

final readonly class ProxiedDeferredAcceptedOperation extends DeferredAcceptedOperation {}

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
        private ?Throwable $failure = null,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

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
        '019f32ab-2be0-7b38-a0a7-1ab2f968769b',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769c',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
