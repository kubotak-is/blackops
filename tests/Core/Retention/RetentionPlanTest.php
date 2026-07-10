<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanItem;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionTarget;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RetentionPlanTest extends TestCase
{
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688b01';

    public function testContractsArePublicApi(): void
    {
        foreach ([RetentionPlanItem::class, RetentionPlan::class, RetentionPlanner::class] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }

    public function testPlanItemCarriesPayloadFreeCandidateMetadata(): void
    {
        $item = new RetentionPlanItem(
            OperationId::fromString(self::OPERATION_ID),
            RetentionTarget::TransportPayload,
            new DateTimeImmutable('2026-07-10T00:00:00+09:00'),
            new DateTimeImmutable('2026-07-11T00:00:00+09:00'),
        );

        self::assertSame(self::OPERATION_ID, $item->operationId()->toString());
        self::assertSame(RetentionTarget::TransportPayload, $item->target());
        self::assertSame('2026-07-09T15:00:00+00:00', $item->basisAt()->format(DATE_ATOM));
        self::assertSame('2026-07-10T15:00:00+00:00', $item->eligibleAt()->format(DATE_ATOM));
    }

    public function testPlanItemRejectsEligibleTimeBeforeBasisTime(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetentionPlanItem(
            OperationId::fromString(self::OPERATION_ID),
            RetentionTarget::Journal,
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            new DateTimeImmutable('2026-07-09T23:59:59Z'),
        );
    }

    public function testPlanProvidesStableItemListAndTargetFiltering(): void
    {
        $payload = $this->item(RetentionTarget::TransportPayload);
        $deadLetter = $this->item(RetentionTarget::DeadLetter);
        $plan = new RetentionPlan([$payload, $deadLetter]);

        self::assertFalse($plan->isEmpty());
        self::assertSame(2, $plan->count());
        self::assertSame([$payload, $deadLetter], $plan->items());
        self::assertSame([$payload], $plan->forTarget(RetentionTarget::TransportPayload));
        self::assertSame([], $plan->forTarget(RetentionTarget::Outcome));
    }

    private function item(RetentionTarget $target): RetentionPlanItem
    {
        return new RetentionPlanItem(
            OperationId::fromString(self::OPERATION_ID),
            $target,
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );
    }
}
