<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyContract;

final readonly class FrameworkProxyOwnershipMarker
{
    public function __construct(
        public string $sourceClass,
        public FrameworkProxyOwnership $ownership,
        public FrameworkProxyProfile $profile,
        public bool $lifecycleOwned,
    ) {}
}
