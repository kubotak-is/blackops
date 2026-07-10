<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

final readonly class PhpTokenClassDeclarationParser
{
    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    public function at(array $tokens, int $offset): ?string
    {
        $previous = $this->previousSignificantToken($tokens, $offset - 1);

        if (is_array($previous) && in_array($previous[0], [T_NEW, T_DOUBLE_COLON], strict: true)) {
            return null;
        }

        return $this->nextIdentifier($tokens, $offset + 1);
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array{int, string, int}|string|null
     */
    private function previousSignificantToken(array $tokens, int $offset): array|string|null
    {
        for ($index = $offset; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], strict: true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function nextIdentifier(array $tokens, int $offset): ?string
    {
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], strict: true)) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }
}
