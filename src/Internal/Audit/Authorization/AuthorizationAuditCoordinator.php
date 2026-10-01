<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Authorization;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryPlan;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryRequest;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Joins the accepted typed evaluation, fixed event projection, and trusted
 * delivery selection into one attempt-local boundary.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class AuthorizationAuditCoordinator
{
    private \SensitiveParameterValue $snapshot;

    public function __construct(
        #[\SensitiveParameter]
        AuthorizationAuditEvaluator $evaluator,
        #[\SensitiveParameter]
        AuthorizationAuditEventFactory $factory,
        #[\SensitiveParameter]
        AuditDeliverySelector $selector,
        #[\SensitiveParameter]
        AuditDeliveryCoordinator $delivery,
        #[\SensitiveParameter]
        Connection $connection,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'evaluator' => $evaluator,
            'factory' => $factory,
            'selector' => $selector,
            'delivery' => $delivery,
            'connection' => $connection,
        ]);
    }

    public function evaluate(
        #[\SensitiveParameter]
        OperationMetadata $metadata,
        #[\SensitiveParameter]
        OperationEnvelope $envelope,
    ): AuthorizationAuditAttempt {
        try {
            $evaluator = $this->value('evaluator');
            $factory = $this->value('factory');
            $selector = $this->value('selector');
            $delivery = $this->value('delivery');
            $connection = $this->value('connection');
            if (
                !$evaluator instanceof AuthorizationAuditEvaluator
                || !$factory instanceof AuthorizationAuditEventFactory
                || !$selector instanceof AuditDeliverySelector
                || !$delivery instanceof AuditDeliveryCoordinator
                || !$connection instanceof Connection
            ) {
                throw self::failure(AuditDeliveryFailure::StorageUnavailable);
            }

            $evaluation = $evaluator->evaluate($metadata, $envelope);
            if (!$evaluation instanceof AuthorizationAuditEvaluation) {
                throw self::failure(AuditDeliveryFailure::PolicyInvalid);
            }
            $request = $factory->create($evaluation, $envelope->context());
            $plan = null;
            if ($request instanceof AuditDeliveryRequest) {
                $plan = $selector->forDraft($request->draft());
                $selected = $selector->validate($plan, $request);
                if (!$plan instanceof AuditDeliveryPlan || $selected !== $plan->delivery()) {
                    throw self::failure(AuditDeliveryFailure::PolicyInvalid);
                }
            }

            return AuthorizationAuditAttempt::prepare($evaluation, $request, $plan, $delivery, $connection);
        } catch (AuditDeliveryException $failure) {
            throw self::remap($failure);
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
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

    private function value(#[\SensitiveParameter] string $key): mixed
    {
        try {
            $value = $this->snapshot->getValue();
        } catch (Throwable) {
            throw self::failure(AuditDeliveryFailure::StorageUnavailable);
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['evaluator', 'factory', 'selector', 'delivery', 'connection']
            || !$value['evaluator'] instanceof AuthorizationAuditEvaluator
            || !$value['factory'] instanceof AuthorizationAuditEventFactory
            || !$value['selector'] instanceof AuditDeliverySelector
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
