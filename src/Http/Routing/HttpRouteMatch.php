<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

final readonly class HttpRouteMatch
{
    /**
     * @param array<string, string> $pathParameters
     */
    public function __construct(
        public HttpOperationRoute $route,
        public array $pathParameters,
    ) {}
}
