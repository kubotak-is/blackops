<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendValueFieldContract
{
    /** @param list<FrontendValidationContract> $validations */
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public bool $required,
        public string $source,
        public string $transportName,
        public bool $sensitive,
        public array $validations,
    ) {}
}
