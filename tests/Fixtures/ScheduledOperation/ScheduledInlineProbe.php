<?php

declare(strict_types=1);

namespace App\Feature\Scheduled\InlineProbe;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\ScheduledBy;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;

#[OperationType('scheduled.inline.probe')]
#[ScheduledBy(name: 'consumer.inline', cron: '* * * * *', timezone: 'UTC')]
#[Transactional]
class ScheduledInlineProbe implements Operation
{
    public function handle(ScheduledInlineProbeValue $value): ScheduledInlineProbeOutcome
    {
        return new ScheduledInlineProbeOutcome('inline');
    }
}
