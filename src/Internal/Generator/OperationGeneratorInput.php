<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

use BlackOps\Core\Attribute\OperationType;
use InvalidArgumentException;
use PhpToken;

final readonly class OperationGeneratorInput
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
        public string $feature,
        public string $action,
        public string $operationType,
    ) {}

    public static function from(string $path, string $operationType): self
    {
        if (preg_match('/[\\\\\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException(
                'Operation path must use exactly two PascalCase segments separated by a forward slash.',
            );
        }

        $segments = explode('/', $path);
        if (count($segments) !== 2) {
            throw new InvalidArgumentException(
                'Operation path must use exactly two PascalCase segments separated by a forward slash.',
            );
        }

        [$feature, $action] = $segments;
        self::validateSegment($feature, 'feature');
        self::validateSegment($action, 'action');
        new OperationType($operationType);

        return new self($feature, $action, $operationType);
    }

    private static function validateSegment(string $segment, string $label): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/D', $segment) !== 1 || !self::isPhpClassIdentifier($segment)) {
            throw new InvalidArgumentException(sprintf(
                'Operation %s must be a valid PascalCase PHP class name.',
                $label,
            ));
        }
    }

    private static function isPhpClassIdentifier(string $identifier): bool
    {
        if (in_array(strtolower($identifier), self::RESERVED_TYPE_NAMES, strict: true)) {
            return false;
        }

        $tokens = PhpToken::tokenize('<?php class ' . $identifier . ' {}');
        $token = $tokens[3] ?? null;

        return $token instanceof PhpToken && $token->id === T_STRING && $token->text === $identifier;
    }
}
