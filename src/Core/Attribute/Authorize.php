<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use BlackOps\Core\Authorization\AuthorizationPolicy;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Authorize
{
    /** @param class-string<AuthorizationPolicy> $policy */
    public function __construct(
        public string $policy,
    ) {}
}
