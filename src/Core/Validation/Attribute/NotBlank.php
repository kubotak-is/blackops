<?php

declare(strict_types=1);

namespace BlackOps\Core\Validation\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[PublicApi]
final readonly class NotBlank {}
