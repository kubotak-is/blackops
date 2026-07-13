<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final readonly class HttpBoundValueTypeMatcher
{
    public function matches(mixed $value, ?ReflectionType $type): bool
    {
        if ($type === null) {
            return true;
        }

        if ($value === null) {
            return $type->allowsNull();
        }

        if ($type instanceof ReflectionUnionType) {
            return array_any($type->getTypes(), fn(ReflectionType $nested): bool => $this->matches($value, $nested));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return false;
        }

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return $this->matchesNamed($value, $type);
    }

    private function matchesNamed(mixed $value, ReflectionNamedType $type): bool
    {
        if (!$type->isBuiltin()) {
            return false;
        }

        return match ($type->getName()) {
            'mixed' => true,
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_int($value) || is_float($value),
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'null' => false,
            default => false,
        };
    }
}
