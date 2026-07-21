<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use InvalidArgumentException;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ConsoleCommand
{
    public function __construct(
        public string $name,
        public string $description = '',
    ) {
        if (
            $name === ''
            || trim($name) !== $name
            || str_contains($name, '|')
            || preg_match('/[\x00-\x20\x7f]/D', $name) === 1
            || preg_match('/^[^:]++(:[^:]++)*$/D', $name) !== 1
        ) {
            throw new InvalidArgumentException('Console command name must be a canonical non-empty name.');
        }
    }
}
