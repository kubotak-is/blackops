<?php

declare(strict_types=1);

namespace BlackOps\Console;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface ConsoleActorProvider
{
    public function actor(): ?ActorRef;
}
