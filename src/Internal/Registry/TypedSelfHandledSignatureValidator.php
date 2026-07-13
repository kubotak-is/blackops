<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
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
        $signature = $this->inspect($definition);
        if ($signature['value'] !== $value) {
            $this->invalid($definition, 'handle value must match the accepted OperationValue');
        }

        return $signature['context'];
    }

    /**
     * @param class-string $definition
     * @return array{value: class-string<OperationValue>, outcome: class-string<Outcome>, context: bool, mode: 'result'|'outcome'|'void'}
     */
    public function inspect(string $definition): array
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
        $value = $this->parameters->valueClass($definition, $parameters[0]);
        if ($withContext) {
            $this->parameters->validateContext($definition, $parameters[1]);
        }

        $return = $method->getReturnType();
        if (!$return instanceof ReflectionNamedType || $return->allowsNull()) {
            $this->invalid($definition, 'handle return type must be a concrete Outcome or void');
        }

        $returnName = $return->getName();
        if ($return->isBuiltin()) {
            if ($returnName !== 'void') {
                $this->invalid($definition, 'handle return type must be a concrete Outcome or void');
            }

            return [
                'value' => $value,
                'outcome' => \BlackOps\Core\EmptyOutcome::class,
                'context' => $withContext,
                'mode' => 'void',
            ];
        }

        if ($returnName === OperationResult::class) {
            return [
                'value' => $value,
                'outcome' => \BlackOps\Core\EmptyOutcome::class,
                'context' => $withContext,
                'mode' => 'result',
            ];
        }

        if (!is_a($returnName, Outcome::class, allow_string: true)) {
            $this->invalid($definition, 'handle return type must implement Outcome');
        }

        $outcome = new ReflectionClass($returnName);
        if (!$outcome->isInstantiable()) {
            $this->invalid($definition, 'handle outcome type must be instantiable');
        }

        return ['value' => $value, 'outcome' => $returnName, 'context' => $withContext, 'mode' => 'outcome'];
    }

    private function invalid(string $definition, string $responsibility): never
    {
        throw new InvalidArgumentException("Typed self-handled operation {$definition} {$responsibility}.");
    }
}
