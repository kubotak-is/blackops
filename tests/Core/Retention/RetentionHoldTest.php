<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHold;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionHoldPort;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RetentionHoldTest extends TestCase
{
    private const HOLD_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688801';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688802';

    public function testContractsArePublicApi(): void
    {
        foreach ([
            RetentionActorRef::class,
            RetentionHoldCategory::class,
            RetentionHold::class,
            RetentionHoldPort::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }

    public function testCategoriesUseStableWireValues(): void
    {
        self::assertSame('legal', RetentionHoldCategory::Legal->value);
        self::assertSame('security', RetentionHoldCategory::Security->value);
        self::assertSame('audit', RetentionHoldCategory::Audit->value);
        self::assertSame('support', RetentionHoldCategory::Support->value);
        self::assertSame('other', RetentionHoldCategory::Other->value);
    }

    public function testActorReferenceRejectsEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RetentionActorRef::fromString('   ');
    }

    public function testActorReferenceNormalizesOuterWhitespaceAndComparesByValue(): void
    {
        $actor = RetentionActorRef::fromString('  system:retention  ');
        $same = RetentionActorRef::fromString('system:retention');

        self::assertSame('system:retention', $actor->toString());
        self::assertSame('system:retention', (string) $actor);
        self::assertTrue($actor->equals($same));
    }

    public function testActiveHoldCarriesPlacementMetadata(): void
    {
        $hold = $this->hold();

        self::assertSame(self::HOLD_ID, $hold->id()->toString());
        self::assertSame(self::OPERATION_ID, $hold->operationId()->toString());
        self::assertSame(RetentionHoldCategory::Security, $hold->category());
        self::assertSame('security investigation', $hold->reason());
        self::assertSame('2026-07-10T00:00:00+00:00', $hold->placedAt()->format(DATE_ATOM));
        self::assertSame('security-team', $hold->placedBy()->toString());
        self::assertNull($hold->releasedAt());
        self::assertNull($hold->releasedBy());
        self::assertTrue($hold->isActive());
    }

    public function testHoldRequiresReason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetentionHold(
            RetentionHoldId::fromString(self::HOLD_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Legal,
            '',
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            RetentionActorRef::fromString('legal-team'),
        );
    }

    public function testHoldRequiresCompleteReleaseMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetentionHold(
            RetentionHoldId::fromString(self::HOLD_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Legal,
            'legal request',
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            RetentionActorRef::fromString('legal-team'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );
    }

    public function testHoldRejectsReleaseBeforePlacement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetentionHold(
            RetentionHoldId::fromString(self::HOLD_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Legal,
            'legal request',
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            RetentionActorRef::fromString('legal-team'),
            new DateTimeImmutable('2026-07-09T23:59:59Z'),
            RetentionActorRef::fromString('legal-team'),
        );
    }

    public function testReleaseReturnsReleasedRecord(): void
    {
        $released = $this->hold()->release(
            new DateTimeImmutable('2026-07-11T00:00:00+09:00'),
            RetentionActorRef::fromString('security-lead'),
        );

        self::assertFalse($released->isActive());
        self::assertSame('2026-07-10T15:00:00+00:00', $released->releasedAt()?->format(DATE_ATOM));
        self::assertSame('security-lead', $released->releasedBy()?->toString());
    }

    public function testReleaseRejectsAlreadyReleasedHold(): void
    {
        $released = $this->hold()->release(
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
            RetentionActorRef::fromString('security-lead'),
        );

        $this->expectException(LogicException::class);

        $released->release(
            new DateTimeImmutable('2026-07-12T00:00:00Z'),
            RetentionActorRef::fromString('security-lead'),
        );
    }

    private function hold(): RetentionHold
    {
        return new RetentionHold(
            RetentionHoldId::fromString(self::HOLD_ID),
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Security,
            'security investigation',
            new DateTimeImmutable('2026-07-10T09:00:00+09:00'),
            RetentionActorRef::fromString('security-team'),
        );
    }
}
