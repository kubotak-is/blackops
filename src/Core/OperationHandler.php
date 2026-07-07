<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;

/**
 * @template TValue of OperationValue
 * @template TOutcome of Outcome
 */
#[PublicApi]
interface OperationHandler
{
    /**
     * @param OperationEnvelope<TValue> $operation
     *
     * @return OperationResult<TOutcome>
     */
    public function handle(OperationEnvelope $operation): OperationResult;
}
