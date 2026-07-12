<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPurgeResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RetentionPurgeResultTest extends TestCase
{
    public function testContractIsPublicApi(): void
    {
        self::assertCount(1, new ReflectionClass(RetentionPurgeResult::class)->getAttributes(PublicApi::class));
    }

    public function testResultCarriesPlanAndCountsWithoutPayload(): void
    {
        $plan = new RetentionPlan([]);
        $result = new RetentionPurgeResult($plan, 2, 3, 4, 5);

        self::assertSame($plan, $result->plan());
        self::assertSame(2, $result->transportPayloadsPurged());
        self::assertSame(3, $result->deadLettersDeleted());
        self::assertSame(4, $result->outcomesDeleted());
        self::assertSame(5, $result->journalsDeleted());
        self::assertSame(14, $result->totalAffected());
    }

    public function testExistingConstructorCallDefaultsJournalCountToZero(): void
    {
        $result = new RetentionPurgeResult(new RetentionPlan([]), 1, 2, 3);

        self::assertSame(0, $result->journalsDeleted());
        self::assertSame(6, $result->totalAffected());
    }

    public function testResultRejectsNegativeCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetentionPurgeResult(new RetentionPlan([]), -1, 0);
    }
}
