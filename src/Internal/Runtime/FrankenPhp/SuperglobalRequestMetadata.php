<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

use InvalidArgumentException;

final readonly class SuperglobalRequestMetadata
{
    private function __construct() {}

    /** @param array<array-key, mixed> $server */
    public static function method(array $server): string
    {
        $method = SuperglobalServerValue::string($server['REQUEST_METHOD'] ?? null);

        if ($method === null || $method === '') {
            throw new InvalidArgumentException('HTTP request method is missing.');
        }

        return $method;
    }

    /** @param array<array-key, mixed> $server */
    public static function protocolVersion(array $server): ?string
    {
        $protocol = SuperglobalServerValue::string($server['SERVER_PROTOCOL'] ?? null) ?? '';

        if (!str_starts_with($protocol, 'HTTP/')) {
            return null;
        }

        return substr($protocol, offset: 5);
    }
}
