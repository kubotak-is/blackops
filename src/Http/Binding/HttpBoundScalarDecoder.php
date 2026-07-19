<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use ReflectionNamedType;
use ReflectionParameter;

final readonly class HttpBoundScalarDecoder
{
    public function decode(ReflectionParameter $parameter, mixed $value): mixed
    {
        if (!is_string($value)) {
            throw OperationValueBindingException::type($parameter->getName());
        }

        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'string', 'mixed' => $value,
            'int' => $this->integer($parameter, $value),
            'float' => $this->float($parameter, $value),
            'bool' => $this->boolean($parameter, $value),
            default => $value,
        };
    }

    private function integer(ReflectionParameter $parameter, string $value): int
    {
        if (preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $value) !== 1) {
            throw OperationValueBindingException::type($parameter->getName());
        }

        $decoded = (int) $value;

        if ((string) $decoded !== $value) {
            throw OperationValueBindingException::type($parameter->getName());
        }

        return $decoded;
    }

    private function float(ReflectionParameter $parameter, string $value): float
    {
        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/D', $value) !== 1) {
            throw OperationValueBindingException::type($parameter->getName());
        }

        $decoded = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($decoded === false || !is_finite($decoded)) {
            throw OperationValueBindingException::type($parameter->getName());
        }

        return $decoded;
    }

    private function boolean(ReflectionParameter $parameter, string $value): bool
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => throw OperationValueBindingException::type($parameter->getName()),
        };
    }
}
