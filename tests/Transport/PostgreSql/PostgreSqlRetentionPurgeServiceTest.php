<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Idempotency\IdempotencyClaimStatus;
use BlackOps\Internal\Idempotency\IdempotencyResultSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHash;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\OperationFingerprint;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\PostgreSqlIdempotencyStore;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlDeadLetterRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlIdempotencyRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeRetentionDeleteService;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeAuditStore;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPurgeService;
use BlackOps\Transport\PostgreSql\PostgreSqlTransportPayloadTombstoneService;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/** @mago-expect lint:too-many-methods */
final class PostgreSqlRetentionPurgeServiceTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_010';
    private const PAYLOAD_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688e01';
    private const DEAD_LETTER_OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9688e02';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlRetentionPurgeService $service;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->sender->migrate();
        $idempotency = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA);
        $idempotency->migrate();
        foreach (new PostgreSqlJournalSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }

        $audit = new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA);
        $ids = new FixedPurgeServiceAuditIdGenerator([
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e11',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e12',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e13',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e14',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e15',
        ]);
        $clock = new FixedPurgeServiceClock('2026-07-12T00:00:00.000000Z');

        $this->service = new PostgreSqlRetentionPurgeService(
            new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA),
            new PostgreSqlTransportPayloadTombstoneService($this->connection, $audit, self::SCHEMA, $clock, $ids),
            new PostgreSqlOutcomeRetentionDeleteService($this->connection, $audit, self::SCHEMA, $clock, $ids),
            new PostgreSqlDeadLetterRetentionDeleteService($this->connection, $audit, self::SCHEMA, $clock, $ids),
            new PostgreSqlJournalRetentionDeleteService($this->connection, $audit, self::SCHEMA, $clock, $ids),
            new PostgreSqlIdempotencyRetentionDeleteService($this->connection, $audit, self::SCHEMA, $clock, $ids),
        );
    }

    public function testPurgePlansAndExecutesSupportedTargets(): void
    {
        $this->seedRows();

        $result = $this->service->purge(
            new RetentionPolicy(
                RetentionPeriod::days(1),
                RetentionPeriod::days(30),
                RetentionPeriod::days(14),
                RetentionPeriod::days(2),
                RetentionPeriod::days(1),
            ),
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );

        self::assertSame(5, $result->plan()->count());
        self::assertSame(2, $result->transportPayloadsPurged());
        self::assertSame(1, $result->outcomesDeleted());
        self::assertSame(1, $result->deadLettersDeleted());
        self::assertSame(2, $result->journalsDeleted());
        self::assertSame(0, $result->idempotencyRecordsDeleted());
        self::assertSame(6, $result->totalAffected());
        self::assertNull($this->operationPayload(self::PAYLOAD_OPERATION));
        self::assertNull($this->operationPayload(self::DEAD_LETTER_OPERATION));
        self::assertFalse($this->deadLetterExists(self::DEAD_LETTER_OPERATION));
        self::assertFalse($this->outcomeExists(self::PAYLOAD_OPERATION));
        self::assertSame(5, $this->auditCount());
    }

    public function testIdempotencyRecordPurgeHonorsRetentionAndAudit(): void
    {
        $store = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA);
        $key = new IdempotencyKey('retention-key');
        $now = new DateTimeImmutable('2026-07-12T00:00:00Z');
        $scope = new IdempotencyScopeHasher()->hash('retention.operation', new ActorRef('u-1', 'user'), $key);
        $value = new class implements \BlackOps\Core\OperationValue {
            public string $value = 'safe';
        };
        $fingerprint = new OperationValueFingerprinter()->fingerprint('retention.operation', $value);
        $operation = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e21');
        $created = $now->modify('-3 days');
        $claim = $store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
        );
        $record = $claim->record();
        self::assertInstanceOf(\BlackOps\Internal\Idempotency\ProcessingRecord::class, $record);
        $store->terminalize(
            $operation,
            new TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $operation,
                new Inline(),
                $created,
                $created->modify('+1 day'),
                null,
                new IdempotencyResultSnapshot(OperationResult::rejected(
                    RejectionReason::conflict('fixture'),
                    $operation,
                )),
            ),
        );

        $result = $this->service->purge(
            new RetentionPolicy(
                RetentionPeriod::days(1),
                RetentionPeriod::days(30),
                RetentionPeriod::days(14),
                RetentionPeriod::days(2),
                RetentionPeriod::days(1),
            ),
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
            $now,
        );

        self::assertSame(1, $result->idempotencyRecordsDeleted());
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT count(*) FROM '
                . self::SCHEMA
                . '.retention_purge_audits WHERE target = \'idempotency_record\'',
            ),
        );
    }

    public function testExpiredIdempotencyRecordRemainsNonReclaimableWhileRetained(): void
    {
        [$store, $scope, $key, $fingerprint, $original] = $this->idempotencyRecord(
            'retained-key',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e31',
        );
        $now = new DateTimeImmutable('2026-07-12T00:00:00Z');
        $replacement = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e32');

        $claim = $store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $replacement,
            new Inline(),
            $now,
            $now->modify('+1 day'),
        );

        self::assertSame(IdempotencyClaimStatus::ExistingSameFingerprint, $claim->status());
        self::assertSame($original->toString(), $claim->record()->operationId()->toString());
        $plan = new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA)->plan($this->retentionPolicy(), $now);
        self::assertCount(1, $plan->forTarget(RetentionTarget::IdempotencyRecord));
        self::assertSame(
            $original->toString(),
            $plan->forTarget(RetentionTarget::IdempotencyRecord)[0]->operationId()->toString(),
        );
    }

    public function testActiveLegalHoldExcludesIdempotencyRecordFromPlanAndPurge(): void
    {
        [$store, $scope, $key, $fingerprint, $operation] = $this->idempotencyRecord(
            'held-key',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e33',
        );
        $this->hold($operation->toString(), '019f32ab-2be0-7b38-a0a7-1ab2f9688e34');
        $now = new DateTimeImmutable('2026-07-12T00:00:00Z');
        $plan = new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA)->plan($this->retentionPolicy(), $now);
        self::assertCount(0, $plan->forTarget(RetentionTarget::IdempotencyRecord));

        $result = $this->service->purge(
            $this->retentionPolicy(),
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
            $now,
        );
        self::assertSame(0, $result->idempotencyRecordsDeleted());
        self::assertNotNull($store->find($scope));
        self::assertStringContainsString($key->hash()->digest(), $this->storedIdempotencyRow($operation)['key_hash']);
    }

    public function testHoldPlacedAfterDryRunBlocksPurgeThenReleaseAllowsNewClaimAndSafeAudit(): void
    {
        [$store, $scope, $key, $fingerprint, $operation] = $this->idempotencyRecord(
            'lifecycle-key',
            '019f32ab-2be0-7b38-a0a7-1ab2f9688e35',
        );
        $now = new DateTimeImmutable('2026-07-12T00:00:00Z');
        $policy = $this->retentionPolicy();
        $plan = new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA)->plan($policy, $now);
        self::assertCount(1, $plan->forTarget(RetentionTarget::IdempotencyRecord));

        $holdId = '019f32ab-2be0-7b38-a0a7-1ab2f9688e36';
        $this->hold($operation->toString(), $holdId);
        $delete = $this->idempotencyDeleteService('019f32ab-2be0-7b38-a0a7-1ab2f9688e37');
        self::assertSame(0, $delete->delete(
            $plan,
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
        ));
        self::assertNotNull($store->find($scope));

        $this->connection->executeStatement('UPDATE ' . self::SCHEMA . '.retention_holds
             SET released_at = :released_at, released_by = :released_by
             WHERE hold_id = :hold_id', [
            'released_at' => '2026-07-12 00:00:00+00',
            'released_by' => 'legal-team',
            'hold_id' => $holdId,
        ]);
        self::assertSame(1, $delete->delete(
            $plan,
            RetentionPolicyRef::fromString('production-retention-v1'),
            RetentionActorRef::fromString('system:retention'),
        ));
        self::assertNull($store->find($scope));

        $audit = $this->connection->fetchAssociative('SELECT operation_id::text AS operation_id, target, affected_count, policy, purged_by FROM '
        . self::SCHEMA
        . '.retention_purge_audits WHERE target = :target', ['target' => 'idempotency_record']);
        self::assertIsArray($audit);
        self::assertSame($operation->toString(), $audit['operation_id']);
        self::assertSame('idempotency_record', $audit['target']);
        self::assertSame('1', (string) $audit['affected_count']);
        self::assertSame('production-retention-v1', $audit['policy']);
        self::assertSame('system:retention', $audit['purged_by']);
        self::assertStringNotContainsString('lifecycle-key', implode('|', array_map('strval', $audit)));
        self::assertStringNotContainsString($scope->digest(), implode('|', array_map('strval', $audit)));
        self::assertStringNotContainsString($fingerprint->digest(), implode('|', array_map('strval', $audit)));

        $replacement = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9688e38');
        $claim = $store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $replacement,
            new Inline(),
            $now,
            $now->modify('+1 day'),
        );
        self::assertSame(IdempotencyClaimStatus::Claimed, $claim->status());
        self::assertSame($replacement->toString(), $claim->record()->operationId()->toString());
    }

    private function retentionPolicy(): RetentionPolicy
    {
        return new RetentionPolicy(
            RetentionPeriod::days(30),
            RetentionPeriod::days(30),
            RetentionPeriod::days(30),
            RetentionPeriod::days(30),
            RetentionPeriod::days(1),
        );
    }

    /** @return array{PostgreSqlIdempotencyStore, IdempotencyScopeHash, IdempotencyKey, OperationFingerprint, OperationId} */
    private function idempotencyRecord(string $keyValue, string $operationValue): array
    {
        $store = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA);
        $key = new IdempotencyKey($keyValue);
        $scope = new IdempotencyScopeHasher()->hash('retention.operation', new ActorRef('u-1', 'user'), $key);
        $value = new class implements \BlackOps\Core\OperationValue {
            public string $value = 'safe';
        };
        $fingerprint = new OperationValueFingerprinter()->fingerprint('retention.operation', $value);
        $operation = OperationId::fromString($operationValue);
        $created = new DateTimeImmutable('2026-07-09T00:00:00Z');
        $claim = $store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
        );
        $record = $claim->record();
        self::assertInstanceOf(ProcessingRecord::class, $record);
        self::assertTrue($store->terminalize(
            $operation,
            new \BlackOps\Internal\Idempotency\TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $operation,
                new Inline(),
                $created,
                $created->modify('+1 day'),
                null,
                new IdempotencyResultSnapshot(OperationResult::rejected(
                    RejectionReason::conflict('retention.fixture'),
                    $operation,
                )),
            ),
        ));

        return [$store, $scope, $key, $fingerprint, $operation];
    }

    private function hold(string $operationId, string $holdId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.retention_holds (
                hold_id, operation_id, category, reason, placed_at, placed_by
            ) VALUES (:hold_id, :operation_id, :category, :reason, :placed_at, :placed_by)',
            [
                'hold_id' => $holdId,
                'operation_id' => $operationId,
                'category' => 'legal',
                'reason' => 'retention test',
                'placed_at' => '2026-07-11 00:00:00+00',
                'placed_by' => 'legal-team',
            ],
        );
    }

    private function idempotencyDeleteService(string $auditId): PostgreSqlIdempotencyRetentionDeleteService
    {
        return new PostgreSqlIdempotencyRetentionDeleteService(
            $this->connection,
            new PostgreSqlRetentionPurgeAuditStore($this->connection, self::SCHEMA),
            self::SCHEMA,
            new FixedPurgeServiceClock('2026-07-12T00:00:00.000000Z'),
            new FixedPurgeServiceAuditIdGenerator([$auditId]),
        );
    }

    /** @return array<string, mixed> */
    private function storedIdempotencyRow(OperationId $operation): array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '
        . self::SCHEMA
        . '.idempotency_records WHERE operation_id = :operation_id', ['operation_id' => $operation->toString()]);
        self::assertIsArray($row);

        return $row;
    }

    private function seedRows(): void
    {
        $this->operation(self::PAYLOAD_OPERATION, 'completed', '2026-07-09 00:00:00+00:00');
        $this->operation(self::DEAD_LETTER_OPERATION, 'dead_lettered', '2026-07-08 00:00:00+00:00');
        $this->deadLetter(self::DEAD_LETTER_OPERATION, '2026-07-08 00:00:00+00:00');
        $this->outcome(self::PAYLOAD_OPERATION, '2026-06-20 00:00:00+00:00');
        $this->journal(self::PAYLOAD_OPERATION, 1, '2026-05-01 00:00:00+00:00');
        $this->journal(self::PAYLOAD_OPERATION, 2, '2026-05-02 00:00:00+00:00');
    }

    private function journal(string $operationId, int $sequence, string $occurredAt): void
    {
        $recordId = $this->recordId($operationId, $sequence);
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.journal (
            record_id, operation_id, sequence, event, schema_version, occurred_at, encoded_record
        ) VALUES (
            :record_id, :operation_id, :sequence, :event, 1, :occurred_at, convert_to(:record, \'UTF8\')
        )', [
            'record_id' => $recordId,
            'operation_id' => $operationId,
            'sequence' => $sequence,
            'event' => 'operation.tested',
            'occurred_at' => $occurredAt,
            'record' => '{}',
        ]);
    }

    private function recordId(string $operationId, int $sequence): string
    {
        $hex = md5($operationId . ':' . $sequence);

        return (
            substr($hex, 0, 8)
            . '-'
            . substr($hex, 8, 4)
            . '-'
            . substr($hex, 12, 4)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20, 12)
        );
    }

    private function operation(string $operationId, string $state, string $updatedAt): void
    {
        $this->sender->enqueue($this->message($operationId));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state,
                updated_at = :updated_at
            WHERE operation_id = :operation_id',
            [
                'operation_id' => $operationId,
                'state' => $state,
                'updated_at' => $updatedAt,
            ],
        );
    }

    private function deadLetter(string $operationId, string $movedAt): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.dead_letters (
                operation_id,
                final_attempt_id,
                final_attempt_number,
                reason_type,
                reason_message,
                moved_at
            ) VALUES (
                :operation_id,
                NULL,
                NULL,
                :reason_type,
                :reason_message,
                :moved_at
            )', [
            'operation_id' => $operationId,
            'reason_type' => \RuntimeException::class,
            'reason_message' => 'boom',
            'moved_at' => $movedAt,
        ]);
    }

    private function operationPayload(string $operationId): ?string
    {
        $payload = $this->connection->fetchOne(
            'SELECT convert_from(encoded_payload, \'UTF8\')
            FROM ' . self::SCHEMA . '.operations
            WHERE operation_id = :operation_id',
            ['operation_id' => $operationId],
        );

        return is_string($payload) ? $payload : null;
    }

    private function outcome(string $operationId, string $completedAt): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.outcomes (
            operation_id, outcome_type, schema_version, encoded_payload, completed_at
        ) VALUES (
            :operation_id, :outcome_type, 1, convert_to(:payload, \'UTF8\'), :completed_at
        )', [
            'operation_id' => $operationId,
            'outcome_type' => 'retention.test',
            'payload' => '{}',
            'completed_at' => $completedAt,
        ]);
    }

    private function outcomeExists(string $operationId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (
            SELECT 1 FROM ' . self::SCHEMA . '.outcomes WHERE operation_id = :operation_id
        )', [
            'operation_id' => $operationId,
        ]);
    }

    private function deadLetterExists(string $operationId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT EXISTS (
                SELECT 1 FROM ' . self::SCHEMA . '.dead_letters WHERE operation_id = :operation_id
            )', [
            'operation_id' => $operationId,
        ]);
    }

    private function auditCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.retention_purge_audits');
    }

    private function message(string $operationId): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($operationId),
            'report.generate',
            1,
            '{"operationId":"' . $operationId . '"}',
            '{"correlationId":"c1"}',
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
        );
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

final readonly class FixedPurgeServiceClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

final class FixedPurgeServiceAuditIdGenerator implements PostgreSqlRetentionPurgeAuditIdGenerator
{
    private int $index = 0;

    /**
     * @param list<string> $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function generate(DateTimeImmutable $time): RetentionPurgeAuditId
    {
        return RetentionPurgeAuditId::fromString($this->values[$this->index++]);
    }
}
