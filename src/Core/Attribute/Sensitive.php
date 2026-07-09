<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Sensitive
{
    public function __construct(
        public SensitiveMode $mode = SensitiveMode::Omit,
    ) {}
}
