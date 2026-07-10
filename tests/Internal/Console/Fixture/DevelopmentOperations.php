<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console\Fixture;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\Route;

#[Route('GET', '/discovered-inline')]
#[OperationType('development.inline')]
#[Accepts(DevelopmentValue::class)]
#[HandledBy(DevelopmentHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class DevelopmentInlineOperation implements Operation {}

#[Route('POST', '/discovered-deferred')]
#[OperationType('development.deferred')]
#[Accepts(DevelopmentValue::class)]
#[HandledBy(DevelopmentHandler::class)]
#[Returns(EmptyOutcome::class)]
#[ExecuteWith(Deferred::class)]
final readonly class DevelopmentDeferredOperation implements Operation {}

final readonly class DevelopmentValue implements OperationValue {}

final readonly class DevelopmentHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}
