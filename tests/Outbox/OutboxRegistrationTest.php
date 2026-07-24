<?php

declare(strict_types=1);

namespace BlackOps\Tests\Outbox;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Outbox\OutboxRegistration;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OutboxRegistrationTest extends TestCase
{
    public function testExposesOnlyTypedIdentityAndUtcRegistrationTime(): void
    {
        $recordId = OutboxRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $operationId = OperationId::fromString('019f32ac-2be0-7b38-a0a7-1ab2f9687697');
        $registration = new OutboxRegistration(
            $recordId,
            $operationId,
            new DateTimeImmutable('2026-07-24T10:02:03+09:00'),
        );

        self::assertSame($recordId, $registration->recordId());
        self::assertSame($operationId, $registration->operationId());
        self::assertSame('UTC', $registration->recordedAt()->getTimezone()->getName());
        self::assertSame('2026-07-24T01:02:03+00:00', $registration->recordedAt()->format(DateTimeImmutable::ATOM));
    }
}
