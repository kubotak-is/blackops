<?php

declare(strict_types=1);

namespace App\UserInterface\Console;

use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Core\ActorRef;

final readonly class SampleConsoleActorProvider implements ConsoleActorProvider
{
    public function actor(): ?ActorRef
    {
        return new ActorRef('quickstart-console', 'user');
    }
}
