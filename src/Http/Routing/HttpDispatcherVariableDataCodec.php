<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpDispatcherVariableDataCodec
{
    public function __construct(
        private HttpDispatcherVariableChunkCodec $chunks = new HttpDispatcherVariableChunkCodec(),
    ) {}

    /**
     * @return array{
     *     0: array<string, list<array{
     *         regex: string,
     *         routeMap: array<int, array{0: string, 1: array<string, string>}>
     *     }>>,
     *     1: array<string, true>
     * }
     */
    public function decode(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('HTTP manifest dispatcher variable routes are invalid.');
        }

        $routes = [];
        $handlers = [];

        foreach (array_keys($data) as $method) {
            if (
                !is_string($method)
                || preg_match('/^[A-Z]+$/', $method) !== 1
                || !is_array($data[$method])
                || !array_is_list($data[$method])
            ) {
                throw new InvalidArgumentException('HTTP manifest dispatcher variable routes are invalid.');
            }

            foreach (array_keys($data[$method]) as $index) {
                [$chunk, $chunkHandlers] = $this->chunks->decode($data[$method][$index]);
                $routes[$method][] = $chunk;
                $handlers += $chunkHandlers;
            }
        }

        return [$routes, $handlers];
    }
}
