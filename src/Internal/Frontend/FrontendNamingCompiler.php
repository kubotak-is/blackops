<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use InvalidArgumentException;
use ReflectionClass;

final readonly class FrontendNamingCompiler
{
    /**
     * @param class-string $definition
     * @return array{string, string}
     */
    public function compile(string $definition, string $typeId): array
    {
        $export = new ReflectionClass($definition)->getShortName();
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $export) !== 1 || $this->reserved($export)) {
            throw new InvalidArgumentException('Frontend operation export name is not a valid identifier.');
        }

        $segments = explode('.', $typeId);
        if (!array_all($segments, static fn(string $segment): bool => preg_match('/^[a-z0-9]+$/', $segment) === 1)) {
            throw new InvalidArgumentException('Frontend operation type ID cannot form a module path.');
        }
        array_pop($segments);

        $file = preg_replace('/([a-z0-9])([A-Z])/', replacement: '$1-$2', subject: $export);
        $file = preg_replace('/([A-Z]+)([A-Z][a-z])/', replacement: '$1-$2', subject: $file ?? '');
        if (!is_string($file) || $file === '') {
            throw new InvalidArgumentException('Frontend operation module file name is invalid.');
        }

        $prefix = $segments === [] ? '' : implode('/', $segments) . '/';

        return [$export, 'operations/' . $prefix . strtolower($file) . '.ts'];
    }

    private function reserved(string $identifier): bool
    {
        return in_array(
            $identifier,
            [
                'await',
                'break',
                'case',
                'catch',
                'class',
                'const',
                'continue',
                'debugger',
                'default',
                'delete',
                'do',
                'else',
                'enum',
                'export',
                'extends',
                'false',
                'finally',
                'for',
                'function',
                'if',
                'implements',
                'import',
                'in',
                'instanceof',
                'interface',
                'let',
                'new',
                'null',
                'package',
                'private',
                'protected',
                'public',
                'return',
                'static',
                'super',
                'switch',
                'this',
                'throw',
                'true',
                'try',
                'typeof',
                'var',
                'void',
                'while',
                'with',
                'yield',
            ],
            strict: true,
        );
    }
}
