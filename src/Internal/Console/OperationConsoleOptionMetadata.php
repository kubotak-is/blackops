<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

final readonly class OperationConsoleOptionMetadata
{
    /** @param 'string'|'int'|'float'|'bool' $type */
    public function __construct(
        public string $property,
        public string $name,
        public string $type,
        public bool $nullable,
        public bool $required,
        public string|int|float|bool|null $default,
    ) {}
}
