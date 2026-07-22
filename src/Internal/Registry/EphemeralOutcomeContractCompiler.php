<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\EphemeralOutcome;
use BlackOps\Outcome\Internal\StructuredOutcomeCompiler;
use BlackOps\Outcome\Internal\StructuredOutcomeShape;
use InvalidArgumentException;
use ReflectionClass;

final readonly class EphemeralOutcomeContractCompiler
{
    public function __construct(
        private StructuredOutcomeCompiler $outcomes = new StructuredOutcomeCompiler(),
    ) {}

    /** @param class-string<EphemeralOutcome> $outcome */
    public function compile(string $outcome): void
    {
        $reflection = new ReflectionClass($outcome);
        if (!$reflection->isInstantiable() || !$reflection->isFinal() || !$reflection->isReadOnly()) {
            throw new InvalidArgumentException(sprintf(
                'Ephemeral outcome %s must be a concrete final readonly class.',
                $outcome,
            ));
        }

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic() || $property->isStatic() || !$property->isPromoted()) {
                throw new InvalidArgumentException(sprintf(
                    'Ephemeral outcome property %s.%s must be public and constructor-promoted.',
                    $outcome,
                    $property->getName(),
                ));
            }
        }

        $shape = $this->outcomes->compile($outcome);
        $this->assertSensitiveKeys($shape, $outcome, true);
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    private function assertSensitiveKeys(StructuredOutcomeShape $shape, string $path, bool $root): void
    {
        foreach ($shape->fields as $field) {
            if ((!$root || $field->dto !== null) && $field->sensitive) {
                throw new InvalidArgumentException(sprintf(
                    'Ephemeral outcome nested field %s.%s must not be sensitive.',
                    $path,
                    $field->name,
                ));
            }
            if ($this->isReservedSensitiveKey($field->name) && !$field->sensitive) {
                throw new InvalidArgumentException(sprintf(
                    'Ephemeral outcome credential property %s.%s requires Sensitive.',
                    $path,
                    $field->name,
                ));
            }
            if ($field->dto !== null) {
                $this->assertSensitiveKeys($field->dto, $path . '.' . $field->name, false);
            }
        }
    }

    private function isReservedSensitiveKey(string $name): bool
    {
        $normalized = strtolower((string) preg_replace(pattern: '/[^a-z0-9]+/i', replacement: '', subject: $name));

        return (
            preg_match(
                '/(?:password|passwd|secret|token|credential|session|apikey|privatekey|bearer|jwt|claim|role|permission|cookie)/',
                $normalized,
            ) === 1
        );
    }
}
