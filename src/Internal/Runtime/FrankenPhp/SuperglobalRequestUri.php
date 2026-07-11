<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

use InvalidArgumentException;

final readonly class SuperglobalRequestUri
{
    private function __construct() {}

    /** @param array<array-key, mixed> $server */
    public static function build(array $server): string
    {
        $authority =
            SuperglobalServerValue::string($server['HTTP_HOST'] ?? null) ?? SuperglobalServerValue::string(
                $server['SERVER_NAME'] ?? null,
            ) ?? 'localhost';
        $target = SuperglobalServerValue::string($server['REQUEST_URI'] ?? null) ?? '/';

        if ($authority === '' || $target === '') {
            throw new InvalidArgumentException('HTTP request URI is invalid.');
        }

        if ($target[0] !== '/') {
            $target = '/' . $target;
        }

        return SuperglobalRequestScheme::from($server) . '://' . $authority . $target;
    }
}
