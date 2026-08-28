<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Route;

use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Status\OperationStatusHttpContract;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetProjection;
use BlackOps\Internal\Projection\BuildSet\ProjectionExecutionStrategyWire;
use InvalidArgumentException;

final readonly class RouteProjectionProducer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
        private ProjectionExecutionStrategyWire $strategyWire = new ProjectionExecutionStrategyWire(),
    ) {}

    public function produce(string $applicationBuildId, HttpOperationManifest $manifest): ProjectionBuildSetProjection
    {
        $records = [OperationStatusHttpContract::descriptor()];

        foreach ($manifest->routes as $method => $paths) {
            foreach ($paths as $path => $typeId) {
                if (OperationStatusHttpContract::collides($method, $path)) {
                    throw new InvalidArgumentException('HTTP route conflicts with a framework reserved resource.');
                }
                if (!is_string($typeId) || !array_key_exists($typeId, $manifest->operations)) {
                    throw new InvalidArgumentException('HTTP manifest route references an unknown operation.');
                }

                $operation = $manifest->operations[$typeId];
                $strategy = $operation['strategy'] ?? null;
                if (!is_string($strategy)) {
                    throw new InvalidArgumentException('HTTP manifest operation strategy is invalid.');
                }

                $records[] = [
                    'method' => $method,
                    'path' => $path,
                    'strategy' => $this->strategyWire->map($strategy),
                    'operationType' => $typeId,
                    'origin' => 'operation_manifest',
                ];
            }
        }

        return $this->codec->projection(
            kind: 'blackops_route_projection',
            applicationBuildId: $applicationBuildId,
            records: $records,
        );
    }
}
