<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final readonly class SapiResponseHeaders
{
    private function __construct() {}

    public static function validate(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            if (!is_string($name)) {
                throw new RuntimeException('Refusing to emit a non-string HTTP response header name.');
            }

            self::assertName($name);

            foreach ($values as $value) {
                self::assertValue($value);
            }
        }
    }

    private static function assertName(string $name): void
    {
        if (preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name) !== 1) {
            throw new RuntimeException('Refusing to emit an invalid HTTP response header name.');
        }
    }

    private static function assertValue(string $value): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('Refusing to emit an invalid HTTP response header value.');
        }
    }
}
