<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionReason;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationResultTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testPublicApiShape(): void
    {
        $result = new ReflectionClass(OperationResult::class);
        $empty = new ReflectionClass(EmptyOutcome::class);

        self::assertTrue($result->isFinal());
        self::assertTrue($result->isReadOnly());
        self::assertTrue($result->getConstructor()?->isPrivate());
        self::assertCount(1, $result->getAttributes(PublicApi::class));
        self::assertTrue($empty->isFinal());
        self::assertTrue($empty->isReadOnly());
        self::assertTrue($empty->implementsInterface(Outcome::class));
        self::assertCount(1, $empty->getAttributes(PublicApi::class));
    }

    public function testCompletedWithOutcome(): void
    {
        $outcome = new ResultOutcomeFixture('done');
        $result = OperationResult::completed($outcome);

        self::assertTrue($result->isCompleted());
        self::assertFalse($result->isRejected());
        self::assertSame($outcome, $result->outcome());
        self::assertNull($result->operationId());
    }

    public function testCompletedWithoutOutcomeUsesEmptyOutcome(): void
    {
        $result = OperationResult::completed();

        self::assertTrue($result->isCompleted());
        self::assertInstanceOf(EmptyOutcome::class, $result->outcome());
    }

    public function testRejectedWithReason(): void
    {
        $reason = RejectionReason::conflict('inventory_unavailable');
        $result = OperationResult::rejected($reason);

        self::assertFalse($result->isCompleted());
        self::assertTrue($result->isRejected());
        self::assertSame($reason, $result->rejectionReason());
        self::assertNull($result->operationId());
    }

    public function testRejectedResultCanCarryOperationId(): void
    {
        $operationId = OperationId::fromString(self::OPERATION_ID);
        $result = OperationResult::rejected(RejectionReason::forbidden('authorization.denied'), $operationId);

        self::assertSame($operationId, $result->operationId());
        self::assertSame('authorization.denied', $result->rejectionReason()->code());
    }

    public function testRejectedHasNoOutcome(): void
    {
        $result = OperationResult::rejected(RejectionReason::conflict('inventory_unavailable'));

        $this->expectException(LogicException::class);
        $result->outcome();
    }

    public function testCompletedHasNoRejectionReason(): void
    {
        $result = OperationResult::completed();

        $this->expectException(LogicException::class);
        $result->rejectionReason();
    }

    public function testCompletedResultCanCarryReplayOperationIdWithoutChangingDefault(): void
    {
        $id = OperationId::fromString(self::OPERATION_ID);
        $original = OperationResult::completed();
        $replayed = OperationResult::completed(new EmptyOutcome(), $id)->asReplayed();

        self::assertNull($original->operationId());
        self::assertSame($id->toString(), $replayed->operationId()?->toString());
        self::assertTrue($replayed->isReplayed());
    }

    public function testRejectedReplayKeepsStableReasonAndOperationId(): void
    {
        $id = OperationId::fromString(self::OPERATION_ID);
        $result = OperationResult::rejected(RejectionReason::conflict('idempotency_conflict'), $id)->asReplayed();

        self::assertSame('idempotency_conflict', $result->rejectionReason()->code());
        self::assertSame($id->toString(), $result->operationId()?->toString());
        self::assertTrue($result->isReplayed());
    }
}

final readonly class ResultOutcomeFixture implements Outcome
{
    public function __construct(
        public string $value,
    ) {}
}
