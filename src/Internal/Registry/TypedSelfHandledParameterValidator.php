<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationValue;
use InvalidArgumentException;
use ReflectionNamedType;
use ReflectionParameter;

final readonly class TypedSelfHandledParameterValidator
{
    /** @param class-string<OperationValue> $value */
    public function validateValue(string $definition, ReflectionParameter $parameter, string $value): void
    {
        $this->assertRequired($definition, $parameter, 'value');
        $type = $this->classType($definition, $parameter, 'value');
        if (!is_a($type, OperationValue::class, allow_string: true) || $type !== $value) {
            $this->invalid($definition, 'handle value must match the accepted OperationValue');
        }
    }

    public function validateContext(string $definition, ReflectionParameter $parameter): void
    {
        $this->assertRequired($definition, $parameter, 'context');
        if ($this->classType($definition, $parameter, 'context') !== ExecutionContext::class) {
            $this->invalid($definition, 'handle context must be ExecutionContext');
        }
    }

    private function assertRequired(string $definition, ReflectionParameter $parameter, string $role): void
    {
        if ($parameter->isOptional() || $parameter->isPassedByReference() || $parameter->isVariadic()) {
            $this->invalid($definition, "handle {$role} parameter must be required and passed by value");
        }
    }

    /** @return class-string */
    private function classType(string $definition, ReflectionParameter $parameter, string $role): string
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || $type->allowsNull()) {
            $this->invalid($definition, "handle {$role} parameter must have a non-null named class type");
        }

        return $type->getName();
    }

    private function invalid(string $definition, string $responsibility): never
    {
        throw new InvalidArgumentException("Typed self-handled operation {$definition} {$responsibility}.");
    }
}
