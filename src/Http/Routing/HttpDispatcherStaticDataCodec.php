<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpDispatcherStaticDataCodec
{
    /**
     * @return array{0: array<string, array<string, string>>, 1: array<string, true>}
     */
    public function decode(mixed $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('HTTP manifest dispatcher static routes are invalid.');
        }

        $routes = [];
        $handlers = [];

        foreach (array_keys($data) as $method) {
            if (!is_string($method) || preg_match('/^[A-Z]+$/', $method) !== 1 || !is_array($data[$method])) {
                throw new InvalidArgumentException('HTTP manifest dispatcher static routes are invalid.');
            }

            foreach (array_keys($data[$method]) as $path) {
                if (
                    !is_string($path)
                    || !str_starts_with($path, '/')
                    || !is_string($data[$method][$path])
                    || $data[$method][$path] === ''
                ) {
                    throw new InvalidArgumentException('HTTP manifest dispatcher static routes are invalid.');
                }

                $routes[$method][$path] = $data[$method][$path];
                $handlers[$data[$method][$path]] = true;
            }
        }

        return [$routes, $handlers];
    }
}
