<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;

final readonly class OperationValueOutcomeCompiler
{
    public function __construct(
        private OperationHandlerMetadataCompiler $handlers,
    ) {}

    /**
     * @param ReflectionClass<Operation> $definition
     * @param list<ReflectionAttribute<Accepts>> $accepts
     * @param list<ReflectionAttribute<Returns>> $returns
     * @param list<ReflectionAttribute<HandledBy>> $handledBy
     * @return array{class-string<OperationValue>, class-string<Outcome>}
     */
    public function compile(ReflectionClass $definition, array $accepts, array $returns, array $handledBy): array
    {
        if (count($accepts) > 1 || count($returns) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat Accepts or Returns.');
        }

        if ($definition->implementsInterface(OperationHandler::class) || $handledBy !== []) {
            return $this->requiredAttributes($accepts, $returns);
        }

        $signature = $this->handlers->signature($definition);
        if ($signature['mode'] === 'result' && (count($accepts) !== 1 || count($returns) !== 1)) {
            throw new InvalidArgumentException(
                'OperationResult compatibility mode requires Accepts and Returns exactly once.',
            );
        }

        $value = $accepts === [] ? $signature['value'] : $accepts[0]->newInstance()->value;
        $outcome = $returns === [] ? $signature['outcome'] : $returns[0]->newInstance()->outcome;
        if ($signature['value'] !== $value || $signature['mode'] !== 'result' && $signature['outcome'] !== $outcome) {
            throw new InvalidArgumentException('Typed self-handled signature does not match operation metadata.');
        }

        return [$value, $outcome];
    }

    /**
     * @param list<ReflectionAttribute<Accepts>> $accepts
     * @param list<ReflectionAttribute<Returns>> $returns
     * @return array{class-string<OperationValue>, class-string<Outcome>}
     */
    private function requiredAttributes(array $accepts, array $returns): array
    {
        if (count($accepts) !== 1 || count($returns) !== 1) {
            throw new InvalidArgumentException(
                'Legacy and separate operations require Accepts and Returns exactly once.',
            );
        }

        return [$accepts[0]->newInstance()->value, $returns[0]->newInstance()->outcome];
    }
}
