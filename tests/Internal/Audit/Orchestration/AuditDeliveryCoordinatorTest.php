<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCatalog;
use BlackOps\Core\Audit\AuditRecordAction;
use BlackOps\Core\Audit\AuditRecordActor;
use BlackOps\Core\Audit\AuditRecordCorrelation;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordEvent;
use BlackOps\Core\Audit\AuditRecordEvidence;
use BlackOps\Core\Audit\AuditRecordOutcome;
use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Audit\AuditRecordResource;
use BlackOps\Core\Audit\AuditRecordSource;
use BlackOps\Core\Audit\AuditSourceOwner;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuditDeliveryCoordinatorTest extends TestCase
{
    public function testStandaloneRejectsEveryAmbientOrManualTransactionBeforeStoreWork(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();

        foreach ([[false, false, 0], [true, true, 0], [true, false, 1], [true, false, 2]] as [
            $autoCommit,
            $active,
            $level,
        ]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn($autoCommit);
            if ($autoCommit) {
                $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
                if (!$active) {
                    $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
                }
            }
            $coordinator = new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);

            try {
                $coordinator->deliverStandalone(new AuditDeliveryRequest($this->draft($catalog), null));
                self::fail('invalid standalone transaction state was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testCurrentTransactionRequiresAutoCommitAndExactlyOneRootLevel(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();
        $plan = $selector->forDraft($this->draft($catalog));

        foreach ([[false, true, 1], [true, false, 1], [true, true, 0], [true, true, 2]] as [
            $autoCommit,
            $active,
            $level,
        ]) {
            $connection = $this->createMock(Connection::class);
            $connection->expects(self::once())->method('isAutoCommit')->willReturn($autoCommit);
            if ($autoCommit) {
                $connection->expects(self::once())->method('isTransactionActive')->willReturn($active);
                if ($active) {
                    $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn($level);
                }
            }
            $coordinator = new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);
            try {
                $coordinator->deliverInCurrentTransaction(
                    $plan,
                    new AuditDeliveryRequest($this->draft($catalog), null),
                );
                self::fail('invalid current transaction state was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::TransactionInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testPolicyValidationPrecedesConnectionInspection(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('isAutoCommit');
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();
        $coordinator = new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);
        $plan = $selector->forDraft($this->draft($catalog));
        $wrong = AuditDeliveryPlan::fromSelection(
            str_repeat('b', 64),
            $plan->eventType(),
            $plan->eventVersion(),
            $plan->evidenceVersion(),
            AuditDelivery::Required,
        );

        try {
            $coordinator->deliverInCurrentTransaction($wrong, new AuditDeliveryRequest($this->draft($catalog), null));
            self::fail('mismatched plan was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testStoreFailureIsPreservedWithoutCatchPostStateRecheck(): void
    {
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new ImmutableAuditEventCatalogLookup([$catalog]);
        $selector = new AuditDeliverySelector($lookup, new AuditRecordValidator($lookup));
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('isAutoCommit')->willReturn(true);
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(false);
        $connection->expects(self::once())->method('getTransactionNestingLevel')->willReturn(0);
        $records = new ReflectionClass(PostgreSqlAuditRecordStore::class)->newInstanceWithoutConstructor();
        $outbox = new ReflectionClass(PostgreSqlAuditOutboxStore::class)->newInstanceWithoutConstructor();
        $coordinator = new AuditDeliveryCoordinator($connection, $selector, $records, $outbox);

        try {
            $coordinator->deliverStandalone(new AuditDeliveryRequest($this->draft($catalog), null));
            self::fail('uninitialized store was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testSuccessOnlyPostStateChecksAreOrderedAfterEachStoreRoute(): void
    {
        $path = __DIR__ . '/../../../../src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php';
        $source = file_get_contents($path);
        self::assertIsString($source);
        $standaloneStart = strpos($source, 'public function deliverStandalone(');
        $currentStart = strpos($source, 'public function deliverInCurrentTransaction(');
        $appendStart = strpos($source, 'private function append(');
        $appendEnd = strpos($source, 'private function assertStandaloneTransaction(', $appendStart);
        self::assertIsInt($standaloneStart);
        self::assertIsInt($currentStart);
        self::assertIsInt($appendStart);
        self::assertIsInt($appendEnd);

        $standalone = substr($source, $standaloneStart, $currentStart - $standaloneStart);
        $current = substr($source, $currentStart, $appendStart - $currentStart);
        $append = substr($source, $appendStart, $appendEnd - $appendStart);
        foreach ([
            ['body' => $standalone, 'assert' => '$this->assertStandaloneTransaction();'],
            ['body' => $current, 'assert' => '$this->assertCurrentTransaction();'],
        ] as $route) {
            self::assertSame(2, substr_count($route['body'], $route['assert']));
            self::assertSame(1, substr_count($route['body'], '$this->append($delivery, $request);'));
            $precheck = strpos($route['body'], $route['assert']);
            $appendCall = strpos($route['body'], '$this->append($delivery, $request);');
            $postcheck = strrpos($route['body'], $route['assert']);
            self::assertIsInt($precheck);
            self::assertIsInt($appendCall);
            self::assertIsInt($postcheck);
            self::assertLessThan($appendCall, $precheck);
            self::assertLessThan($postcheck, $appendCall);
        }

        self::assertSame(1, substr_count($append, '->appendRequired('));
        self::assertSame(1, substr_count($append, '->appendDurable('));
        self::assertSame(0, substr_count($append, '$this->assertStandaloneTransaction();'));
        self::assertSame(0, substr_count($append, '$this->assertCurrentTransaction();'));
        self::assertSame(0, substr_count($append, 'beginTransaction'));
        self::assertSame(0, substr_count($append, 'commit'));
        self::assertSame(0, substr_count($append, 'rollBack'));
    }

    private function draft(AuditEventCatalog $catalog): AuditRecordDraft
    {
        foreach ($catalog->events as $definition) {
            if ($definition->delivery !== AuditDelivery::Required) {
                continue;
            }
            $tuple = $definition->semanticTuples[0];
            return new AuditRecordDraft(
                new AuditRecordEvent(
                    $definition->type,
                    $definition->version,
                    $definition->category,
                    $catalog->catalogHash,
                ),
                new AuditRecordAction('action-1', $tuple->actionType, $tuple->phase),
                new AuditRecordActor(
                    new AuditTypedReference('user', 'user-1'),
                    new AuditTypedReference('execution', 'execution-1'),
                ),
                new AuditRecordResource($tuple->resourceType, 'resource-1'),
                new AuditRecordOutcome($tuple->outcomeStatus, $tuple->outcomeCode),
                new AuditRecordPolicy(
                    'policy-1',
                    1,
                    $tuple->policyDecision,
                    $tuple->policyReasonCode,
                    AuditDelivery::Required,
                ),
                null,
                '2026-09-02T00:00:00.000000Z',
                new AuditRecordSource(AuditSourceOwner::Framework, 'test', AuditSourceRuntime::Cli, 'build-1'),
                AuditRecordCorrelation::fromArray([]),
                new AuditRecordEvidence($definition->evidenceVersion),
            );
        }

        self::fail('required definition unavailable');
    }
}
