<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpRouteRegistry
{
    /**
     * @var array<string, HttpOperationRoute>
     */
    private array $routes;

    /**
     * @param iterable<HttpOperationRoute> $routes
     */
    public function __construct(iterable $routes)
    {
        $indexed = [];

        foreach ($routes as $route) {
            if (array_key_exists($route->key(), $indexed)) {
                throw new InvalidArgumentException('HTTP route registry requires unique method and path pairs.');
            }

            $indexed[$route->key()] = $route;
        }

        $this->routes = $indexed;
    }

    public function match(string $method, string $path): ?HttpOperationRoute
    {
        return $this->routes[strtoupper($method) . ' ' . $path] ?? null;
    }
}
