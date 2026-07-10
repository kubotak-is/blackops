<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

final readonly class PhpTokenNamespaceParser
{
    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    public function at(array $tokens, int $offset): string
    {
        $parts = [];

        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_string($token) && in_array($token, [';', '{'], strict: true)) {
                break;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], strict: true)) {
                $parts[] = $token[1];
            }
        }

        return trim(string: implode('', $parts), characters: '\\');
    }
}
