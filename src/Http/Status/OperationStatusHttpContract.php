<?php

declare(strict_types=1);

namespace BlackOps\Http\Status;

final readonly class OperationStatusHttpContract
{
    public const int SCHEMA_VERSION = 1;
    public const string METHOD = 'GET';
    public const string PATH = '/operations/{operationId}';
    public const string TEMPLATE = self::PATH;
    public const int POLLING_HINT_SECONDS = 1;
    public const string CACHE_CONTROL = 'private, no-store';

    public static function method(): string
    {
        return self::METHOD;
    }

    public static function template(): string
    {
        return self::PATH;
    }

    public static function matches(string $method, string $path): bool
    {
        if ($method !== self::METHOD || !str_starts_with($path, self::pathPrefix())) {
            return false;
        }

        return !str_contains(substr(string: $path, offset: strlen(self::pathPrefix())), '/');
    }

    public static function pathPrefix(): string
    {
        $position = strpos(haystack: self::PATH, needle: '{');

        return $position === false ? self::PATH : substr(string: self::PATH, offset: 0, length: $position);
    }

    /**
     * Return the one collision identity shared by the fixed route and every
     * one-segment GET route below /operations/.
     */
    public static function collisionIdentity(string $method, string $path): ?string
    {
        if (!self::matches($method, $path)) {
            return null;
        }

        $suffix = substr(string: $path, offset: strlen(self::pathPrefix()));
        if ($suffix === '') {
            return null;
        }

        return self::METHOD . ' ' . self::PATH;
    }

    public static function collides(string $method, string $path): bool
    {
        return self::collisionIdentity($method, $path) !== null;
    }

    /** @return array{method: string, path: string, strategy: null, operationType: null, origin: 'framework_fixed'} */
    public static function descriptor(): array
    {
        return [
            'method' => self::METHOD,
            'path' => self::PATH,
            'strategy' => null,
            'operationType' => null,
            'origin' => 'framework_fixed',
        ];
    }
}
