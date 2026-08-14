<?php

declare(strict_types=1);

namespace App\Feature\Scheduled\DeferredProbe;

use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\ScheduledBy;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use LogicException;

#[OperationType('scheduled.deferred.probe')]
#[ScheduledBy(name: 'consumer.deferred', cron: '* * * * *', timezone: 'UTC')]
#[Deferred]
final readonly class ScheduledDeferredProbe implements Operation
{
    public function handle(ScheduledDeferredProbeValue $value, ExecutionContext $context): ScheduledDeferredProbeOutcome
    {
        if ($context->attempt() === null) {
            throw new LogicException('Deferred scheduled probe requires an attempt.');
        }

        return new ScheduledDeferredProbeOutcome('deferred');
    }
}
