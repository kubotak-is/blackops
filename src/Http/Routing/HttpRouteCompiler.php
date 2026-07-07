<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use BlackOps\Core\Operation;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Attribute\Route;
use InvalidArgumentException;
use ReflectionClass;

final readonly class HttpRouteCompiler
{
    public function __construct(
        private OperationRegistry $operations,
    ) {}

    /**
     * @param iterable<Operation> $definitions
     */
    public function compile(iterable $definitions): HttpRouteRegistry
    {
        $routes = [];

        foreach ($definitions as $definition) {
            $metadata = $this->operations->findByDefinition($definition::class);

            if ($metadata === null) {
                throw new InvalidArgumentException('HTTP operation definition is not registered.');
            }

            $route = $this->route($definition);

            if ($route !== null) {
                $routes[] = new HttpOperationRoute($route->method, $route->path, $definition, $metadata->value);
            }
        }

        return new HttpRouteRegistry($routes);
    }

    private function route(Operation $definition): ?Route
    {
        $attributes = new ReflectionClass($definition)->getAttributes(Route::class);

        if (count($attributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat HTTP Route.');
        }

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
