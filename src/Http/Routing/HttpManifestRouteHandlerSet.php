<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpManifestRouteHandlerSet
{
    /**
     * @param array<string, array<string, string>> $routes
     * @param array<string, array<string, string>> $operations
     *
     * @return array<string, true>
     */
    public function extract(array $routes, array $operations): array
    {
        $handlers = [];

        foreach ($routes as $method => $paths) {
            if (preg_match('/^[A-Z]+$/', $method) !== 1) {
                throw new InvalidArgumentException('HTTP manifest route method is invalid.');
            }

            foreach ($paths as $path => $typeId) {
                if (!str_starts_with($path, '/') || !array_key_exists($typeId, $operations)) {
                    throw new InvalidArgumentException('HTTP manifest route metadata is invalid.');
                }

                if (array_key_exists($typeId, $handlers)) {
                    throw new InvalidArgumentException('HTTP manifest operation must not define multiple routes.');
                }

                $handlers[$typeId] = true;
            }
        }

        return $handlers;
    }
}
