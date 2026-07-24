<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Identifier;

use BlackOps\Core\Identifier\OutboxRecordId;
use PHPUnit\Framework\TestCase;

final class OutboxRecordIdTest extends TestCase
{
    public function testIdentifierBehaviorRoundTripsAndNormalizes(): void
    {
        $id = OutboxRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');

        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f9687697', $id->toString());
        self::assertSame($id->toString(), (string) $id);
        self::assertTrue($id->equals(OutboxRecordId::fromString(strtoupper($id->toString()))));
    }

    public function testInvalidUuidIsRejected(): void
    {
        $this->expectException(\BlackOps\Core\Exception\InvalidIdentifierException::class);

        OutboxRecordId::fromString('not-an-id');
    }
}
