<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Outbox;

use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator;
use PHPUnit\Framework\TestCase;

final class PostgreSqlAuditProtectedIngressHydratorTest extends TestCase
{
    public function testHydratesTheExactEightFieldOrderAndNormalizesCanonicalIntegers(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        $row = $this->row();
        $row['ingress_ordinal'] = '1';

        $ingress = $hydrator->hydrate($row);

        self::assertSame(
            [
                'recordId' => $row['record_id'],
                'partitionId' => $row['partition_id'],
                'tenantBinding' => null,
                'ingressOrdinal' => 1,
                'idempotencyKeyHash' => $row['idempotency_key_hash'],
                'contentHash' => $row['content_hash'],
                'protectedBody' => $row['protected_body'],
            ],
            $ingress->toArray(),
        );
    }

    public function testAcceptsTenantPairsAndU2028U2029InClearIdentity(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        $row = $this->row();
        $row['partition_id'] = 'tenant:' . str_repeat('a', 64);
        $row['tenant_type'] = "account\u{2028}";
        $row['tenant_id'] = "tenant\u{2029}";

        self::assertSame(
            [
                'type' => $row['tenant_type'],
                'id' => $row['tenant_id'],
            ],
            $hydrator->hydrate($row)->toArray()['tenantBinding'],
        );
    }

    public function testRejectsPartialTenantPairsAndMalformedShapesBeforeReadingMembers(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        $valid = $this->row();
        $invalid = [
            array_values($valid),
            array_slice($valid, 0, 7, true),
            [...$valid, 'extra' => 'value'],
            $this->reorder($valid, [
                'partition_id',
                'record_id',
                'tenant_type',
                'tenant_id',
                'ingress_ordinal',
                'idempotency_key_hash',
                'content_hash',
                'protected_body',
            ]),
        ];
        foreach ($invalid as $row) {
            $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row));
        }

        foreach ([
            ['tenant_type' => 'account', 'tenant_id' => null],
            ['tenant_type' => null, 'tenant_id' => 'tenant-a'],
        ] as $pair) {
            $row = $valid;
            $row['tenant_type'] = $pair['tenant_type'];
            $row['tenant_id'] = $pair['tenant_id'];
            $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row));
        }
    }

    public function testAcceptsNativeAndCanonicalDecimalOrdinalBoundaries(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        $row = $this->row();
        $row['ingress_ordinal'] = 9_007_199_254_740_991;
        self::assertSame(9_007_199_254_740_991, $hydrator->hydrate($row)->toArray()['ingressOrdinal']);
        $row['ingress_ordinal'] = '9007199254740991';
        self::assertSame(9_007_199_254_740_991, $hydrator->hydrate($row)->toArray()['ingressOrdinal']);
    }

    public function testRejectsNonCanonicalOrUnsafeOrdinalScalars(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        foreach ([
            '+1',
            ' 1',
            '01',
            1.0,
            '-1',
            '9007199254740992',
            0,
        ] as $ordinal) {
            $row = $this->row();
            $row['ingress_ordinal'] = $ordinal;
            $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row));
        }
    }

    public function testRejectsUuidHashAndBodyGrammarViolations(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        foreach ([
            ['record_id', '018f0a00-0000-8000-8000-000000000001'],
            ['idempotency_key_hash', str_repeat('A', 64)],
            ['content_hash', str_repeat('b', 63)],
            ['protected_body', 'NOPE' . str_repeat('x', 49)],
            ['protected_body', 'BOPD' . str_repeat('x', 1)],
            ['protected_body', 'BOPD' . str_repeat('x', 1_048_576)],
        ] as [$field, $value]) {
            $row = $this->row();
            $row[$field] = $value;
            $this->assertIntegrityFailure(static fn() => $hydrator->hydrate($row));
        }
    }

    public function testBodyMinMaxAndResourceWithNulRoundTrip(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        $row = $this->row();
        $row['protected_body'] = 'BOPD' . str_repeat("\0", 49);
        self::assertSame($row['protected_body'], $hydrator->hydrate($row)->protectedBody());

        $row['protected_body'] = 'BOPD' . str_repeat('x', 1_048_572);
        self::assertSame(strlen($row['protected_body']), strlen($hydrator->hydrate($row)->protectedBody()));

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'BOPD' . "\0" . str_repeat('r', 48));
        rewind($stream);
        $row['protected_body'] = $stream;
        self::assertSame('BOPD' . "\0" . str_repeat('r', 48), $hydrator->hydrate($row)->protectedBody());
        fclose($stream);
    }

    public function testInvalidRowsReturnOnlyTheFixedFailureAndDoNotLeakCanaries(): void
    {
        $hydrator = new PostgreSqlAuditProtectedIngressHydrator();
        $canary = 'ingress-hydrator-canary-018f0a00-0000-7000-8000-000000000001';
        $row = $this->row();
        $row['protected_body'] = 'NOPE' . $canary . str_repeat('x', 20);

        try {
            $hydrator->hydrate($row);
            self::fail('malformed ingress was accepted');
        } catch (AuditOutboxStoreException $exception) {
            $surfaces = [
                $exception->getMessage(),
                (string) $exception->getPrevious(),
                $exception->getTraceAsString(),
                var_export($exception->getTrace(), true),
                print_r($exception, true),
                var_export($exception, true),
                json_encode($exception, JSON_THROW_ON_ERROR),
                serialize($exception),
            ];
            foreach ($surfaces as $surface) {
                self::assertStringNotContainsString($canary, $surface);
            }
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        return [
            'record_id' => '018f0a00-0000-7000-8000-000000000001',
            'partition_id' => 'system:global',
            'tenant_type' => null,
            'tenant_id' => null,
            'ingress_ordinal' => 1,
            'idempotency_key_hash' => str_repeat('a', 64),
            'content_hash' => str_repeat('b', 64),
            'protected_body' => 'BOPD' . str_repeat('x', 49),
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
            self::fail('malformed ingress row was accepted');
        } catch (AuditOutboxStoreException $exception) {
            self::assertSame(AuditOutboxStoreFailure::IntegrityInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }
}
