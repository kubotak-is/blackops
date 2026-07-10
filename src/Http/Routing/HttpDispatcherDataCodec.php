<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpDispatcherDataCodec
{
    public function __construct(
        private HttpDispatcherStaticDataCodec $static = new HttpDispatcherStaticDataCodec(),
        private HttpDispatcherVariableDataCodec $variable = new HttpDispatcherVariableDataCodec(),
    ) {}

    /**
     * @param array<string, true> $routeHandlers
     *
     * @return array{
     *     0: array<string, array<string, string>>,
     *     1: array<string, list<array{
     *         regex: string,
     *         routeMap: array<int, array{0: string, 1: array<string, string>}>
     *     }>>
     * }
     */
    public function decode(mixed $data, array $routeHandlers): array
    {
        if (!is_array($data) || !array_is_list($data) || count($data) !== 2) {
            throw new InvalidArgumentException('HTTP manifest dispatcher data is missing or invalid.');
        }

        [$staticRoutes, $staticHandlers] = $this->static->decode($data[0]);
        [$variableRoutes, $variableHandlers] = $this->variable->decode($data[1]);
        $handlers = $staticHandlers + $variableHandlers;
        ksort($handlers);
        ksort($routeHandlers);

        if ($handlers !== $routeHandlers) {
            throw new InvalidArgumentException('HTTP manifest dispatcher routes do not match route metadata.');
        }

        return [$staticRoutes, $variableRoutes];
    }
}
