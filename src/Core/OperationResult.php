<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Rejection\RejectionReason;
use LogicException;

/**
 * @template-covariant TOutcome of Outcome
 */
#[PublicApi]
final readonly class OperationResult
{
    /**
     * @var TOutcome|null
     */
    private ?Outcome $outcome;

    /**
     * @param TOutcome|null $outcome
     */
    private function __construct(
        ?Outcome $outcome,
        private ?RejectionReason $rejectionReason,
        private ?OperationId $operationId,
    ) {
        $this->outcome = $outcome;
    }

    /**
     * @template TCompleted of Outcome
     *
     * @param TCompleted $outcome
     *
     * @return self<TCompleted>
     */
    public static function completed(Outcome $outcome = new EmptyOutcome()): self
    {
        return new self($outcome, null, null);
    }

    /**
     * @return self<Outcome>
     */
    public static function rejected(RejectionReason $reason, ?OperationId $operationId = null): self
    {
        return new self(null, $reason, $operationId);
    }

    public function isCompleted(): bool
    {
        return $this->outcome !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejectionReason !== null;
    }

    public function outcome(): Outcome
    {
        return $this->outcome ?? throw new LogicException('Rejected operation result has no outcome.');
    }

    public function rejectionReason(): RejectionReason
    {
        return (
            $this->rejectionReason ?? throw new LogicException('Completed operation result has no rejection reason.')
        );
    }

    public function operationId(): ?OperationId
    {
        return $this->operationId;
    }
}
