<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use InvalidArgumentException;
use UnexpectedValueException;

final readonly class HttpRouteRegistry
{
    /**
     * @var array<string, HttpOperationRoute>
     */
    private array $routes;

    private Dispatcher $dispatcher;

    /**
     * @param iterable<array-key, HttpOperationRoute> $routes
     * @param array{
     *     0: array<string, array<string, string>>,
     *     1: array<string, list<array{
     *         regex: string,
     *         routeMap: array<int, array{0: string, 1: array<string, string>}>
     *     }>>
     * }|null $dispatcherData
     */
    public function __construct(iterable $routes, ?array $dispatcherData = null)
    {
        $byHandler = [];
        $routeDefinitions = [];
        $routeKeys = [];

        foreach ($routes as $handler => $route) {
            if (array_key_exists($route->key(), $routeKeys)) {
                throw new InvalidArgumentException('HTTP route registry requires unique method and path pairs.');
            }

            $handler = is_string($handler) ? $handler : $route->key();

            if (array_key_exists($handler, $byHandler)) {
                throw new InvalidArgumentException('HTTP route registry requires unique dispatcher handlers.');
            }

            $routeKeys[$route->key()] = true;
            $byHandler[$handler] = $route;
            $routeDefinitions[strtoupper($route->method)][$route->path] = $handler;
        }

        $this->routes = $byHandler;
        $this->dispatcher = new GroupCountBased(
            $dispatcherData ?? new FastRouteDispatcherDataCompiler()->compile($routeDefinitions),
        );
    }

    public function match(string $method, string $path): ?HttpRouteMatch
    {
        $result = $this->dispatcher->dispatch(strtoupper($method), $path);

        if (($result[0] ?? null) !== Dispatcher::FOUND) {
            return null;
        }

        if (
            !array_key_exists(1, $result)
            || !is_string($result[1])
            || !array_key_exists($result[1], $this->routes)
            || !array_key_exists(2, $result)
            || !is_array($result[2])
        ) {
            throw new UnexpectedValueException('FastRoute dispatcher returned invalid route data.');
        }

        return new HttpRouteMatch($this->routes[$result[1]], $this->pathParameters($result[2]));
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<string, string>
     */
    private function pathParameters(array $parameters): array
    {
        $result = [];

        foreach (array_keys($parameters) as $name) {
            if (!is_string($name) || !is_string($parameters[$name])) {
                throw new UnexpectedValueException('FastRoute dispatcher returned invalid path parameters.');
            }

            $result[$name] = rawurldecode($parameters[$name]);
        }

        return $result;
    }
}
