<?php

declare(strict_types=1);

namespace BlackOps\Internal\Identifier;

use BlackOps\Identifier\Uuidv7Generator;
use InvalidArgumentException;

final readonly class ValidatedUuidv7Generator implements Uuidv7Generator
{
    public function __construct(
        private Uuidv7Generator $source,
    ) {}

    public function generate(): string
    {
        $value = $this->source->generate();

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value)) {
            throw new InvalidArgumentException('UUID generator returned an invalid value.');
        }

        return $value;
    }
}
