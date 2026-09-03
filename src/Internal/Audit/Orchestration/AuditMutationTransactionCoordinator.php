<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Orchestration;

use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use Closure;

final readonly class AuditMutationTransactionCoordinator
{
    public function __construct(
        private OperationTransactionCoordinator $transactions,
        private AuditDeliveryCoordinator $delivery,
    ) {}

    public function execute(
        AuditDeliveryPlan $plan,
        OperationMetadata $metadata,
        #[\SensitiveParameter]
        Closure $invoke,
        #[\SensitiveParameter]
        Closure $deliveryRequest,
    ): OperationResult {
        $this->assertMetadata($metadata);
        $businessThrowable = null;
        $businessSignal = new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);

        try {
            return $this->transactions->execute(
                $metadata,
                static function () use (&$businessSignal, &$businessThrowable, $invoke): OperationResult {
                    try {
                        $result = $invoke();
                    } catch (\Throwable $failure) {
                        $businessThrowable = $failure;

                        throw $businessSignal;
                    }
                    if (!$result instanceof OperationResult) {
                        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
                    }

                    return $result;
                },
                function (#[\SensitiveParameter] OperationResult $result) use ($plan, $deliveryRequest): void {
                    try {
                        $request = $deliveryRequest($result);
                    } catch (\Throwable) {
                        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
                    }
                    if (!$request instanceof AuditDeliveryRequest) {
                        throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
                    }

                    $this->delivery->deliverInCurrentTransaction($plan, $request);
                },
            );
        } catch (\Throwable $failure) {
            if ($failure === $businessSignal) {
                if ($businessThrowable !== null) {
                    throw $businessThrowable;
                }

                throw $failure;
            }
            if ($failure instanceof AuditDeliveryException) {
                throw $failure;
            }

            throw new AuditDeliveryException(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    private function assertMetadata(#[\SensitiveParameter] OperationMetadata $metadata): void
    {
        try {
            if (!$this->transactions->isTransactional($metadata)) {
                throw new \RuntimeException();
            }
            if (!$this->transactions->sharesFrameworkConnection($metadata)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditDeliveryException(AuditDeliveryFailure::TransactionInvalid);
        }
    }
}
