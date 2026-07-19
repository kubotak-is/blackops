<?php

declare(strict_types=1);

namespace BlackOps\Tests\Status;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Status\OperationStatusState;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;

final class OperationStatusStateTest extends TestCase
{
    public function testDefinesTheSevenStableStates(): void
    {
        self::assertSame(
            [
                'accepted',
                'running',
                'retry_scheduled',
                'completed',
                'rejected',
                'failed',
                'dead_lettered',
            ],
            array_map(static fn(OperationStatusState $state): string => $state->value, OperationStatusState::cases()),
        );
    }

    public function testClassifiesOnlyTerminalStatesAsTerminal(): void
    {
        self::assertFalse(OperationStatusState::Accepted->isTerminal());
        self::assertFalse(OperationStatusState::Running->isTerminal());
        self::assertFalse(OperationStatusState::RetryScheduled->isTerminal());
        self::assertTrue(OperationStatusState::Completed->isTerminal());
        self::assertTrue(OperationStatusState::Rejected->isTerminal());
        self::assertTrue(OperationStatusState::Failed->isTerminal());
        self::assertTrue(OperationStatusState::DeadLettered->isTerminal());
    }

    public function testIsAStringBackedPublicApiEnum(): void
    {
        $reflection = new ReflectionEnum(OperationStatusState::class);

        self::assertSame('string', $reflection->getBackingType()?->getName());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }
}
