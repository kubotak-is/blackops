<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

final readonly class SuperglobalServerValue
{
    private function __construct() {}

    public static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
