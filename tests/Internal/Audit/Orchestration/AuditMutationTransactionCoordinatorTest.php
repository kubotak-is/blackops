<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditMutationTransactionCoordinator;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuditMutationTransactionCoordinatorTest extends TestCase
{
    public function testNonTransactionalMetadataRejectsBeforeEitherClosureRuns(): void
    {
        $transactions = new ReflectionClass(OperationTransactionCoordinator::class)->newInstanceWithoutConstructor();
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $coordinator = new AuditMutationTransactionCoordinator($transactions, $delivery);
        $plan = AuditDeliveryPlan::fromSelection(
            str_repeat('a', 64),
            'business.operation',
            1,
            1,
            AuditDelivery::Required,
        );
        $invoked = 0;
        $requested = 0;

        try {
            $coordinator->execute(
                $plan,
                $this->metadata(null),
                function () use (&$invoked): OperationResult {
                    $invoked++;
                    return OperationResult::completed();
                },
                function () use (&$requested): AuditDeliveryRequest {
                    $requested++;
                    throw new \LogicException('request-factory-should-not-run');
                },
            );
            self::fail('non-transactional metadata was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $invoked);
        self::assertSame(0, $requested);
    }

    public function testCrossConnectionMetadataRejectsBeforeBusinessInvocation(): void
    {
        $transactions = new ReflectionClass(OperationTransactionCoordinator::class)->newInstanceWithoutConstructor();
        $delivery = new ReflectionClass(AuditDeliveryCoordinator::class)->newInstanceWithoutConstructor();
        $coordinator = new AuditMutationTransactionCoordinator($transactions, $delivery);
        $plan = AuditDeliveryPlan::fromSelection(
            str_repeat('a', 64),
            'business.operation',
            1,
            1,
            AuditDelivery::Required,
        );
        $invoked = 0;
        $requested = 0;

        try {
            $coordinator->execute(
                $plan,
                $this->metadata('other'),
                function () use (&$invoked): OperationResult {
                    $invoked++;
                    return OperationResult::completed();
                },
                function () use (&$requested): AuditDeliveryRequest {
                    $requested++;
                    throw new \LogicException('request-factory-should-not-run');
                },
            );
            self::fail('cross-connection metadata was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $invoked);
        self::assertSame(0, $requested);
    }

    public function testTransactionWrapperKeepsRequestFactoryInsideCompletionCallback(): void
    {
        $source = file_get_contents(__DIR__
        . '/../../../../src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php');
        self::assertIsString($source);
        self::assertStringContainsString('$request = $deliveryRequest($result);', $source);
        self::assertStringContainsString('$this->delivery->deliverInCurrentTransaction($plan, $request);', $source);
        self::assertSame(1, substr_count($source, '$deliveryRequest($result)'));
        self::assertSame(1, substr_count($source, 'deliverInCurrentTransaction('));
        self::assertSame(0, substr_count($source, 'beginTransaction('));
        self::assertSame(0, substr_count($source, '->commit('));
        self::assertSame(0, substr_count($source, '->rollBack('));
    }

    private function metadata(?string $connection): OperationMetadata
    {
        return new OperationMetadata(
            'test.operation',
            'TestOperation',
            'TestValue',
            'TestHandler',
            'TestOutcome',
            'TestStrategy',
            transactionConnection: $connection,
        );
    }
}
