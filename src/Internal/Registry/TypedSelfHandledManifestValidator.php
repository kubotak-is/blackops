<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use InvalidArgumentException;
use ReflectionClass;

final readonly class TypedSelfHandledManifestValidator
{
    public function __construct(
        private TypedSelfHandledSignatureValidator $signatures = new TypedSelfHandledSignatureValidator(),
    ) {}

    /**
     * @param class-string $definition
     * @param class-string<OperationValue> $value
     * @param class-string<Outcome> $outcome
     * @return array{value: class-string<OperationValue>, outcome: class-string<Outcome>, context: bool, mode: 'result'|'outcome'|'void'}
     */
    public function validate(string $definition, string $value, string $outcome): array
    {
        $signature = $this->signatures->inspect($definition);
        if ($signature['value'] !== $value) {
            throw new InvalidArgumentException(
                'Typed self-handled operation handle value must match the accepted OperationValue.',
            );
        }

        $expectedOutcome = $signature['outcome'];
        if ($signature['mode'] === 'result') {
            $returns = new ReflectionClass($definition)->getAttributes(Returns::class);
            if (count($returns) !== 1) {
                throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
            }
            $expectedOutcome = $returns[0]->newInstance()->outcome;
        }

        if ($expectedOutcome !== $outcome) {
            throw new InvalidArgumentException('Operation manifest handler invocation metadata is invalid.');
        }

        return $signature;
    }
}
