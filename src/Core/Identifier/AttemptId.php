<?php

declare(strict_types=1);

namespace BlackOps\Core\Identifier;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class AttemptId
{
    use IdentifierBehavior;
}
