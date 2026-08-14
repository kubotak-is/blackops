<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyDefinition;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyMetadata;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipMarker;

final readonly class FrameworkProxyDefinitionBinding
{
    public function __construct(
        public string $serviceId,
        public string $sourceClass,
        public string $proxyClass,
        public FrameworkProxyMetadata $metadata,
        public FrameworkProxyOwnershipMarker $marker,
    ) {}
}
