<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;
use ReflectionNamedType;
use ReflectionType;

final readonly class OperationValueArgumentCoercer
{
    public function coerce(?ReflectionType $type, mixed $value): mixed
    {
        if (!$type instanceof ReflectionNamedType) {
            throw new OperationCodecException('Operation value constructor parameters must use named types.');
        }

        return match ($type->getName()) {
            'string' => is_string($value) ? $value : $this->typeMismatch(),
            'int' => is_int($value) ? $value : $this->typeMismatch(),
            'float' => is_float($value) || is_int($value) ? (float) $value : $this->typeMismatch(),
            'bool' => is_bool($value) ? $value : $this->typeMismatch(),
            'array' => is_array($value) ? $value : $this->typeMismatch(),
            default => throw new OperationCodecException(
                'Operation value constructor parameter type is not supported by the JSON codec.',
            ),
        };
    }

    private function typeMismatch(): never
    {
        throw new OperationCodecException('Encoded payload value type does not match the OperationValue constructor.');
    }
}
