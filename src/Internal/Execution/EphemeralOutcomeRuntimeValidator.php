<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Outcome\Internal\StructuredOutcomeNormalizer;
use LogicException;
use Throwable;

final readonly class EphemeralOutcomeRuntimeValidator
{
    public function __construct(
        private StructuredOutcomeNormalizer $outcomes = new StructuredOutcomeNormalizer(),
    ) {}

    public function validate(OperationMetadata $metadata, OperationResult $result): void
    {
        if (!$result->isCompleted()) {
            return;
        }

        $outcome = $result->outcome();
        $declaredEphemeral = is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true);
        $actualEphemeral = $outcome instanceof EphemeralOutcome;
        if ($declaredEphemeral !== $actualEphemeral) {
            throw new LogicException('Ephemeral operation outcome does not match its declared contract.');
        }
        if (!$declaredEphemeral) {
            return;
        }
        if ($outcome::class !== $metadata->outcome) {
            throw new LogicException('Ephemeral operation outcome does not match its declared contract.');
        }

        try {
            $payload = $this->outcomes->normalize($outcome);
            json_encode($payload === [] ? new \stdClass() : $payload, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new LogicException('Ephemeral operation outcome cannot be projected safely.');
        }
    }
}
