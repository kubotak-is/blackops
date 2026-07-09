<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Routing\HttpOperationManifest;
use Psr\Container\ContainerInterface;

final readonly class ProductionRuntimeArtifacts
{
    public function __construct(
        public OperationRegistry $operations,
        public HttpOperationManifest $http,
        public ContainerInterface $container,
    ) {}
}
