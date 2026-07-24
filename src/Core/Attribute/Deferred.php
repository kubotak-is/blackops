<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Deferred {}
