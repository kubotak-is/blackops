<?php

declare(strict_types=1);

namespace BlackOps\Internal\Authorization;

use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use LogicException;

final readonly class AuthorizationEvaluator
{
    public function __construct(
        private AuthorizationPolicyResolver $policies,
    ) {}

    public function evaluate(OperationMetadata $metadata, OperationEnvelope $envelope): ?RejectionReason
    {
        if ($metadata->authorizationPolicy === null) {
            return null;
        }

        $actor = $envelope->context()->actorContext()?->authorization();
        if ($actor === null) {
            return RejectionReason::unauthorized('authorization.authentication_required');
        }

        $policy = $this->policies->resolve($metadata) ?? throw new LogicException(
            'Operation authorization policy metadata could not be resolved.',
        );
        $decision = $policy->decide(
            new AuthorizationRequest($envelope->definition(), $envelope->value(), $envelope->context(), $actor),
        );

        if ($decision->isAllowed()) {
            return null;
        }

        $code = $decision->code() ?? throw new LogicException('Authorization rejection requires a stable code.');

        return $decision->isUnauthorized() ? RejectionReason::unauthorized($code) : RejectionReason::forbidden($code);
    }
}
