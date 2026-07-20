<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Outcome;
use BlackOps\Outcome\Internal\StructuredOutcomeCompiler;
use BlackOps\Outcome\Internal\StructuredOutcomeField;
use BlackOps\Outcome\Internal\StructuredOutcomeShape;
use InvalidArgumentException;

final readonly class FrontendOutcomeContractCompiler
{
    public function __construct(
        private StructuredOutcomeCompiler $outcomes = new StructuredOutcomeCompiler(),
    ) {}

    /** @param class-string<Outcome> $outcome */
    public function compile(string $outcome): FrontendOutcomeContract
    {
        if ($outcome === EmptyOutcome::class) {
            return new FrontendOutcomeContract($outcome, 'void', []);
        }

        $shape = $this->outcomes->compile($outcome);
        $this->assertNotSensitive($shape, $outcome);
        $this->assertUniqueDtoNames($shape);
        $fields = array_values(array_map($this->field(...), $shape->fields));

        return new FrontendOutcomeContract($outcome, 'outcome', $fields);
    }

    private function field(StructuredOutcomeField $field): FrontendOutcomeFieldContract
    {
        return new FrontendOutcomeFieldContract($field->name, $this->type($field));
    }

    private function type(StructuredOutcomeField $field): FrontendOutcomeTypeContract
    {
        if (in_array($field->kind, ['string', 'integer', 'float', 'boolean'], strict: true)) {
            return new FrontendOutcomeTypeContract('scalar', $field->nullable, $field->kind);
        }

        $dto = $field->dto ?? throw new InvalidArgumentException('Frontend outcome DTO contract is invalid.');
        $nested = array_values(array_map($this->field(...), $dto->fields));

        return new FrontendOutcomeTypeContract($field->kind, $field->nullable, class: $dto->class, fields: $nested);
    }

    private function assertNotSensitive(StructuredOutcomeShape $shape, string $path): void
    {
        foreach ($shape->fields as $field) {
            if ($field->sensitive) {
                throw new InvalidArgumentException(sprintf(
                    'Frontend contract does not permit sensitive outcome field %s.%s.',
                    $path,
                    $field->name,
                ));
            }
            if ($field->dto !== null) {
                $this->assertNotSensitive($field->dto, $path . '.' . $field->name);
            }
        }
    }

    /** @param array<string, class-string> $names */
    private function assertUniqueDtoNames(StructuredOutcomeShape $shape, array &$names = []): void
    {
        foreach ($shape->fields as $field) {
            if ($field->dto === null) {
                continue;
            }

            $class = $field->dto->class;
            $separator = strrpos(haystack: $class, needle: '\\');
            $shortName = $separator === false ? $class : substr($class, $separator + 1);
            $key = strtolower($shortName);
            if (array_key_exists($key, $names) && $names[$key] !== $class) {
                throw new InvalidArgumentException(sprintf(
                    'Frontend outcome DTO short class name %s is ambiguous.',
                    $shortName,
                ));
            }

            $names[$key] = $class;
            $this->assertUniqueDtoNames($field->dto, $names);
        }
    }
}
