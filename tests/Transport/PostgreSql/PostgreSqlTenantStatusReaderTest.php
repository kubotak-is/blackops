<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use BlackOps\Core\TenantRef;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusFailureKind;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReader;
use BlackOps\Transport\PostgreSql\PostgreSqlStatusReadFailed;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/** @mago-expect lint:cyclomatic-complexity */
/** @mago-expect lint:kan-defect */
/** @mago-expect lint:too-many-methods */
final class PostgreSqlTenantStatusReaderTest extends TestCase
{
    private const string SCHEMA = 'blackops_p23_d2s1_reader';
    private const string TYPE = 'status.tenant.reader';

    private RecordingTenantStatusConnection $connection;

    private PostgreSqlStatusReader $reader;

    protected function setUp(): void
    {
        $base = $this->connection();
        $this->connection = new RecordingTenantStatusConnection($base->getParams(), $base->getDriver());
        $base->close();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        )->migrate();
        new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        )->migrate();
        $this->reader = new PostgreSqlStatusReader($this->connection, self::SCHEMA);
        $this->connection->calls = [];
    }

    protected function tearDown(): void
    {
        try {
            while ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        } finally {
            $this->connection->close();
        }
    }

    public function testAllFiveScopedSelectorFamiliesBindOperationAndNullableTenantPredicates(): void
    {
        $id = $this->id(1);
        $tenantA = new TenantRef('customer', 'tenant-a');
        $tenantB = new TenantRef('customer', 'tenant-b');
        $this->enqueue($id, $tenantA);
        $this->appendReceived($id, $tenantA);
        $this->insertOutcome($id, $tenantA, 'own-outcome');
        $this->insertDeadLetter($id, $tenantA);
        $this->insertPurge($id, RetentionPurgeTarget::Journal, $tenantA, '2026-07-19T00:00:02Z', 1);
        $this->insertPurge($id, RetentionPurgeTarget::Outcome, $tenantA, '2026-07-19T00:00:01Z', 2);
        $this->insertPurge($id, RetentionPurgeTarget::DeadLetter, $tenantB, '2026-07-19T00:00:00Z', 3);

        $this->connection->calls = [];
        self::assertNotNull($this->reader->findSubjectForTenant($id, $tenantA));
        $subjectCalls = $this->connection->calls;
        self::assertNotEmpty($subjectCalls);
        foreach ($subjectCalls as $call) {
            $this->assertScopedCall($call, $id, $tenantA);
        }

        $this->connection->calls = [];
        self::assertNotNull($this->reader->deferredStateForTenant($id, $tenantA));
        $this->assertExactlyOneScopedCall($this->connection->calls, $id, $tenantA);

        $this->connection->calls = [];
        self::assertTrue($this->reader->outcomeExistsForTenant($id, $tenantA));
        $this->assertExactlyOneScopedCall($this->connection->calls, $id, $tenantA);

        $this->connection->calls = [];
        self::assertTrue($this->reader->deadLetterExistsForTenant($id, $tenantA));
        $this->assertExactlyOneScopedCall($this->connection->calls, $id, $tenantA);

        $this->connection->calls = [];
        self::assertSame(
            [RetentionPurgeTarget::Outcome, RetentionPurgeTarget::Journal],
            $this->reader->purgeTargetsForTenant($id, $tenantA),
        );
        $this->assertExactlyOneScopedCall($this->connection->calls, $id, $tenantA);

        $tieId = $this->id(13);
        $this->enqueue($tieId, $tenantA);
        $tieTime = '2026-07-19T00:00:09Z';
        $this->insertPurge($tieId, RetentionPurgeTarget::Journal, $tenantA, $tieTime, 5);
        $this->insertPurge($tieId, RetentionPurgeTarget::Outcome, $tenantA, $tieTime, 4);
        $this->connection->calls = [];
        self::assertSame(
            [RetentionPurgeTarget::Outcome, RetentionPurgeTarget::Journal],
            $this->reader->purgeTargetsForTenant($tieId, $tenantA),
        );
        $this->assertExactlyOneScopedCall($this->connection->calls, $tieId, $tenantA);

        $this->connection->calls = [];
        self::assertNull($this->reader->findSubjectForTenant($id, $tenantB));
        foreach ($this->connection->calls as $call) {
            $this->assertScopedCall($call, $id, $tenantB);
        }
        self::assertFalse($this->reader->outcomeExistsForTenant($id, $tenantB));
        self::assertFalse($this->reader->deadLetterExistsForTenant($id, $tenantB));
        self::assertSame([RetentionPurgeTarget::DeadLetter], $this->reader->purgeTargetsForTenant($id, $tenantB));
    }

    public function testExplicitGlobalScopeIsDistinctFromLegacyUnscopedAndNamedScopes(): void
    {
        $namedId = $this->id(2);
        $globalId = $this->id(3);
        $tenant = new TenantRef('customer', 'tenant-a');
        $this->enqueue($namedId, $tenant);
        $this->appendReceived($namedId, $tenant);
        $this->enqueue($globalId, null);

        $this->connection->calls = [];
        self::assertNull($this->reader->findSubjectForTenant($namedId, null));
        foreach ($this->connection->calls as $call) {
            $this->assertScopedCall($call, $namedId, null);
        }

        $this->connection->calls = [];
        self::assertNotNull($this->reader->findSubjectForTenant($globalId, null));
        foreach ($this->connection->calls as $call) {
            $this->assertScopedCall($call, $globalId, null);
        }

        $this->connection->calls = [];
        self::assertNotNull($this->reader->findSubject($namedId));
        self::assertNotEmpty($this->connection->calls);
        foreach ($this->connection->calls as $call) {
            self::assertStringNotContainsString(
                'IS NOT DISTINCT FROM :tenant_type',
                $call['sql'],
                'legacy SQL must not be used as the scoped-global sentinel',
            );
        }

        $this->connection->calls = [];
        self::assertNull($this->reader->findSubjectForTenant($namedId, new TenantRef('other', 'tenant-a')));
        foreach ($this->connection->calls as $call) {
            $this->assertScopedCall($call, $namedId, new TenantRef('other', 'tenant-a'));
        }
    }

    public function testEveryScopedSelectorUsesGlobalNullAndNamedTenantIdentityAcrossAbsentAndCrossRows(): void
    {
        $globalId = $this->id(9);
        $namedId = $this->id(10);
        $crossId = $this->id(11);
        $otherTypeId = $this->id(12);
        $tenantA = new TenantRef('customer', 'tenant-a');
        $tenantB = new TenantRef('customer', 'tenant-b');
        $sameIdOtherType = new TenantRef('partner', 'tenant-a');
        $absentId = $this->id(99);

        $this->enqueue($globalId, null);
        $this->insertOutcome($globalId, null, 'global-outcome');
        $this->insertDeadLetter($globalId, null);
        $this->insertPurge($globalId, RetentionPurgeTarget::Outcome, null, '2026-07-19T00:00:00Z', 9);

        $this->enqueue($namedId, $tenantA);
        $this->insertOutcome($namedId, $tenantA, 'named-outcome');
        $this->insertDeadLetter($namedId, $tenantA);
        $this->insertPurge($namedId, RetentionPurgeTarget::Journal, $tenantA, '2026-07-19T00:00:00Z', 10);

        $this->enqueue($crossId, $tenantB);
        $this->enqueue($otherTypeId, $sameIdOtherType);

        $selectors = [
            'subject' => fn(OperationId $id, ?TenantRef $tenant): mixed => $this->reader->findSubjectForTenant(
                $id,
                $tenant,
            ),
            'deferred-state' => fn(OperationId $id, ?TenantRef $tenant): mixed => $this->reader->deferredStateForTenant(
                $id,
                $tenant,
            ),
            'outcome-exists' => fn(OperationId $id, ?TenantRef $tenant): mixed => $this->reader->outcomeExistsForTenant(
                $id,
                $tenant,
            ),
            'dead-letter-exists' => fn(
                OperationId $id,
                ?TenantRef $tenant,
            ): mixed => $this->reader->deadLetterExistsForTenant($id, $tenant),
            'purge-targets' => fn(OperationId $id, ?TenantRef $tenant): mixed => $this->reader->purgeTargetsForTenant(
                $id,
                $tenant,
            ),
        ];
        $cases = [
            [$globalId, null, true, true, true, [RetentionPurgeTarget::Outcome]],
            [$globalId, $tenantA, false, false, false, []],
            [$namedId, null, false, false, false, []],
            [$namedId, $tenantA, true, true, true, [RetentionPurgeTarget::Journal]],
            [$crossId, $tenantA, false, false, false, []],
            [$crossId, new TenantRef('partner', 'tenant-b'), false, false, false, []],
            [$otherTypeId, $tenantA, false, false, false, []],
            [$absentId, null, false, false, false, []],
        ];

        foreach ($selectors as $name => $selector) {
            foreach ($cases as [$operationId, $tenant, $hasSubject, $hasState, $hasOutcome, $purges]) {
                $this->connection->calls = [];
                $actual = $selector($operationId, $tenant);
                self::assertNotEmpty($this->connection->calls, $name);
                foreach ($this->connection->calls as $call) {
                    $this->assertScopedCall($call, $operationId, $tenant);
                }
                if ($name === 'subject') {
                    $hasSubject ? self::assertNotNull($actual) : self::assertNull($actual);
                    continue;
                }
                if ($name === 'deferred-state') {
                    $hasState ? self::assertNotNull($actual) : self::assertNull($actual);
                    continue;
                }
                if ($name === 'outcome-exists') {
                    self::assertSame($hasOutcome, $actual);
                    continue;
                }
                if ($name === 'dead-letter-exists') {
                    self::assertSame($hasOutcome, $actual);
                    continue;
                }
                self::assertSame($purges, $actual);
            }
        }
    }

    public function testOperationIdAndTenantTypeAndIdAreAllIndependentSelectors(): void
    {
        $idA = $this->id(4);
        $idB = $this->id(5);
        $tenantA = new TenantRef('customer', 'tenant-a');
        $tenantB = new TenantRef('customer', 'tenant-b');
        $this->enqueue($idA, $tenantA);
        $this->enqueue($idB, $tenantB);
        $this->insertOutcome($idA, $tenantB, 'foreign-id');
        $this->insertDeadLetter($idA, $tenantB);
        $this->insertPurge($idA, RetentionPurgeTarget::Journal, $tenantB, '2026-07-19T00:00:00Z', 6);

        self::assertNull($this->reader->findSubjectForTenant($idB, $tenantA));
        self::assertFalse($this->reader->outcomeExistsForTenant($idA, $tenantA));
        self::assertFalse($this->reader->deadLetterExistsForTenant($idA, $tenantA));
        self::assertSame([], $this->reader->purgeTargetsForTenant($idA, $tenantA));
        self::assertNull($this->reader->findSubjectForTenant($idA, new TenantRef('other', 'tenant-a')));
        self::assertFalse($this->reader->outcomeExistsForTenant($idA, new TenantRef('other', 'tenant-b')));
    }

    public function testPartialTenantPairsStayOutOfEveryScopeAndMalformedTargetsFailIntegrity(): void
    {
        $id = $this->id(6);
        $this->enqueue($id, new TenantRef('customer', 'tenant-a'));
        $this->connection->executeStatement(
            'ALTER TABLE ' . self::SCHEMA . '.operations DROP CONSTRAINT operations_tenant_pair_check',
        );
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
             SET tenant_type = :tenant_type, tenant_id = NULL
             WHERE operation_id = :operation_id',
            ['tenant_type' => 'customer', 'operation_id' => $id->toString()],
        );
        self::assertNull($this->reader->findSubjectForTenant($id, null));
        self::assertNull($this->reader->findSubjectForTenant($id, new TenantRef('customer', 'tenant-a')));

        $idWithOnlyTenantId = $this->id(7);
        $this->enqueue($idWithOnlyTenantId, new TenantRef('customer', 'tenant-a'));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
             SET tenant_type = NULL, tenant_id = :tenant_id
             WHERE operation_id = :operation_id',
            ['tenant_id' => 'tenant-a', 'operation_id' => $idWithOnlyTenantId->toString()],
        );
        self::assertNull($this->reader->findSubjectForTenant($idWithOnlyTenantId, null));
        self::assertNull($this->reader->findSubjectForTenant(
            $idWithOnlyTenantId,
            new TenantRef('customer', 'tenant-a'),
        ));

        $invalidTargetId = $this->id(8);
        $tenant = new TenantRef('customer', 'tenant-a');
        $this->enqueue($invalidTargetId, $tenant);
        $this->connection->executeStatement(
            'ALTER TABLE '
            . self::SCHEMA
            . '.retention_purge_audits DROP CONSTRAINT retention_purge_audits_target_check',
        );
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.retention_purge_audits (
                audit_id, operation_id, tenant_type, tenant_id, target, affected_count, policy, purged_at, purged_by
             ) VALUES (:audit_id, :operation_id, :tenant_type, :tenant_id, :target, 1, :policy, :purged_at, :purged_by)',
            [
                'audit_id' => '019f70ab-4000-7000-8000-000000000008',
                'operation_id' => $invalidTargetId->toString(),
                'tenant_type' => $tenant->type(),
                'tenant_id' => $tenant->id(),
                'target' => 'invalid-target',
                'policy' => 'reader-test',
                'purged_at' => '2026-07-19T00:00:00Z',
                'purged_by' => 'reader-test',
            ],
        );
        try {
            $this->reader->purgeTargetsForTenant($invalidTargetId, $tenant);
            self::fail('A malformed purge target was accepted.');
        } catch (PostgreSqlStatusReadFailed $exception) {
            self::assertSame(PostgreSqlStatusFailureKind::Integrity, $exception->kind);
            self::assertSame('PostgreSQL status read failed.', $exception->getMessage());
        }
    }

    /** @param array{method:string,sql:string,params:array<string,mixed>} $call */
    private function assertScopedCall(array $call, OperationId $id, ?TenantRef $tenant): void
    {
        self::assertStringContainsString('operation_id = :operation_id', $call['sql']);
        self::assertStringContainsString('tenant_type IS NOT DISTINCT FROM :tenant_type', $call['sql']);
        self::assertStringContainsString('tenant_id IS NOT DISTINCT FROM :tenant_id', $call['sql']);
        self::assertSame($id->toString(), $call['params']['operation_id'] ?? null);
        self::assertArrayHasKey('tenant_type', $call['params']);
        self::assertArrayHasKey('tenant_id', $call['params']);
        self::assertSame($tenant?->type(), $call['params']['tenant_type']);
        self::assertSame($tenant?->id(), $call['params']['tenant_id']);
    }

    /** @param list<array{method:string,sql:string,params:array<string,mixed>}> $calls */
    private function assertExactlyOneScopedCall(array $calls, OperationId $id, ?TenantRef $tenant): void
    {
        self::assertCount(1, $calls);
        $this->assertScopedCall($calls[0], $id, $tenant);
    }

    private function enqueue(OperationId $id, ?TenantRef $tenant): void
    {
        new PostgreSqlDeferredOperationSender(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        )->enqueue(
            new DeferredOperationMessage(
                $id,
                self::TYPE,
                1,
                '{"payload":"opaque"}',
                '{"context":"opaque"}',
                new DateTimeImmutable('2026-07-19T00:00:00Z'),
                tenant: $tenant,
                originActor: new ActorRef('origin-a', 'customer'),
            ),
        );
    }

    private function appendReceived(OperationId $id, TenantRef $tenant): void
    {
        new PostgreSqlCanonicalJournalStore(
            $this->connection,
            PostgreSqlTestStorageProtection::codec(),
            self::SCHEMA,
        )->append(
            new JournalRecord(
                JournalRecordId::fromString('019f70ab-2000-7000-8000-' . substr($id->toString(), -12)),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-07-19T00:00:01Z'),
                1,
                new JournalOperation(
                    $id,
                    self::TYPE,
                    1,
                    'deferred',
                    CorrelationId::fromString('019f70ab-3000-7000-8000-' . substr($id->toString(), -12)),
                    actorContext: new \BlackOps\Core\ActorContext(
                        new ActorRef('origin-a', 'customer'),
                        null,
                        new ActorRef('execution-a', 'worker'),
                    ),
                    tenant: $tenant,
                ),
                null,
                new OperationReceivedData(new PostgreSqlTenantStatusReaderValue('opaque')),
            ),
        );
    }

    private function insertOutcome(OperationId $id, ?TenantRef $tenant, string $plaintext): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.outcomes (
                operation_id, outcome_type, schema_version, tenant_type, tenant_id, encoded_payload, completed_at
             ) VALUES (:operation_id, :outcome_type, 1, :tenant_type, :tenant_id, :encoded_payload, :completed_at)',
            [
                'operation_id' => $id->toString(),
                'outcome_type' => self::TYPE,
                'tenant_type' => $tenant?->type(),
                'tenant_id' => $tenant?->id(),
                'encoded_payload' => PostgreSqlTestStorageProtection::outcomeEnvelope(
                    $plaintext,
                    $id->toString(),
                    self::TYPE,
                    tenant: $tenant,
                ),
                'completed_at' => '2026-07-19T00:00:03Z',
            ],
        );
    }

    private function insertDeadLetter(OperationId $id, ?TenantRef $tenant): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.dead_letters (
                operation_id, tenant_type, tenant_id, final_attempt_number, encoded_reason, moved_at
             ) VALUES (:operation_id, :tenant_type, :tenant_id, 1, decode(\'424f5044\', \'hex\'), :moved_at)',
            [
                'operation_id' => $id->toString(),
                'tenant_type' => $tenant?->type(),
                'tenant_id' => $tenant?->id(),
                'moved_at' => '2026-07-19T00:00:04Z',
            ],
        );
    }

    private function insertPurge(
        OperationId $id,
        RetentionPurgeTarget $target,
        ?TenantRef $tenant,
        string $purgedAt,
        int $number,
    ): void {
        $this->connection->executeStatement(
            'INSERT INTO '
            . self::SCHEMA
            . '.retention_purge_audits (
                audit_id, operation_id, tenant_type, tenant_id, target, affected_count, policy, purged_at, purged_by
             ) VALUES (:audit_id, :operation_id, :tenant_type, :tenant_id, :target, 1, :policy, :purged_at, :purged_by)',
            [
                'audit_id' => sprintf('019f70ab-4000-7000-8000-%012d', $number),
                'operation_id' => $id->toString(),
                'tenant_type' => $tenant?->type(),
                'tenant_id' => $tenant?->id(),
                'target' => $target->value,
                'policy' => 'reader-test',
                'purged_at' => $purgedAt,
                'purged_by' => 'reader-test',
            ],
        );
    }

    private function id(int $number): OperationId
    {
        return OperationId::fromString(sprintf('019f70ab-1000-7000-8000-%012d', $number));
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

final class RecordingTenantStatusConnection extends Connection
{
    /** @var list<array{method:string,sql:string,params:array<string,mixed>}> */
    public array $calls = [];

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->calls[] = ['method' => 'fetchAssociative', 'sql' => $query, 'params' => $params];

        return parent::fetchAssociative($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $this->calls[] = ['method' => 'fetchOne', 'sql' => $query, 'params' => $params];

        return parent::fetchOne($query, $params, $types);
    }

    /** @return list<array<string,mixed>> */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->calls[] = ['method' => 'fetchAllAssociative', 'sql' => $query, 'params' => $params];

        return parent::fetchAllAssociative($query, $params, $types);
    }
}

final readonly class PostgreSqlTenantStatusReaderValue implements \BlackOps\Core\OperationValue
{
    public function __construct(
        public string $value,
    ) {}
}
