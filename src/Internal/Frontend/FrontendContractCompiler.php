<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Http\Routing\HttpOperationManifestArtifact;
use BlackOps\Internal\Registry\OperationManifestArtifact;
use InvalidArgumentException;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrontendContractCompiler
{
    public function __construct(
        private FrontendNamingCompiler $names = new FrontendNamingCompiler(),
        private FrontendValueContractCompiler $values = new FrontendValueContractCompiler(),
        private FrontendOutcomeContractCompiler $outcomes = new FrontendOutcomeContractCompiler(),
    ) {}

    public function compile(
        OperationManifestArtifact $operations,
        HttpOperationManifestArtifact $http,
    ): FrontendContractManifest {
        if ($operations->applicationBuildId !== $http->applicationBuildId) {
            throw new InvalidArgumentException('Frontend contract source artifacts have different build IDs.');
        }

        $routes = [];
        foreach ($http->manifest->routes as $method => $paths) {
            foreach ($paths as $path => $typeId) {
                if (array_key_exists($typeId, $routes)) {
                    throw new InvalidArgumentException('Frontend contract requires one route per operation.');
                }
                $routes[$typeId] = [$method, $path];
            }
        }
        ksort($routes);

        $contracts = [];
        foreach ($routes as $typeId => [$method, $path]) {
            $metadata = $operations->operations->findByTypeId($typeId);
            $httpMetadata = $http->manifest->operations[$typeId] ?? null;
            if (!$metadata instanceof OperationMetadata || !is_array($httpMetadata)) {
                throw new InvalidArgumentException('Frontend contract route references unknown operation metadata.');
            }
            $this->assertMetadata($metadata, $httpMetadata);
            [$export, $module] = $this->names->compile($metadata->definition, $metadata->typeId);

            $contracts[] = new FrontendOperationContract(
                $metadata->typeId,
                $metadata->definition,
                $export,
                $module,
                strtoupper($method),
                $path,
                $this->strategy($metadata->strategy),
                $this->values->compile($metadata->value, $method, $path),
                $this->outcomes->compile($metadata->outcome),
            );
        }

        $this->assertNames($contracts);

        return new FrontendContractManifest($contracts);
    }

    /** @param array<string, string> $http */
    private function assertMetadata(OperationMetadata $operation, array $http): void
    {
        if (
            ($http['definition'] ?? null) !== $operation->definition
            || ($http['value'] ?? null) !== $operation->value
            || ($http['handler'] ?? null) !== $operation->handler
            || ($http['outcome'] ?? null) !== $operation->outcome
            || ($http['strategy'] ?? null) !== $operation->strategy
        ) {
            throw new InvalidArgumentException('Frontend contract operation and HTTP metadata do not match.');
        }
    }

    private function strategy(string $strategy): string
    {
        return match ($strategy) {
            Inline::class => 'inline',
            Deferred::class => 'deferred',
            default => throw new InvalidArgumentException('Frontend contract execution strategy is not supported.'),
        };
    }

    /** @param list<FrontendOperationContract> $operations */
    private function assertNames(array $operations): void
    {
        $modules = [];
        $exports = [];
        foreach ($operations as $operation) {
            $module = strtolower($operation->module);
            $export = strtolower($operation->exportName);
            if (array_key_exists($module, $modules) || array_key_exists($export, $exports)) {
                throw new InvalidArgumentException('Frontend operation module or export name collides.');
            }
            $modules[$module] = true;
            $exports[$export] = true;
        }
    }
}
