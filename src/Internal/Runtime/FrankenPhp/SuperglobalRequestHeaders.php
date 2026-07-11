<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

final readonly class SuperglobalRequestHeaders
{
    private function __construct() {}

    /**
     * @param array<array-key, mixed> $server
     *
     * @return array<string, string>
     */
    public static function extract(array $server): array
    {
        $headers = [];

        foreach (array_keys($server) as $key) {
            if (!is_string($key)) {
                continue;
            }

            $value = SuperglobalServerValue::string($server[$key] ?? null);

            if ($value === null) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[self::name(substr($key, offset: 5))] = $value;
                continue;
            }

            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[self::name($key)] = $value;
            }
        }

        return $headers;
    }

    private static function name(string $serverName): string
    {
        $words = str_replace(search: '_', replace: ' ', subject: $serverName);

        return str_replace(search: ' ', replace: '-', subject: ucwords(strtolower($words)));
    }
}
