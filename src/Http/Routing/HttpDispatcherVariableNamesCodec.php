<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

use InvalidArgumentException;

final readonly class HttpDispatcherVariableNamesCodec
{
    /**
     * @param array<array-key, mixed> $variables
     *
     * @return array<string, string>
     */
    public function decode(array $variables): array
    {
        $result = [];

        foreach (array_keys($variables) as $name) {
            if (!is_string($name) || !is_string($variables[$name]) || $name === '' || $name !== $variables[$name]) {
                throw new InvalidArgumentException('HTTP manifest dispatcher variable names are invalid.');
            }

            $result[$name] = $variables[$name];
        }

        return $result;
    }
}
