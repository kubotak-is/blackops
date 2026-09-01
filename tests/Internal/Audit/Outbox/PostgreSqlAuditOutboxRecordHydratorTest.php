<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use PHPUnit\Framework\TestCase;

final class PostgreSqlAuditOutboxRecordHydratorTest extends TestCase
{
    public function testHydratesTheExactSixteenFieldPhysicalOrderAndCanonicalIntegers(): void
    {
        $hydrator = new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator());
        $row = $this->row();
        $row['schema_version'] = '1';
        $row['attempt'] = '0';
        $row['version'] = '1';

        $record = $hydrator->hydrate($row, new AuditOutboxConfiguration());

        self::assertSame(
            [
                'schemaVersion' => 1,
                'kind' => 'audit_outbox_record',
                'recordId' => $row['record_id'],
                'partitionId' => $row['partition_id'],
                'tenantBinding' => null,
                'ingressOrdinal' => 1,
                'idempotencyKeyHash' => $row['idempotency_key_hash'],
                'contentHash' => $row['content_hash'],
                'state' => 'pending',
                'attempt' => 0,
                'availableAt' => $row['available_at'],
                'leaseFencingToken' => null,
                'leaseExpiresAt' => null,
                'protectedBody' => $row['protected_body'],
                'version' => 1,
            ],
            $record->toArray(),
        );
    }

    public function testRejectsListMissingExtraReorderedAndWideRowsBeforeHydration(): void
    {
        $hydrator = $this->hydrator();
        $valid = $this->row();
        $invalid = [
            array_values($valid),
            array_slice($valid, 0, 15, true),
            [...$valid, 'extra' => 'wide'],
            $this->reorder($valid, [
                'kind',
                'schema_version',
                'record_id',
                'partition_id',
                'tenant_type',
                'tenant_id',
                'ingress_ordinal',
                'idempotency_key_hash',
                'content_hash',
                'state',
                'attempt',
                'available_at',
                'lease_fencing_token',
                'lease_expires_at',
                'protected_body',
                'version',
            ]),
        ];
        foreach ($invalid as $row) {
            $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row, new AuditOutboxConfiguration()));
        }
    }

    public function testStateMatrixAndConfiguredAttemptBoundAreForwarded(): void
    {
        $hydrator = $this->hydrator();
        $configuration = new AuditOutboxConfiguration(60, 2, 1, 10);
        $pending = $this->row();
        $pending['attempt'] = 1;
        $pending['version'] = 2;
        self::assertSame(1, $hydrator->hydrate($pending, $configuration)->stateSnapshot()->attempt());

        $leased = $this->row();
        $leased['state'] = 'leased';
        $leased['attempt'] = 2;
        $leased['available_at'] = null;
        $leased['lease_fencing_token'] = '018f0a00-0000-7000-8000-000000000002';
        $leased['lease_expires_at'] = '2026-09-01T03:05:00.123456Z';
        $leased['version'] = 3;
        self::assertSame('leased', $hydrator->hydrate($leased, $configuration)->stateSnapshot()->state()->value);

        $terminal = $leased;
        $terminal['state'] = 'poisoned';
        $terminal['lease_fencing_token'] = null;
        $terminal['lease_expires_at'] = null;
        $terminal['version'] = 4;
        self::assertSame('poisoned', $hydrator->hydrate($terminal, $configuration)->stateSnapshot()->state()->value);

        $tooMany = $pending;
        $tooMany['attempt'] = 2;
        $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($tooMany, $configuration));
    }

    public function testIntegerScalarsRequireNativeOrCanonicalUnsignedDecimal(): void
    {
        $hydrator = $this->hydrator();
        foreach (['schema_version', 'attempt', 'version'] as $field) {
            foreach (['+1', ' 1', '01', 1.0, '-1', '9007199254740992'] as $value) {
                $row = $this->row();
                $row[$field] = $value;
                $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row, new AuditOutboxConfiguration()));
            }
        }
    }

    public function testTenantPairAndTimestampStateNullabilityAreStrict(): void
    {
        $hydrator = $this->hydrator();
        $tenant = $this->row();
        $tenant['partition_id'] = 'tenant:' . str_repeat('c', 64);
        $tenant['tenant_type'] = 'account';
        $tenant['tenant_id'] = 'tenant-a';
        self::assertSame(
            ['type' => 'account', 'id' => 'tenant-a'],
            $hydrator->hydrate($tenant, new AuditOutboxConfiguration())->toArray()['tenantBinding'],
        );

        foreach ([
            ['tenant_type' => 'account', 'tenant_id' => null],
            ['tenant_type' => null, 'tenant_id' => 'tenant-a'],
            ['available_at' => null],
            ['available_at' => '2026-02-30T03:00:00.123456Z'],
            ['state' => 'leased', 'lease_fencing_token' => null],
            ['state' => 'relayed', 'attempt' => 0],
        ] as $changes) {
            $row = $this->row();
            foreach ($changes as $field => $value) {
                $row[$field] = $value;
            }
            $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row, new AuditOutboxConfiguration()));
        }
    }

    public function testFullHydratorDoesNotRetainConfiguration(): void
    {
        $reflection = new \ReflectionClass(PostgreSqlAuditOutboxRecordHydrator::class);
        self::assertSame(
            ['protectedIngress'],
            array_map(
                static fn(\ReflectionProperty $property): string => $property->getName(),
                $reflection->getProperties(),
            ),
        );

        $hydrator = $this->hydrator();
        $row = $this->row();
        $row['attempt'] = 2;
        $row['version'] = 3;
        $this->assertIntegrityFailure(static fn() => $hydrator->hydrate(
            $row,
            new AuditOutboxConfiguration(60, 2, 1, 10),
        ));
        self::assertSame(
            2,
            $hydrator
                ->hydrate($row, new AuditOutboxConfiguration(60, 3, 1, 10))
                ->stateSnapshot()
                ->attempt(),
        );
    }

    public function testCanariesNeverAppearInHydratorFailureSurfaces(): void
    {
        $hydrator = $this->hydrator();
        $canary = 'record-hydrator-canary-018f0a00-0000-7000-8000-000000000003';
        $row = $this->row();
        $row['protected_body'] = 'NOPE' . $canary . str_repeat('x', 20);

        try {
            $hydrator->hydrate($row, new AuditOutboxConfiguration());
            self::fail('malformed outbox row was accepted');
        } catch (AuditOutboxStoreException $exception) {
            foreach ([
                $exception->getMessage(),
                (string) $exception->getPrevious(),
                $exception->getTraceAsString(),
                var_export($exception->getTrace(), true),
                print_r($exception, true),
                var_export($exception, true),
                json_encode($exception, JSON_THROW_ON_ERROR),
                serialize($exception),
            ] as $surface) {
                self::assertStringNotContainsString($canary, $surface);
            }
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    private function hydrator(): PostgreSqlAuditOutboxRecordHydrator
    {
        return new PostgreSqlAuditOutboxRecordHydrator(new PostgreSqlAuditProtectedIngressHydrator());
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        return [
            'schema_version' => 1,
            'kind' => 'audit_outbox_record',
            'record_id' => '018f0a00-0000-7000-8000-000000000001',
            'partition_id' => 'system:global',
            'tenant_type' => null,
            'tenant_id' => null,
            'ingress_ordinal' => 1,
            'idempotency_key_hash' => str_repeat('a', 64),
            'content_hash' => str_repeat('b', 64),
            'state' => 'pending',
            'attempt' => 0,
            'available_at' => '2026-09-01T03:00:00.123456Z',
            'lease_fencing_token' => null,
            'lease_expires_at' => null,
            'protected_body' => 'BOPD' . str_repeat('x', 49),
            'version' => 1,
        ];
    }

    /** @param array<string,mixed> $row @param list<string> $keys @return array<string,mixed> */
    private function reorder(array $row, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $row[$key];
        }

        return $result;
    }

    private function assertIntegrityFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('malformed outbox row was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }
}
