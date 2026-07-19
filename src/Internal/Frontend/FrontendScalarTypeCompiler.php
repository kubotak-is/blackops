<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use InvalidArgumentException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final readonly class FrontendScalarTypeCompiler
{
    /** @return array{string, bool} */
    public function compile(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$this->named($type), $type->allowsNull()];
        }

        if ($type instanceof ReflectionUnionType) {
            $named = null;
            $hasNull = false;
            foreach ($type->getTypes() as $candidate) {
                if (!$candidate instanceof ReflectionNamedType) {
                    throw new InvalidArgumentException('Frontend contract does not support nested intersection types.');
                }
                if ($candidate->getName() === 'null') {
                    $hasNull = true;
                    continue;
                }
                if ($named !== null) {
                    throw new InvalidArgumentException('Frontend contract does not support scalar union types.');
                }
                $named = $candidate;
            }
            if ($named instanceof ReflectionNamedType && $hasNull) {
                return [$this->named($named), true];
            }
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw new InvalidArgumentException('Frontend contract does not support intersection types.');
        }

        throw new InvalidArgumentException('Frontend contract requires a supported scalar type.');
    }

    private function named(ReflectionNamedType $type): string
    {
        if (!$type->isBuiltin()) {
            throw new InvalidArgumentException('Frontend contract does not support object types.');
        }

        return match ($type->getName()) {
            'string' => 'string',
            'int' => 'integer',
            'float' => 'float',
            'bool' => 'boolean',
            default => throw new InvalidArgumentException('Frontend contract does not support this scalar type.'),
        };
    }
}
