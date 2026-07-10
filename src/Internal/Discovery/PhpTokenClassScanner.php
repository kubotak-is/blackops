<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class PhpTokenClassScanner
{
    public function __construct(
        private PhpTokenClassParser $classes = new PhpTokenClassParser(),
    ) {}

    /**
     * @return list<class-string>
     */
    public function scan(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('PHP token scan source file must be readable.');
        }

        $source = file_get_contents($path);

        if ($source === false) {
            throw new InvalidArgumentException('PHP token scan source file could not be read.');
        }

        return $this->classes->parse(token_get_all($source, TOKEN_PARSE));
    }
}
