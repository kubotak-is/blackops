<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;

final readonly class OperationHandlerMetadataCompiler
{
    public function __construct(
        private TypedSelfHandledSignatureValidator $typedSignatures = new TypedSelfHandledSignatureValidator(),
    ) {}

    /**
     * @param ReflectionClass<Operation> $definition
     * @return array{value: class-string<OperationValue>, outcome: class-string<\BlackOps\Core\Outcome>, context: bool, mode: 'result'|'outcome'|'void'}
     */
    public function signature(ReflectionClass $definition): array
    {
        return $this->typedSignatures->inspect($definition->getName());
    }

    /**
     * @param ReflectionClass<Operation> $definition
     * @param list<ReflectionAttribute<HandledBy>> $attributes
     * @param class-string<OperationValue> $value
     * @param class-string<\BlackOps\Core\Outcome> $outcome
     * @return array{class-string, bool, bool, null|'result'|'outcome'|'void'}
     */
    public function compile(ReflectionClass $definition, array $attributes, string $value, string $outcome): array
    {
        $legacy = $definition->implementsInterface(OperationHandler::class);
        if ($legacy && $attributes !== []) {
            throw new InvalidArgumentException('Self-handled operation must not declare HandledBy.');
        }

        if ($legacy) {
            return [$definition->getName(), false, false, null];
        }

        if ($attributes === []) {
            $signature = $this->typedSignatures->inspect($definition->getName());
            if (
                $signature['value'] !== $value
                || $signature['mode'] !== 'result' && $signature['outcome'] !== $outcome
            ) {
                throw new InvalidArgumentException('Typed self-handled signature does not match operation metadata.');
            }

            return [$definition->getName(), true, $signature['context'], $signature['mode']];
        }

        if ($this->hasTypedSignature($definition, $value)) {
            throw new InvalidArgumentException('Typed self-handled operation must not declare HandledBy.');
        }

        $handler = $attributes[0]->newInstance()->handler;
        if (!is_a($handler, OperationHandler::class, allow_string: true)) {
            throw new InvalidArgumentException('Separate operation handler must implement OperationHandler.');
        }

        return [$handler, false, false, null];
    }

    /**
     * @param ReflectionClass<Operation> $definition
     * @param class-string<OperationValue> $value
     */
    private function hasTypedSignature(ReflectionClass $definition, string $value): bool
    {
        if (!$definition->hasMethod('handle')) {
            return false;
        }

        try {
            $this->typedSignatures->validate($definition->getName(), $value);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
