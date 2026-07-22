<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use InvalidArgumentException;
use PhpToken;

final readonly class SeederGeneratorInput
{
    private const RESERVED_TYPE_NAMES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'parent',
        'resource',
        'self',
        'static',
        'string',
        'true',
        'void',
    ];

    /** @param non-empty-list<string> $segments */
    private function __construct(
        public array $segments,
    ) {}

    public static function from(string $name): self
    {
        if (preg_match('/[\\\\\x00-\x1F\x7F]/', $name) === 1) {
            throw new InvalidArgumentException(
                'Seeder name must use PascalCase segments separated by forward slashes.',
            );
        }

        $segments = explode('/', $name);
        if ($segments === []) {
            throw new InvalidArgumentException(
                'Seeder name must use PascalCase segments separated by forward slashes.',
            );
        }
        foreach ($segments as $segment) {
            if (!self::validSegment($segment)) {
                throw new InvalidArgumentException(
                    'Seeder name must use valid PascalCase PHP class names separated by forward slashes.',
                );
            }
        }

        return new self($segments);
    }

    private static function validSegment(string $segment): bool
    {
        if (
            preg_match('/^[A-Z][A-Za-z0-9]*$/D', $segment) !== 1
            || in_array(strtolower($segment), self::RESERVED_TYPE_NAMES, strict: true)
        ) {
            return false;
        }

        $token = PhpToken::tokenize('<?php class ' . $segment . ' {}')[3] ?? null;

        return $token instanceof PhpToken && $token->id === T_STRING && $token->text === $segment;
    }
}
