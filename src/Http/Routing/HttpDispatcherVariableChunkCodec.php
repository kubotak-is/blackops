<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpDispatcherVariableChunkCodec
{
    public function __construct(
        private HttpDispatcherRouteMapCodec $routeMaps = new HttpDispatcherRouteMapCodec(),
    ) {}

    /**
     * @return array{
     *     0: array{
     *         regex: string,
     *         routeMap: array<int, array{0: string, 1: array<string, string>}>
     *     },
     *     1: array<string, true>
     * }
     */
    public function decode(mixed $chunk): array
    {
        if (
            !is_array($chunk)
            || array_keys($chunk) !== ['regex', 'routeMap']
            || !is_string($chunk['regex'])
            || $chunk['regex'] === ''
            || !is_array($chunk['routeMap'])
            || $chunk['routeMap'] === []
        ) {
            throw new InvalidArgumentException('HTTP manifest dispatcher variable route chunk is invalid.');
        }

        [$routeMap, $handlers] = $this->routeMaps->decode($chunk['routeMap']);

        return [['regex' => $chunk['regex'], 'routeMap' => $routeMap], $handlers];
    }
}
