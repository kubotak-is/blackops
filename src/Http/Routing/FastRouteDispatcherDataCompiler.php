<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use FastRoute\BadRouteException;
use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedDataGenerator;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use InvalidArgumentException;

final readonly class FastRouteDispatcherDataCompiler
{
    public function __construct(
        private HttpDispatcherDataCodec $codec = new HttpDispatcherDataCodec(),
    ) {}

    /**
     * @param array<string, array<string, string>> $routes
     *
     * @return array{
     *     0: array<string, array<string, string>>,
     *     1: array<string, list<array{
     *         regex: string,
     *         routeMap: array<int, array{0: string, 1: array<string, string>}>
     *     }>>
     * }
     */
    public function compile(array $routes): array
    {
        $collector = new RouteCollector(new Std(), new GroupCountBasedDataGenerator());
        $handlers = [];

        try {
            foreach ($routes as $method => $paths) {
                foreach ($paths as $path => $handler) {
                    $collector->addRoute($method, $path, $handler);
                    $handlers[$handler] = true;
                }
            }
        } catch (BadRouteException $exception) {
            throw new InvalidArgumentException(
                'HTTP route compilation rejected a duplicate or conflicting route.',
                previous: $exception,
            );
        }

        return $this->codec->decode($collector->getData(), $handlers);
    }
}
