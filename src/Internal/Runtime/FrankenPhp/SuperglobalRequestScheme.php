<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

final readonly class SuperglobalRequestScheme
{
    private function __construct() {}

    /** @param array<array-key, mixed> $server */
    public static function from(array $server): string
    {
        $https = SuperglobalServerValue::string($server['HTTPS'] ?? null) ?? '';

        if ($https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }

        if (
            SuperglobalServerValue::string($server['REQUEST_SCHEME'] ?? null) === 'https'
            || SuperglobalServerValue::string($server['SERVER_PORT'] ?? null) === '443'
        ) {
            return 'https';
        }

        return 'http';
    }
}
