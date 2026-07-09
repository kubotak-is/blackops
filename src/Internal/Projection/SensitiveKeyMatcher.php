<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection;

final readonly class SensitiveKeyMatcher
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(
        private array $patterns = ['password', 'token', 'secret'],
    ) {}

    public function matches(string $key): bool
    {
        $normalized = strtolower($key);

        foreach ($this->patterns as $pattern) {
            if (str_contains($normalized, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
