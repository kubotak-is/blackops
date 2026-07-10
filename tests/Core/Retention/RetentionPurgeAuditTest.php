<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionPolicyRef;
use BlackOps\Core\Retention\RetentionPurgeAuditPort;
use BlackOps\Core\Retention\RetentionPurgeAuditRecord;
use BlackOps\Core\Retention\RetentionPurgeTarget;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RetentionPurgeAuditTest extends TestCase
{
    private const AUDIT_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688a01';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688a02';

    public function testContractsArePublicApi(): void
    {
        foreach ([
            RetentionPurgeTarget::class,
            RetentionPolicyRef::class,
            RetentionPurgeAuditRecord::class,
            RetentionPurgeAuditPort::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }

    public function testTargetsUseStableWireValues(): void
    {
        self::assertSame('transport_payload', RetentionPurgeTarget::TransportPayload->value);
        self::assertSame('journal', RetentionPurgeTarget::Journal->value);
        self::assertSame('outcome', RetentionPurgeTarget::Outcome->value);
        self::assertSame('dead_letter', RetentionPurgeTarget::DeadLetter->value);
    }

    public function testPolicyReferenceRejectsEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RetentionPolicyRef::fromString('   ');
    }

    public function testPolicyReferenceNormalizesOuterWhitespaceAndComparesByValue(): void
    {
        $policy = RetentionPolicyRef::fromString('  production-retention-v1  ');
        $same = RetentionPolicyRef::fromString('production-retention-v1');

        self::assertSame('production-retention-v1', $policy->toString());
        self::assertSame('production-retention-v1', (string) $policy);
        self::assertTrue($policy->equals($same));
    }

    public function testAuditRecordCarriesPayloadFreePurgeMetadata(): void
    {
        $record = $this->record();

        self::assertSame(self::AUDIT_ID, $record->id()->toString());
        self::assertSame(self::OPERATION_ID, $record->operationId()->toString());
        self::assertSame(RetentionPurgeTarget::TransportPayload, $record->target());
        self::assertSame(2, $record->affectedCount());
        self::assertSame('production-retention-v1', $record->policy()->toString());
        self::assertSame('2026-07-10T15:00:00+00:00', $record->purgedAt()->format(DATE_ATOM));
        self::assertSame('system:retention', $record->purgedBy()->toString());
    }

    public function testAuditRecordRejectsZeroAffectedCount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetentionPurgeAuditRecord(
            RetentionPurgeAuditId::fromString(self::AUDIT_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionPurgeTarget::Journal,
            0,
            RetentionPolicyRef::fromString('production-retention-v1'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            RetentionActorRef::fromString('system:retention'),
        );
    }

    private function record(): RetentionPurgeAuditRecord
    {
        return new RetentionPurgeAuditRecord(
            RetentionPurgeAuditId::fromString(self::AUDIT_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionPurgeTarget::TransportPayload,
            2,
            RetentionPolicyRef::fromString('production-retention-v1'),
            new DateTimeImmutable('2026-07-11T00:00:00+09:00'),
            RetentionActorRef::fromString('system:retention'),
        );
    }
}
