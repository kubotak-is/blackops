<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Registry\OperationManifestFile;
use InvalidArgumentException;

final readonly class ProductionRuntimeArtifactLoader
{
    public function __construct(
        private OperationManifestFile $operations = new OperationManifestFile(),
        private HttpOperationManifestFile $http = new HttpOperationManifestFile(),
        private RuntimeContainerArtifactLoader $containers = new RuntimeContainerArtifactLoader(),
    ) {}

    public function load(
        string $operationManifest,
        string $httpManifest,
        string $container,
        string $containerClass,
        string $containerNamespace = '',
    ): ProductionRuntimeArtifacts {
        $operations = $this->operations->loadArtifact($operationManifest);
        $http = $this->http->loadArtifact($httpManifest);

        if ($operations->applicationBuildId !== $http->applicationBuildId) {
            throw new InvalidArgumentException('Production runtime manifest application build IDs do not match.');
        }

        return new ProductionRuntimeArtifacts(
            $operations->operations,
            $http->manifest,
            $this->containers->load($container, $containerClass, $containerNamespace),
        );
    }
}
