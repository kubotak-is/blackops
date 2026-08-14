<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Replay;

use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Replay\ObserverReplayRequest;
use BlackOps\Internal\Replay\ObserverReplayRuntime;
use BlackOps\Internal\Replay\ObserverReplayTargetRegistry;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Telemetry\TelemetryCorrelation;
use BlackOps\Tests\Internal\Telemetry\RecordingTracerProvider;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

final class ObserverReplayRuntimeFailureTest extends TestCase
{
    private const SCHEMA = 'blackops_p19_006_runtime';

    private $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        foreach (new PostgreSqlJournalSchema(self::SCHEMA)->statements() as $statement)
            $this->connection->executeStatement($statement);
    }

    public function testObserveFailureLeavesFailedRecordUnadvanced(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        foreach (['019f32ab-2be0-7b38-a0a7-1ab2f968769a', '019f32ab-2be0-7b38-a0a7-1ab2f968769b'] as $index => $id) {
            $record = new JournalRecord(
                JournalRecordId::fromString($id),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-07-01T00:00:0' . ($index + 1) . 'Z'),
                $index + 1,
                new JournalOperation(
                    $operation,
                    'order.create',
                    1,
                    'inline',
                    CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                ),
                null,
                new EmptyJournalData(),
            );
            $this->connection->executeStatement(
                'INSERT INTO "'
                . self::SCHEMA
                . '"."journal" (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (:record,:operation,\'order.create\',:sequence,:event,1,1,:at,:encoded)',
                [
                    'record' => $id,
                    'operation' => $operation->toString(),
                    'sequence' => $index + 1,
                    'event' => 'operation.received',
                    'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'encoded' =>
                        \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::journalRecordEnvelope(
                            $record,
                        ),
                ],
                ['encoded' => ParameterType::BINARY],
            );
        }
        $seen = new RecordingObserver();
        $provider = new RecordingTracerProvider();
        $targets = new ObserverReplayTargetRegistry([
            new JournalObserverBinding('recording', $seen),
            new JournalObserverBinding('failing', new FailingSequenceObserver(2)),
        ]);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore(
                $this->connection,
                \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
            $targets,
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            2,
            new TelemetryTracer($provider),
        );
        $this->expectExceptionMessage('Observer replay delivery failed.');
        $this->expectException(JournalObservationFailed::class);
        try {
            $runtime->replay(
                new ObserverReplayRequest(
                    PostgreSqlObserverReplaySelector::operation($operation),
                    ['recording', 'failing'],
                    'runtime-checkpoint',
                    'operator',
                    'test',
                ),
            );
        } finally {
            self::assertCount(1, $provider->spans);
            $span = $provider->spans[0];
            self::assertSame('blackops.observer.replay', $span->name);
            self::assertSame(TelemetryTracer::KIND_INTERNAL, $span->kind);
            self::assertSame('observer_replay', $span->attributes['blackops.runtime.kind']);
            self::assertSame('failed', $span->attributes['blackops.result']);
            self::assertTrue($span->ended);
            self::assertSame([1], $seen->sequences);
            $checkpoint = $this->connection->fetchAssociative(
                'SELECT state, cursor_record_id FROM "'
                . self::SCHEMA
                . '"."observer_replay_checkpoints" WHERE checkpoint_id=\'runtime-checkpoint\'',
            );
            self::assertSame('failed', $checkpoint['state']);
            self::assertSame('1|' . '019f32ab-2be0-7b38-a0a7-1ab2f968769a', $checkpoint['cursor_record_id']);
        }
    }

    public function testSuccessfulReplayPreservesOriginalCorrelationDuringReplaySpan(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $record = new JournalRecord(
            JournalRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f968769a'),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-01T00:00:01Z'),
            1,
            new JournalOperation(
                $operation,
                'order.create',
                1,
                'inline',
                CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                telemetry: new TelemetryCorrelation('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7', true),
            ),
            null,
            new EmptyJournalData(),
        );
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."journal" (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (:record,:operation,\'order.create\',1,\'operation.received\',1,1,:at,:encoded)',
            [
                'record' => $record->recordId->toString(),
                'operation' => $operation->toString(),
                'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                'encoded' =>
                    \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::journalRecordEnvelope(
                        $record,
                    ),
            ],
            ['encoded' => ParameterType::BINARY],
        );
        $seen = new RecordingObserver();
        $provider = new RecordingTracerProvider();
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore(
                $this->connection,
                \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
            new ObserverReplayTargetRegistry([new JournalObserverBinding('recording', $seen)]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            2,
            new TelemetryTracer($provider),
        );

        $result = $runtime->replay(
            new ObserverReplayRequest(
                PostgreSqlObserverReplaySelector::operation($operation),
                ['recording'],
                'runtime-success',
                'operator',
                'test',
            ),
        );

        self::assertSame(1, $result->delivered);
        self::assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $seen->correlations[0]?->traceId);
        self::assertSame('00f067aa0ba902b7', $seen->correlations[0]?->spanId);
        self::assertCount(1, $provider->spans);
        $span = $provider->spans[0];
        self::assertSame('blackops.observer.replay', $span->name);
        self::assertSame(TelemetryTracer::KIND_INTERNAL, $span->kind);
        self::assertSame('observer_replay', $span->attributes['blackops.runtime.kind']);
        self::assertSame('completed', $span->attributes['blackops.result']);
        self::assertTrue($span->ended);
        self::assertNotSame($seen->correlations[0]?->traceId, $span->getContext()->getTraceId());
        self::assertNotSame($seen->correlations[0]?->spanId, $span->getContext()->getSpanId());
    }

    public function testFlushFailureRedeliversSameRecordIdAndIdempotentTargetConverges(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        foreach (['019f32ab-2be0-7b38-a0a7-1ab2f968769a', '019f32ab-2be0-7b38-a0a7-1ab2f968769b'] as $index => $id) {
            $record = new JournalRecord(
                JournalRecordId::fromString($id),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-07-01T00:00:0' . ($index + 1) . 'Z'),
                $index + 1,
                new JournalOperation(
                    $operation,
                    'order.create',
                    1,
                    'inline',
                    CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                ),
                null,
                new EmptyJournalData(),
            );
            $this->connection->executeStatement(
                'INSERT INTO "'
                . self::SCHEMA
                . '"."journal" (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (:record,:operation,\'order.create\',:sequence,:event,1,1,:at,:encoded)',
                [
                    'record' => $id,
                    'operation' => $operation->toString(),
                    'sequence' => $index + 1,
                    'event' => 'operation.received',
                    'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'encoded' =>
                        \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::journalRecordEnvelope(
                            $record,
                        ),
                ],
                ['encoded' => ParameterType::BINARY],
            );
        }
        $target = new AcceptThenFlushFailObserver();
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore(
                $this->connection,
                \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
            new ObserverReplayTargetRegistry([new JournalObserverBinding('target', $target)]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            2,
        );
        $selector = PostgreSqlObserverReplaySelector::operation($operation);
        try {
            $runtime->replay(new ObserverReplayRequest($selector, ['target'], 'flush-checkpoint', 'operator', 'test'));
        } catch (JournalObservationFailed) {
        }
        self::assertSame(
            '1|019f32ab-2be0-7b38-a0a7-1ab2f968769a',
            $this->connection->fetchOne(
                'SELECT cursor_record_id FROM "'
                . self::SCHEMA
                . '"."observer_replay_checkpoints" WHERE checkpoint_id=\'flush-checkpoint\'',
            ),
        );
        $runtime->resume('flush-checkpoint', 'operator', 'resume');
        self::assertSame(
            ['019f32ab-2be0-7b38-a0a7-1ab2f968769a', '019f32ab-2be0-7b38-a0a7-1ab2f968769b'],
            array_keys($target->records),
        );
    }

    public function testPrimaryObserverFailureRemainsVisibleWhenFailureAuditCannotPersist(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $record = new JournalRecord(
            JournalRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f968769a'),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-01T00:00:01Z'),
            1,
            new JournalOperation(
                $operation,
                'order.create',
                1,
                'inline',
                CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
            ),
            null,
            new EmptyJournalData(),
        );
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."journal" (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (:record,:operation,\'order.create\',1,:event,1,1,:at,:encoded)',
            [
                'record' => $record->recordId->toString(),
                'operation' => $operation->toString(),
                'event' => 'operation.received',
                'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                'encoded' =>
                    \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::journalRecordEnvelope(
                        $record,
                    ),
            ],
            ['encoded' => ParameterType::BINARY],
        );
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore(
                $this->connection,
                \BlackOps\Tests\Transport\PostgreSql\PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
            new ObserverReplayTargetRegistry([
                new JournalObserverBinding('failing', new DropAuditAndFailObserver($this->connection, self::SCHEMA)),
            ]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
        );
        try {
            $runtime->replay(
                new ObserverReplayRequest(
                    PostgreSqlObserverReplaySelector::operation($operation),
                    ['failing'],
                    'primary-failure',
                    'operator',
                    'test',
                ),
            );
            self::fail('Primary observer failure was not raised.');
        } catch (JournalObservationFailed $exception) {
            self::assertSame('Observer replay delivery failed.', $exception->getMessage());
            self::assertStringNotContainsString('primary observer failure', $exception->getMessage());
        }
    }
}

final class RecordingObserver implements JournalObserver
{
    /** @var list<int> */ public array $sequences = [];
    /** @var list<?TelemetryCorrelation> */ public array $correlations = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->sequences[] = $record->sequence;
        $this->correlations[] = $record->operation->telemetry;
    }
}

final class FailingSequenceObserver implements JournalObserver
{
    public function __construct(
        private int $sequence,
    ) {}

    public function observe(ObservedJournalRecord $record): void
    {
        if ($record->sequence === $this->sequence)
            throw new JournalObservationFailed('observer failure');
    }
}

final class AcceptThenFlushFailObserver implements FlushableJournalObserver
{
    /** @var array<string, true> */ public array $records = [];
    private int $flushes = 0;

    public function observe(ObservedJournalRecord $record): void
    {
        $this->records[$record->recordId->toString()] = true;
    }

    public function flush(): void
    {
        if (++$this->flushes === 2)
            throw new JournalObservationFailed('flush failure');
    }
}

final class DropAuditAndFailObserver implements JournalObserver
{
    public function __construct(
        private $connection,
        private string $schema,
    ) {}

    public function observe(ObservedJournalRecord $record): void
    {
        $this->connection->executeStatement('DROP TABLE "' . $this->schema . '"."observer_replay_audits"');
        throw new JournalObservationFailed('primary observer failure');
    }
}
