<?php

declare(strict_types=1);

namespace BlackOps\Http\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
#[PublicApi]
final readonly class Route
{
    public string $method;

    public function __construct(
        string $method,
        public string $path,
    ) {
        $normalized = strtoupper($method);

        if (!preg_match('/^[A-Z]+$/', $normalized)) {
            throw new InvalidArgumentException('HTTP route method must contain only letters.');
        }

        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('HTTP route path must start with a slash.');
        }

        $this->method = $normalized;
    }
}
