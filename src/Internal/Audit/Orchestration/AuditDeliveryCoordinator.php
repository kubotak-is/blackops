<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use Doctrine\DBAL\Connection;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class AuditDeliveryCoordinator
{
    public function __construct(
        private Connection $connection,
        private AuditDeliverySelector $selector,
        private PostgreSqlAuditRecordStore $records,
        private PostgreSqlAuditOutboxStore $outbox,
    ) {}

    public function deliverStandalone(#[\SensitiveParameter] AuditDeliveryRequest $request): void
    {
        $plan = $this->selector->forDraft($request->draft());
        $delivery = $this->selector->validate($plan, $request);
        $this->assertStandaloneTransaction();
        $this->append($delivery, $request);
        $this->assertStandaloneTransaction();
    }

    public function deliverInCurrentTransaction(
        AuditDeliveryPlan $plan,
        #[\SensitiveParameter]
        AuditDeliveryRequest $request,
    ): void {
        $delivery = $this->selector->validate($plan, $request);
        $this->assertCurrentTransaction();
        $this->append($delivery, $request);
        $this->assertCurrentTransaction();
    }

    private function append(AuditDelivery $delivery, #[\SensitiveParameter] AuditDeliveryRequest $request): void
    {
        try {
            if ($delivery === AuditDelivery::Required) {
                $this->records->appendRequired($request->draft());

                return;
            }

            $key = $request->idempotencyKey();
            if ($key === null) {
                throw self::policyFailure();
            }
            $this->outbox->appendDurable($request->draft(), $key);
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (AuditStoreException $failure) {
            if ($failure->failure() === AuditStoreFailure::ResourceUnavailable) {
                throw self::resourceFailure();
            }
            throw self::storageFailure();
        } catch (AuditOutboxStoreException $failure) {
            if ($failure->failure() === AuditOutboxStoreFailure::ResourceUnavailable) {
                throw self::resourceFailure();
            }
            throw self::storageFailure();
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    private function assertStandaloneTransaction(): void
    {
        try {
            if (!$this->connection->isAutoCommit() || $this->connection->isTransactionActive()) {
                throw new \RuntimeException();
            }
            if ($this->connection->getTransactionNestingLevel() !== 0) {
                throw new \RuntimeException();
            }
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    private function assertCurrentTransaction(): void
    {
        try {
            if (!$this->connection->isAutoCommit() || !$this->connection->isTransactionActive()) {
                throw new \RuntimeException();
            }
            if ($this->connection->getTransactionNestingLevel() !== 1) {
                throw new \RuntimeException();
            }
        } catch (AuditDeliveryException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    private static function policyFailure(): AuditDeliveryException
    {
        return new AuditDeliveryException(AuditDeliveryFailure::PolicyInvalid);
    }

    private static function resourceFailure(): AuditDeliveryException
    {
        return new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
    }

    private static function storageFailure(): AuditDeliveryException
    {
        return new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
    }
}
