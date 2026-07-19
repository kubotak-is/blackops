<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Outcome;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

final readonly class FrontendOutcomeContractCompiler
{
    public function __construct(
        private FrontendScalarTypeCompiler $types = new FrontendScalarTypeCompiler(),
    ) {}

    /** @param class-string<Outcome> $outcome */
    public function compile(string $outcome): FrontendOutcomeContract
    {
        if ($outcome === EmptyOutcome::class) {
            return new FrontendOutcomeContract($outcome, 'void', []);
        }

        $reflection = new ReflectionClass($outcome);
        $properties = array_values(array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn(ReflectionProperty $property): bool => !$property->isStatic(),
        ));
        usort($properties, static fn(ReflectionProperty $left, ReflectionProperty $right): int => strcmp(
            $left->getName(),
            $right->getName(),
        ));

        $fields = [];
        foreach ($properties as $property) {
            if ($property->getAttributes(Sensitive::class) !== []) {
                throw new InvalidArgumentException('Frontend contract does not permit sensitive outcome fields.');
            }
            [$type, $nullable] = $this->types->compile($property->getType());
            $fields[] = new FrontendOutcomeFieldContract($property->getName(), $type, $nullable);
        }

        return new FrontendOutcomeContract($outcome, 'outcome', $fields);
    }
}
