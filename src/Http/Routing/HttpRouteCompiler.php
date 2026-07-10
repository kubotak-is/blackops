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
        private FastRouteDispatcherDataCompiler $dispatcherData = new FastRouteDispatcherDataCompiler(),
    ) {}

    /**
     * @param iterable<Operation> $definitions
     */
    public function compile(iterable $definitions): HttpRouteRegistry
    {
        $definitionList = $this->definitionList($definitions);

        return $this->compileManifest($definitionList)->toRegistry($definitionList);
    }

    /**
     * @param iterable<Operation> $definitions
     */
    public function compileManifest(iterable $definitions): HttpOperationManifest
    {
        $routes = [];
        $operations = [];

        foreach ($definitions as $definition) {
            $metadata = $this->operations->findByDefinition($definition::class);

            if ($metadata === null) {
                throw new InvalidArgumentException('HTTP operation definition is not registered.');
            }

            $route = $this->route($definition);

            if ($route !== null) {
                if (
                    array_key_exists($route->method, $routes) && array_key_exists($route->path, $routes[$route->method])
                ) {
                    throw new InvalidArgumentException('HTTP route compilation requires unique method and path pairs.');
                }

                $routes[$route->method][$route->path] = $metadata->typeId;
            }

            $operations[$metadata->typeId] = [
                'definition' => $metadata->definition,
                'value' => $metadata->value,
                'handler' => $metadata->handler,
                'outcome' => $metadata->outcome,
                'strategy' => $metadata->strategy,
            ];
        }

        return new HttpOperationManifest($routes, $operations, $this->dispatcherData->compile($routes));
    }

    /**
     * @param iterable<Operation> $definitions
     *
     * @return list<Operation>
     */
    private function definitionList(iterable $definitions): array
    {
        if (is_array($definitions)) {
            return array_values($definitions);
        }

        return iterator_to_array($definitions, preserve_keys: false);
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
