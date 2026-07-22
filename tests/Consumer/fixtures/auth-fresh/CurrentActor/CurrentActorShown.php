<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\CurrentActor;

use BlackOps\Core\Outcome;

final readonly class CurrentActorShown implements Outcome
{
    public function __construct(
        public ?string $id,
        public ?string $type,
    ) {}
}
