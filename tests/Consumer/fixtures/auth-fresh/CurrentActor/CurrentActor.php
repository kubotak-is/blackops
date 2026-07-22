<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\CurrentActor;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'GET', path: '/auth-probe/current')]
#[OperationType('auth.probe.current')]
final readonly class CurrentActor implements Operation
{
    public function handle(CurrentActorValue $value, ExecutionContext $context): CurrentActorShown
    {
        $actor = $context->actorContext()?->authorization();

        return new CurrentActorShown($actor?->id(), $actor?->type());
    }
}
