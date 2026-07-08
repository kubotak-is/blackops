<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpRouteRegistry
{
    /**
     * @var array<string, HttpOperationRoute>
     */
    private array $staticRoutes;

    /**
     * @var list<array{method: string, pattern: HttpPathPattern, route: HttpOperationRoute}>
     */
    private array $dynamicRoutes;

    /**
     * @param iterable<HttpOperationRoute> $routes
     */
    public function __construct(iterable $routes)
    {
        $staticRoutes = [];
        $dynamicRoutes = [];

        foreach ($routes as $route) {
            if (array_key_exists($route->key(), $staticRoutes)) {
                throw new InvalidArgumentException('HTTP route registry requires unique method and path pairs.');
            }

            if (str_contains($route->path, '{')) {
                $dynamicRoutes[] = [
                    'method' => strtoupper($route->method),
                    'pattern' => new HttpPathPattern($route->path),
                    'route' => $route,
                ];
                continue;
            }

            $staticRoutes[$route->key()] = $route;
        }

        $this->staticRoutes = $staticRoutes;
        $this->dynamicRoutes = $dynamicRoutes;
    }

    public function match(string $method, string $path): ?HttpRouteMatch
    {
        $normalized = strtoupper($method);
        $route = $this->staticRoutes[$normalized . ' ' . $path] ?? null;

        if ($route !== null) {
            return new HttpRouteMatch($route, []);
        }

        foreach ($this->dynamicRoutes as $candidate) {
            if ($candidate['method'] !== $normalized) {
                continue;
            }

            $pathParameters = $candidate['pattern']->match($path);

            if ($pathParameters === null) {
                continue;
            }

            return new HttpRouteMatch($candidate['route'], $pathParameters);
        }

        return null;
    }
}
