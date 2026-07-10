<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

final readonly class PhpTokenClassParser
{
    public function __construct(
        private PhpTokenNamespaceParser $namespaces = new PhpTokenNamespaceParser(),
        private PhpTokenClassDeclarationParser $classes = new PhpTokenClassDeclarationParser(),
    ) {}

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return list<class-string>
     */
    public function parse(array $tokens): array
    {
        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->namespaces->at($tokens, $index + 1);
                continue;
            }

            if ($token[0] !== T_CLASS) {
                continue;
            }

            $name = $this->classes->at($tokens, $index);

            if ($name === null) {
                continue;
            }

            $class = $namespace === '' ? $name : $namespace . '\\' . $name;
            $classes[$class] = true;
        }

        $result = array_keys($classes);
        sort($result);

        return $result;
    }
}
