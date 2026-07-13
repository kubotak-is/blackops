<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use InvalidArgumentException;
use PhpToken;

final readonly class MigrationGeneratorInput
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

    private function __construct(
        public string $description,
    ) {}

    public static function from(string $description): self
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/D', $description) !== 1 || !self::isPhpIdentifier($description)) {
            throw new InvalidArgumentException('Migration description must be a valid PascalCase identifier.');
        }

        return new self($description);
    }

    private static function isPhpIdentifier(string $identifier): bool
    {
        if (in_array(strtolower($identifier), self::RESERVED_TYPE_NAMES, strict: true)) {
            return false;
        }

        $tokens = PhpToken::tokenize('<?php class ' . $identifier . ' {}');
        $token = $tokens[3] ?? null;

        return $token instanceof PhpToken && $token->id === T_STRING && $token->text === $identifier;
    }
}
