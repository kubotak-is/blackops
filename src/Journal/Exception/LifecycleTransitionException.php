<?php

declare(strict_types=1);

namespace BlackOps\Journal\Exception;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;

#[PublicApi]
final class LifecycleTransitionException extends \LogicException
{
    public static function invalid(?LifecycleState $current, JournalEvent $event): self
    {
        $state = $current === null ? 'initial' : $current->value;

        return new self("Cannot apply lifecycle event '{$event->value}' from '{$state}' state.");
    }
}
