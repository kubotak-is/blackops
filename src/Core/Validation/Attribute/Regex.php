<?php

declare(strict_types=1);

namespace BlackOps\Core\Validation\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[PublicApi]
final readonly class Regex
{
    public function __construct(
        public string $pattern,
    ) {
        if ($pattern === '') {
            throw new InvalidArgumentException('Regex requires a non-empty pattern.');
        }

        set_error_handler(static fn(): bool => true);
        try {
            $valid = preg_match($pattern, subject: '');
        } finally {
            restore_error_handler();
        }

        if ($valid === false) {
            throw new InvalidArgumentException('Regex requires a valid PCRE pattern.');
        }
    }
}
