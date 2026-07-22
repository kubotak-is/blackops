<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use BlackOps\Core\EphemeralOutcome;
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
        $definitionList = is_array($definitions)
            ? array_values($definitions)
            : iterator_to_array($definitions, preserve_keys: false);

        return $this->compileManifest($definitionList)->toRegistry($definitionList);
    }

    /**
     * @param iterable<Operation|class-string<Operation>> $definitions
     */
    public function compileManifest(iterable $definitions): HttpOperationManifest
    {
        $routes = [];
        $operations = [];

        foreach ($definitions as $definition) {
            $definitionClass = is_string($definition) ? $definition : $definition::class;
            $metadata = $this->operations->findByDefinition($definitionClass);

            if ($metadata === null) {
                throw new InvalidArgumentException('HTTP operation definition is not registered.');
            }

            $route = $this->route($definitionClass);

            if ($route !== null) {
                if ($this->collidesWithOperationStatus($route)) {
                    throw new InvalidArgumentException('HTTP route conflicts with a framework reserved resource.');
                }

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
                'ephemeral' => is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true),
            ];
        }

        return new HttpOperationManifest($routes, $operations, $this->dispatcherData->compile($routes));
    }

    /** @param class-string<Operation> $definition */
    private function route(string $definition): ?Route
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

    private function collidesWithOperationStatus(Route $route): bool
    {
        return $route->method === 'GET' && preg_match('#^/operations/[^/]+$#', $route->path) === 1;
    }
}
