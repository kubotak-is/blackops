<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpDispatcherRouteMapCodec
{
    public function __construct(
        private HttpDispatcherVariableNamesCodec $variableNames = new HttpDispatcherVariableNamesCodec(),
    ) {}

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array{
     *     0: array<int, array{0: string, 1: array<string, string>}>,
     *     1: array<string, true>
     * }
     */
    public function decode(array $data): array
    {
        $routeMap = [];
        $handlers = [];

        foreach (array_keys($data) as $groupCount) {
            if (
                !is_int($groupCount)
                || $groupCount < 1
                || !is_array($data[$groupCount])
                || !array_is_list($data[$groupCount])
                || count($data[$groupCount]) !== 2
                || !is_string($data[$groupCount][0])
                || $data[$groupCount][0] === ''
                || !is_array($data[$groupCount][1])
            ) {
                throw new InvalidArgumentException('HTTP manifest dispatcher variable route map is invalid.');
            }

            $handler = $data[$groupCount][0];
            $routeMap[$groupCount] = [$handler, $this->variableNames->decode($data[$groupCount][1])];
            $handlers[$handler] = true;
        }

        return [$routeMap, $handlers];
    }
}
