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
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
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
        $codec = new PostgreSqlJournalRecordCodec();
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
                . '"."journal" (record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record) VALUES (:record,:operation,:sequence,:event,1,:at,convert_to(:encoded,\'UTF8\'))',
                [
                    'record' => $id,
                    'operation' => $operation->toString(),
                    'sequence' => $index + 1,
                    'event' => 'operation.received',
                    'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'encoded' => $codec->encode($record),
                ],
            );
        }
        $seen = new RecordingObserver();
        $targets = new ObserverReplayTargetRegistry([
            new JournalObserverBinding('recording', $seen),
            new JournalObserverBinding('failing', new FailingSequenceObserver(2)),
        ]);
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($this->connection, self::SCHEMA),
            $targets,
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            2,
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

    public function testFlushFailureRedeliversSameRecordIdAndIdempotentTargetConverges(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $codec = new PostgreSqlJournalRecordCodec();
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
                . '"."journal" (record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record) VALUES (:record,:operation,:sequence,:event,1,:at,convert_to(:encoded,\'UTF8\'))',
                [
                    'record' => $id,
                    'operation' => $operation->toString(),
                    'sequence' => $index + 1,
                    'event' => 'operation.received',
                    'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'encoded' => $codec->encode($record),
                ],
            );
        }
        $target = new AcceptThenFlushFailObserver();
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($this->connection, self::SCHEMA),
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
            . '"."journal" (record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record) VALUES (:record,:operation,1,:event,1,:at,convert_to(:encoded,\'UTF8\'))',
            [
                'record' => $record->recordId->toString(),
                'operation' => $operation->toString(),
                'event' => 'operation.received',
                'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                'encoded' => new PostgreSqlJournalRecordCodec()->encode($record),
            ],
        );
        $runtime = new ObserverReplayRuntime(
            new PostgreSqlObserverReplayStore($this->connection, self::SCHEMA),
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

    public function observe(ObservedJournalRecord $record): void
    {
        $this->sequences[] = $record->sequence;
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
