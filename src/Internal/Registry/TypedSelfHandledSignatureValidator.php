<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

final readonly class TypedSelfHandledSignatureValidator
{
    public function __construct(
        private TypedSelfHandledParameterValidator $parameters = new TypedSelfHandledParameterValidator(),
    ) {}

    /**
     * @param class-string $definition
     * @param class-string<OperationValue> $value
     */
    public function validate(string $definition, string $value): bool
    {
        $reflection = new ReflectionClass($definition);

        if (!$reflection->isInstantiable()) {
            $this->invalid($definition, 'must be instantiable');
        }

        if (!$reflection->hasMethod('handle')) {
            $this->invalid($definition, 'requires a handle method');
        }

        $method = $reflection->getMethod('handle');
        if (!$method->isPublic() || $method->isStatic()) {
            $this->invalid($definition, 'handle must be public and non-static');
        }

        $parameters = $method->getParameters();
        if (!in_array(count($parameters), [1, 2], strict: true)) {
            $this->invalid($definition, 'handle must declare one or two parameters');
        }

        $withContext = count($parameters) === 2;
        $this->parameters->validateValue($definition, $parameters[0], $value);
        if ($withContext) {
            $this->parameters->validateContext($definition, $parameters[1]);
        }

        $return = $method->getReturnType();
        if (
            !$return instanceof ReflectionNamedType
            || $return->isBuiltin()
            || $return->allowsNull()
            || $return->getName() !== OperationResult::class
        ) {
            $this->invalid($definition, 'handle return type must be OperationResult');
        }

        return $withContext;
    }

    private function invalid(string $definition, string $responsibility): never
    {
        throw new InvalidArgumentException("Typed self-handled operation {$definition} {$responsibility}.");
    }
}
