<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Outcome\Internal\StructuredOutcomeCompiler;
use BlackOps\Outcome\Internal\StructuredOutcomeShape;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class OperationConsoleMetadataCompiler
{
    public function __construct(
        private StructuredOutcomeCompiler $outcomes = new StructuredOutcomeCompiler(),
    ) {}

    /** @return list<OperationConsoleCommandMetadata> */
    public function compile(OperationRegistry $registry): array
    {
        $commands = [];
        foreach ($registry->all() as $metadata) {
            $command = $this->compileOperation($metadata);
            if ($command !== null) {
                $commands[] = $command;
            }
        }

        usort(
            $commands,
            static fn($left, $right): int => [$left->name, $left->typeId] <=> [$right->name, $right->typeId],
        );
        $names = [];
        foreach ($commands as $command) {
            if (array_key_exists($command->name, $names)) {
                throw new InvalidArgumentException('Operation console command name is duplicated.');
            }
            $names[$command->name] = true;
        }

        return $commands;
    }

    private function compileOperation(OperationMetadata $metadata): ?OperationConsoleCommandMetadata
    {
        $definition = new ReflectionClass($metadata->definition);
        $attributes = $definition->getAttributes(ConsoleCommand::class);
        if ($attributes === []) {
            return null;
        }
        if (count($attributes) !== 1) {
            throw new InvalidArgumentException('Operation definition must not repeat ConsoleCommand.');
        }
        $attribute = $attributes[0]->newInstance();
        $options = $this->compileOptions($metadata->value);
        $this->assertOutcomeNotSensitive($metadata->outcome);

        return new OperationConsoleCommandMetadata(
            $metadata->typeId,
            $metadata->definition,
            $metadata->value,
            $metadata->outcome,
            $metadata->strategy,
            $attribute->name,
            $attribute->description,
            $options,
        );
    }

    /**
     * @param class-string $value
     * @return list<OperationConsoleOptionMetadata>
     */
    private function compileOptions(string $value): array
    {
        $reflection = new ReflectionClass($value);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];
        $public = [];
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic() || $property->isStatic() || !$property->isPromoted()) {
                throw new InvalidArgumentException(
                    'Console operation value must contain only public promoted properties.',
                );
            }
            if ($property->getAttributes(Sensitive::class) !== []) {
                throw new InvalidArgumentException('Console operation value must not contain sensitive properties.');
            }
            $public[$property->getName()] = true;
        }

        /** @var list<OperationConsoleOptionMetadata> $options */
        $options = [];
        $names = ['json' => true];
        foreach ($parameters as $parameter) {
            if (
                !$parameter->isPromoted()
                || !array_key_exists($parameter->getName(), $public)
                || $parameter->isVariadic()
            ) {
                throw new InvalidArgumentException(
                    'Console operation value constructor must match its promoted properties.',
                );
            }
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
                throw new InvalidArgumentException('Console operation value option type is unsupported.');
            }
            $typeName = $type->getName();
            if (!in_array($typeName, ['string', 'int', 'float', 'bool'], strict: true)) {
                throw new InvalidArgumentException('Console operation value option type is unsupported.');
            }
            $name = $this->optionName($parameter->getName());
            if (array_key_exists($name, $names) || in_array($name, self::globalOptions(), strict: true)) {
                throw new InvalidArgumentException(
                    'Console operation value option name collides with a reserved option.',
                );
            }
            $names[$name] = true;
            $default = $parameter->isDefaultValueAvailable()
                ? $this->normalizeDefault($parameter->getDefaultValue())
                : null;
            if (
                $parameter->isDefaultValueAvailable() && !$this->validDefault($default, $typeName, $type->allowsNull())
            ) {
                throw new InvalidArgumentException('Console operation value option default is invalid.');
            }
            $options[] = new OperationConsoleOptionMetadata(
                $parameter->getName(),
                $name,
                $typeName,
                $type->allowsNull(),
                !$parameter->isDefaultValueAvailable(),
                $default,
            );
        }
        if (count($public) !== count($parameters)) {
            throw new InvalidArgumentException(
                'Console operation value constructor must match its promoted properties.',
            );
        }

        return $options;
    }

    private function optionName(string $property): string
    {
        $name = preg_replace(pattern: '/([A-Z]+)([A-Z][a-z])/', replacement: '$1-$2', subject: $property);
        $name ??= '';
        $name = preg_replace(pattern: '/([a-z0-9])([A-Z])/', replacement: '$1-$2', subject: $name);
        $name ??= '';
        $name = strtolower(str_replace(search: '_', replace: '-', subject: $name));
        $name = preg_replace(pattern: '/-+/', replacement: '-', subject: $name);
        $name ??= '';
        if ($name === '' || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $name) !== 1) {
            throw new InvalidArgumentException(
                'Console operation value property cannot be converted to an option name.',
            );
        }

        return $name;
    }

    private function validDefault(mixed $default, string $type, bool $nullable): bool
    {
        if ($default === null) {
            return $nullable;
        }

        return match ($type) {
            'string' => is_string($default),
            'int' => is_int($default),
            'float' => is_float($default),
            'bool' => is_bool($default),
            default => false,
        };
    }

    private function normalizeDefault(mixed $default): string|int|float|bool|null
    {
        if (is_string($default) || is_int($default) || is_float($default) || is_bool($default) || $default === null) {
            return $default;
        }

        throw new InvalidArgumentException('Console operation value option default is invalid.');
    }

    /** @param class-string $outcome */
    private function assertOutcomeNotSensitive(string $outcome): void
    {
        if ($outcome === EmptyOutcome::class) {
            return;
        }
        $this->assertShapeNotSensitive($this->outcomes->compile($outcome));
    }

    private function assertShapeNotSensitive(StructuredOutcomeShape $shape): void
    {
        foreach ($shape->fields as $field) {
            if ($field->sensitive) {
                throw new InvalidArgumentException('Console operation outcome must not contain sensitive properties.');
            }
            if ($field->dto !== null) {
                $this->assertShapeNotSensitive($field->dto);
            }
        }
    }

    /** @return list<string> */
    public static function globalOptions(): array
    {
        return ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'];
    }
}
