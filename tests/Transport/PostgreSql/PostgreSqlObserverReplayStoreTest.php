<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgreSqlObserverReplayStoreTest extends TestCase
{
    private const SCHEMA = 'blackops_p19_006_replay';

    private Connection $connection;
    private PostgreSqlObserverReplayStore $store;

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
        $schema = new PostgreSqlJournalSchema(self::SCHEMA);
        foreach ($schema->statements() as $statement)
            $this->connection->executeStatement($statement);
        $this->store = new PostgreSqlObserverReplayStore($this->connection, self::SCHEMA);
    }

    public function testSelectorsAreBoundedAndNormalised(): void
    {
        $selector = PostgreSqlObserverReplaySelector::time(
            new DateTimeImmutable('2026-07-01T00:00:00+09:00'),
            new DateTimeImmutable('2026-07-02T00:00:00+09:00'),
        );
        self::assertSame('time', $selector->kind);
        self::assertSame('UTC', $selector->from?->getTimezone()->getName());
        $this->expectException(InvalidArgumentException::class);
        PostgreSqlObserverReplaySelector::time(
            new DateTimeImmutable('2026-07-02T00:00:00Z'),
            new DateTimeImmutable('2026-07-01T00:00:00Z'),
        );
    }

    public function testCheckpointIdentifierUsesSegmentGrammar(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        foreach (['-leading', 'trailing-', 'double..dot', 'UPPER'] as $checkpoint) {
            try {
                $this->store->begin(
                    new PostgreSqlObserverReplayBeginRequest($checkpoint, $selector, ['observer'], 'operator', 'test'),
                );
                self::fail('Invalid checkpoint was accepted: ' . $checkpoint);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        $valid = str_repeat('a', 128);
        $binding = $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest($valid, $selector, ['observer'], 'operator', 'test'),
        );
        $this->store->unlock($valid);
        self::assertNotSame('', $binding->auditId);
        $this->expectException(InvalidArgumentException::class);
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(str_repeat('a', 129), $selector, ['observer'], 'operator', 'test'),
        );
    }

    public function testEmptyOperationAndRecordSelectionsAreFinite(): void
    {
        $operation = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        self::assertSame([], $this->store->select($operation, 1, null)['records']);
        $record = PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
        ));
        self::assertFalse($this->store->select($record, 1, null)['hasMore']);
    }

    public function testCorruptCanonicalRowFailsSafelyWithoutMutation(): void
    {
        $before = $this->connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."journal"');
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."journal" (record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record) VALUES (:record,:operation,1,:event,1,:at,convert_to(:encoded,\'UTF8\'))',
            [
                'record' => '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
                'operation' => '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
                'event' => 'operation.received',
                'at' => '2026-07-01T00:00:00Z',
                'encoded' => '{}',
            ],
        );
        $this->expectException(RuntimeException::class);
        try {
            $this->store->select(
                PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
                    '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
                )),
                1,
                null,
            );
        } finally {
            self::assertSame(
                (int) $before + 1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."journal"'),
            );
        }
    }

    public function testCanonicalIdsAndEncodedBytesRemainUnchangedAcrossSelection(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $codec = new PostgreSqlJournalRecordCodec();
        $record = new JournalRecord(
            JournalRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f968769a'),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-01T00:00:00Z'),
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
        $encoded = $codec->encode($record);
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."journal" (record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record) VALUES (:record,:operation,1,:event,1,:at,convert_to(:encoded,\'UTF8\'))',
            [
                'record' => $record->recordId->toString(),
                'operation' => $operation->toString(),
                'event' => 'operation.received',
                'at' => '2026-07-01T00:00:00Z',
                'encoded' => $encoded,
            ],
        );
        $before = $this->connection->fetchAllAssociative(
            'SELECT record_id, encode(encoded_record, \'hex\') AS encoded FROM "'
            . self::SCHEMA
            . '"."journal" ORDER BY record_id',
        );
        $this->store->select(PostgreSqlObserverReplaySelector::operation($operation), 10, null);
        self::assertSame(
            [],
            $this->store->select(
                PostgreSqlObserverReplaySelector::record($record->recordId),
                10,
                $record->recordId->toString(),
            )['records'],
        );
        $after = $this->connection->fetchAllAssociative(
            'SELECT record_id, encode(encoded_record, \'hex\') AS encoded FROM "'
            . self::SCHEMA
            . '"."journal" ORDER BY record_id',
        );
        self::assertSame($before, $after);
    }

    public function testSameCheckpointIsNonBlockingAndRecoverableAfterOwnerRelease(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'replay-checkpoint',
                $selector,
                ['application-jsonl'],
                'operator',
                'test',
            ),
        );
        $second = new PostgreSqlObserverReplayStore(DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]), self::SCHEMA);
        $this->expectException(InvalidArgumentException::class);
        try {
            $second->begin(
                new PostgreSqlObserverReplayBeginRequest(
                    'replay-checkpoint',
                    $selector,
                    ['application-jsonl'],
                    'operator',
                    'test',
                ),
            );
        } finally {
            $this->store->unlock('replay-checkpoint');
        }
    }

    public function testCheckpointPersistsSelectorAndTargetsForResume(): void
    {
        $selector = PostgreSqlObserverReplaySelector::time(
            new DateTimeImmutable('2026-07-01T00:00:00Z'),
            new DateTimeImmutable('2026-07-02T00:00:00Z'),
        );
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'resume-checkpoint',
                $selector,
                ['application-jsonl', 'audit'],
                'operator',
                'resume',
            ),
        );
        $loaded = $this->store->load('resume-checkpoint');
        self::assertSame('time', $loaded->selector->kind);
        self::assertSame(['application-jsonl', 'audit'], $loaded->targets);
        $this->store->unlock('resume-checkpoint');
    }

    public function testOperationSelectionUsesSequenceKeysetAndHasMore(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $codec = new PostgreSqlJournalRecordCodec();
        $rows = [];
        foreach ([
            ['019f32ab-2be0-7b38-a0a7-1ab2f968769a', 1],
            ['019f32ab-2be0-7b38-a0a7-1ab2f968769b', 2],
        ] as [$id, $sequence]) {
            $record = new JournalRecord(
                JournalRecordId::fromString($id),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-07-01T00:00:00Z'),
                $sequence,
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
                    'sequence' => $sequence,
                    'event' => 'operation.received',
                    'at' => '2026-07-01T00:00:00Z',
                    'encoded' => $codec->encode($record),
                ],
            );
            $rows[] = $record;
        }
        $selector = PostgreSqlObserverReplaySelector::operation($operation);
        $first = $this->store->select($selector, 1, null);
        self::assertTrue($first['hasMore']);
        self::assertSame($rows[0]->recordId->toString(), $first['records'][0]->recordId->toString());
        $second = $this->store->select($selector, 1, $this->store->cursorFor($selector, $first['records'][0]));
        self::assertFalse($second['hasMore']);
        self::assertSame($rows[1]->recordId->toString(), $second['records'][0]->recordId->toString());
    }

    public function testTimeSelectionUsesHalfOpenBoundaryAndOccurredOrder(): void
    {
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $codec = new PostgreSqlJournalRecordCodec();
        foreach ([
            ['019f32ab-2be0-7b38-a0a7-1ab2f968769a', '2026-07-01T00:00:00Z'],
            ['019f32ab-2be0-7b38-a0a7-1ab2f968769b', '2026-07-02T00:00:00Z'],
        ] as $index => [$id, $at]) {
            $record = new JournalRecord(
                JournalRecordId::fromString($id),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable($at),
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
                    'at' => $at,
                    'encoded' => $codec->encode($record),
                ],
            );
        }
        $result = $this->store->select(
            PostgreSqlObserverReplaySelector::time(
                new DateTimeImmutable('2026-07-01T00:00:00Z'),
                new DateTimeImmutable('2026-07-02T00:00:00Z'),
            ),
            10,
            null,
        );
        self::assertCount(1, $result['records']);
        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f968769a', $result['records'][0]->recordId->toString());
    }

    public function testCheckpointSelectorMismatchIsRejected(): void
    {
        $first = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        $second = PostgreSqlObserverReplaySelector::record(JournalRecordId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
        ));
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'mismatch-checkpoint',
                $first,
                ['application-jsonl'],
                'operator',
                'test',
            ),
        );
        $this->store->unlock('mismatch-checkpoint');
        $this->expectException(InvalidArgumentException::class);
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'mismatch-checkpoint',
                $second,
                ['application-jsonl'],
                'operator',
                'test',
            ),
        );
    }

    public function testTargetHashIsCollisionSafe(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest('target-hash-checkpoint', $selector, ['a,b'], 'operator', 'test'),
        );
        $this->store->unlock('target-hash-checkpoint');
        $this->expectException(InvalidArgumentException::class);
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'target-hash-checkpoint',
                $selector,
                ['a', 'b'],
                'operator',
                'test',
            ),
        );
    }

    public function testRunningCheckpointRecoversAfterOwningSessionCloses(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'stale-checkpoint',
                $selector,
                ['application-jsonl'],
                'operator',
                'test',
            ),
        );
        $this->connection->close();
        $replacementConnection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $replacement = new PostgreSqlObserverReplayStore($replacementConnection, self::SCHEMA);
        $replacement->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'stale-checkpoint',
                $selector,
                ['application-jsonl'],
                'operator',
                'resume',
            ),
        );
        self::assertTrue(true);
        $replacement->unlock('stale-checkpoint');
    }

    public function testAuditCompletesWithSafeCountsAndFingerprint(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        $binding = $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest(
                'audit-checkpoint',
                $selector,
                ['application-jsonl'],
                'operator',
                'audit',
            ),
        );
        $this->store->advance('audit-checkpoint', '1|019f32ab-2be0-7b38-a0a7-1ab2f968769a', 1, $binding->auditId);
        $this->store->finishInvocation('audit-checkpoint', 'paused', null, $binding->auditId);
        $audit = $this->connection->fetchAssociative(
            'SELECT state, delivered_count, first_record_id, last_record_id, actor, reason, failure_fingerprint, selector_operation_id FROM "'
            . self::SCHEMA
            . '"."observer_replay_audits" WHERE checkpoint_id = :id',
            ['id' => 'audit-checkpoint'],
        );
        self::assertSame('complete', $audit['state']);
        self::assertSame(1, (int) $audit['delivered_count']);
        self::assertSame('operator', $audit['actor']);
        self::assertSame($selector->operationId?->toString(), $audit['selector_operation_id']);
        self::assertNull($audit['failure_fingerprint']);
        $this->store->unlock('audit-checkpoint');
    }

    public function testAuditCountsArePerInvocationWhileCheckpointCountsAccumulate(): void
    {
        $selector = PostgreSqlObserverReplaySelector::operation(OperationId::fromString(
            '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));
        $first = $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest('audit-resume', $selector, ['observer'], 'operator', 'first'),
        );
        $this->store->advance('audit-resume', '1|019f32ab-2be0-7b38-a0a7-1ab2f968769a', 1, $first->auditId);
        $this->store->finishInvocation('audit-resume', 'paused', null, $first->auditId);
        $this->store->unlock('audit-resume');
        $second = $this->store->begin(
            new PostgreSqlObserverReplayBeginRequest('audit-resume', $selector, ['observer'], 'operator', 'second'),
        );
        $this->store->advance('audit-resume', '2|019f32ab-2be0-7b38-a0a7-1ab2f968769b', 1, $second->auditId);
        $this->store->finishInvocation('audit-resume', 'complete', null, $second->auditId);
        $this->store->unlock('audit-resume');
        $counts = $this->connection->fetchFirstColumn(
            'SELECT delivered_count FROM "'
            . self::SCHEMA
            . '"."observer_replay_audits" WHERE checkpoint_id = \'audit-resume\' ORDER BY started_at',
        );
        self::assertSame([1, 1], array_map('intval', $counts));
        self::assertSame(
            2,
            (int) $this->connection->fetchOne(
                'SELECT delivered_count FROM "'
                . self::SCHEMA
                . '"."observer_replay_checkpoints" WHERE checkpoint_id = \'audit-resume\'',
            ),
        );
    }
}
