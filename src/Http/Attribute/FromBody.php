<?php

declare(strict_types=1);

namespace BlackOps\Http\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
#[PublicApi]
final readonly class FromBody
{
    public function __construct(
        public ?string $name = null,
    ) {}
}
