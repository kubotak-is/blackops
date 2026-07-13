<?php

declare(strict_types=1);

namespace BlackOps\Core\Validation;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class Violation
{
    public function __construct(
        public string $field,
        public string $rule,
        public string $code,
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) !== 1) {
            throw new InvalidArgumentException('Validation violation requires a valid field name.');
        }

        if (preg_match('/^[a-z]+(?:_[a-z]+)*$/', $rule) !== 1) {
            throw new InvalidArgumentException('Validation violation requires a valid rule name.');
        }

        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $code) !== 1) {
            throw new InvalidArgumentException('Validation violation requires a valid stable code.');
        }
    }
}
