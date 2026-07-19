<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendValidationContract
{
    /** @param array<string, bool|float|int|string|list<bool|float|int|string>> $parameters */
    public function __construct(
        public string $rule,
        public string $code,
        public array $parameters,
    ) {}
}
