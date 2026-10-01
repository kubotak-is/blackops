<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

use BlackOps\Core\Audit\AuditRecordPolicy;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Carries one evaluated decision through exactly one delivery boundary.
 *
 * The request, plan, and trusted dependencies remain in a protected snapshot;
 * no operation envelope, metadata, handler, payload, or throwable is retained.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class AuthorizationAuditAttempt
{
    private \SensitiveParameterValue $snapshot;

    private bool $consumed = false;

    private function __construct(
        #[\SensitiveParameter]
        AuthorizationAuditEvaluation $evaluation,
        #[\SensitiveParameter]
        ?AuditDeliveryRequest $request,
        #[\SensitiveParameter]
        ?AuditDeliveryPlan $plan,
        #[\SensitiveParameter]
        AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'evaluation' => $evaluation,
            'request' => $request,
            'plan' => $plan,
            'delivery' => $delivery,
            'connection' => $connection,
        ]);
    }

    public static function prepare(
        #[\SensitiveParameter]
        AuthorizationAuditEvaluation $evaluation,
        #[\SensitiveParameter]
        ?AuditDeliveryRequest $request,
        #[\SensitiveParameter]
        ?AuditDeliveryPlan $plan,
        #[\SensitiveParameter]
        AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        Connection $connection,
    ): self {
        try {
            self::assertProjectionAgreement($evaluation, $request, $plan);

            return new self($evaluation, $request, $plan, $delivery, $connection);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    private static function assertProjectionAgreement(
        #[\SensitiveParameter]
        AuthorizationAuditEvaluation $evaluation,
        #[\SensitiveParameter]
        ?AuditDeliveryRequest $request,
        #[\SensitiveParameter]
        ?AuditDeliveryPlan $plan,
    ): void {
        try {
            $hasPolicy = $evaluation->hasPolicy();
            if (!is_bool($hasPolicy)) {
                throw new \LogicException();
            }
            if (!$hasPolicy) {
                if (
                    $request !== null
                    || $plan !== null
                    || $evaluation->policy() !== null
                    || $evaluation->rejection() !== null
                ) {
                    throw new \LogicException();
                }

                return;
            }
            if (!$request instanceof AuditDeliveryRequest || !$plan instanceof AuditDeliveryPlan) {
                throw new \LogicException();
            }
            $policy = $evaluation->policy();
            $draft = $request->draft();
            $draftPolicy = $draft->policy;
            if (
                !$policy instanceof AuditRecordPolicy
                || !$draftPolicy instanceof AuditRecordPolicy
                || $policy->id !== $draftPolicy->id
                || $policy->version !== $draftPolicy->version
                || $policy->decision !== $draftPolicy->decision
                || $policy->reasonCode !== $draftPolicy->reasonCode
                || $policy->auditDelivery !== $plan->delivery()
                || $policy->auditDelivery !== $draftPolicy->auditDelivery
            ) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::PolicyInvalid);
        }
    }

    public function evaluation(): AuthorizationAuditEvaluation
    {
        $evaluation = $this->value('evaluation');
        $request = $this->value('request');
        $plan = $this->value('plan');
        if (
            !$evaluation instanceof AuthorizationAuditEvaluation
            || $request !== null && !$request instanceof AuditDeliveryRequest
            || $plan !== null && !$plan instanceof AuditDeliveryPlan
        ) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            self::assertProjectionAgreement($evaluation, $request, $plan);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $evaluation;
    }

    public function assertMutation(
        #[\SensitiveParameter]
        OperationMetadata $metadata,
        #[\SensitiveParameter]
        ?OperationTransactionCoordinator $transactions,
    ): void {
        try {
            $evaluation = $this->evaluation();
            if (!$evaluation->hasPolicy()) {
                return;
            }
            $delivery = $this->value('delivery');
            $connection = $this->value('connection');
            if (
                !$delivery instanceof AuditDeliveryCoordinator
                || !$connection instanceof Connection
                || $transactions === null
                || !$transactions->isTransactional($metadata)
                || !$transactions->sharesFrameworkConnection($metadata)
                || !$transactions->usesFrameworkConnection($connection)
                || !$delivery->usesConnection($connection)
                || !$connection->isAutoCommit()
                || $connection->isTransactionActive()
                || $connection->getTransactionNestingLevel() !== 0
            ) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
    }

    public function deliverStandalone(): void
    {
        $values = $this->deliveryValues();
        if ($values['evaluation']->hasPolicy() === false) {
            return;
        }
        $request = $values['request'];
        if (!$request instanceof AuditDeliveryRequest) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            if (!$values['delivery']->usesConnection($values['connection'])) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
        $this->consume();
        try {
            $values['delivery']->deliverStandalone($request);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    public function deliverInMutationTransaction(): void
    {
        $values = $this->deliveryValues();
        if ($values['evaluation']->hasPolicy() === false) {
            return;
        }
        $request = $values['request'];
        $plan = $values['plan'];
        if (!$request instanceof AuditDeliveryRequest || !$plan instanceof AuditDeliveryPlan) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            if (!$values['delivery']->usesConnection($values['connection'])) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
        $this->consume();
        try {
            $values['delivery']->deliverInCurrentTransaction($plan, $request);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    public function deliverInOwnedTransaction(#[\SensitiveParameter] Connection $connection): void
    {
        $values = $this->deliveryValues();
        if ($values['evaluation']->hasPolicy() === false) {
            return;
        }
        $request = $values['request'];
        $plan = $values['plan'];
        if (!$request instanceof AuditDeliveryRequest || !$plan instanceof AuditDeliveryPlan) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        try {
            if ($connection !== $values['connection'] || !$values['delivery']->usesConnection($connection)) {
                throw new \LogicException();
            }
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::TransactionInvalid);
        }
        $this->consume();
        try {
            $values['delivery']->deliverInCurrentTransaction($plan, $request);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    public function __clone(): void
    {
        throw self::failure(AuditDeliveryFailure::PolicyInvalid);
    }

    /** @return array<never, never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<never, never> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw self::failure(AuditDeliveryFailure::StorageUnavailable);
    }

    /** @return array{evaluation:AuthorizationAuditEvaluation,request:AuditDeliveryRequest|null,plan:AuditDeliveryPlan|null,delivery:AuditDeliveryCoordinator,connection:Connection} */
    private function deliveryValues(): array
    {
        try {
            $evaluation = $this->value('evaluation');
            $request = $this->value('request');
            $plan = $this->value('plan');
            $delivery = $this->value('delivery');
            $connection = $this->value('connection');
            if (
                !$evaluation instanceof AuthorizationAuditEvaluation
                || $request !== null && !$request instanceof AuditDeliveryRequest
                || $plan !== null && !$plan instanceof AuditDeliveryPlan
                || !$delivery instanceof AuditDeliveryCoordinator
                || !$connection instanceof Connection
            ) {
                throw self::failure(AuditDeliveryFailure::StorageUnavailable);
            }
            try {
                self::assertProjectionAgreement($evaluation, $request, $plan);
            } catch (Throwable) {
                throw self::failure(AuditDeliveryFailure::StorageUnavailable);
            }

            return [
                'evaluation' => $evaluation,
                'request' => $request,
                'plan' => $plan,
                'delivery' => $delivery,
                'connection' => $connection,
            ];
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    private function consume(): void
    {
        try {
            if ($this->consumed) {
                throw self::failure(AuditDeliveryFailure::PolicyInvalid);
            }
            $this->consumed = true;
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['evaluation', 'request', 'plan', 'delivery', 'connection']
            || !$value['evaluation'] instanceof AuthorizationAuditEvaluation
            || $value['request'] !== null && !$value['request'] instanceof AuditDeliveryRequest
            || $value['plan'] !== null && !$value['plan'] instanceof AuditDeliveryPlan
            || !$value['delivery'] instanceof AuditDeliveryCoordinator
            || !$value['connection'] instanceof Connection
            || !array_key_exists($key, $value)
        ) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }

        return $value[$key];
    }

    private static function failure(AuditDeliveryFailure $failure): AuditDeliveryException
    {
        return new AuditDeliveryException($failure);
    }

    private static function remap(#[\SensitiveParameter] AuditDeliveryException $failure): AuditDeliveryException
    {
        try {
            return self::failure($failure->failure());
        } catch (Throwable) {
            return self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
    }
}
