<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class ApplicationCommandManifestFile
{
    public const int SCHEMA_VERSION = 2;

    /**
     * @param list<ApplicationCommandMetadata> $commands
     * @param list<OperationConsoleCommandMetadata> $operationCommands
     */
    public function write(array $commands, array $operationCommands, string $path, string $applicationBuildId): void
    {
        $commands = $this->normalizeCommands($commands);
        $operationCommands = $this->normalizeOperationCommands($operationCommands);
        $this->assertBuildId($applicationBuildId);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Application command manifest directory does not exist.');
        }

        $temporary = tempnam(directory: $directory, prefix: 'command-manifest-');
        if ($temporary === false) {
            throw new RuntimeException('Application command manifest temporary file could not be created.');
        }

        try {
            $written = file_put_contents($temporary, $this->source($commands, $operationCommands, $applicationBuildId));
            if ($written === false) {
                throw new RuntimeException('Application command manifest could not be written.');
            }
            $this->loadArtifact($temporary);
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Application command manifest could not be moved into place.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function loadArtifact(string $path): ApplicationCommandManifestArtifact
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Application command manifest file does not exist.');
        }

        try {
            return $this->decodeArtifact($this->requireFile($path));
        } catch (Throwable) {
            throw new InvalidArgumentException('Application command manifest file is invalid.');
        }
    }

    private function decodeArtifact(mixed $data): ApplicationCommandManifestArtifact
    {
        if (
            !is_array($data)
            || array_keys($data) !== ['schema_version', 'application_build_id', 'commands', 'operation_commands']
        ) {
            throw new InvalidArgumentException('Application command manifest must return an exact versioned array.');
        }
        if ($data['schema_version'] !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Application command manifest schema version is not supported.');
        }
        if (!is_string($data['application_build_id'])) {
            throw new InvalidArgumentException('Application command manifest build ID is invalid.');
        }
        $this->assertBuildId($data['application_build_id']);
        if (!is_array($data['commands']) || !array_is_list($data['commands'])) {
            throw new InvalidArgumentException('Application command manifest commands must be a list.');
        }

        $commands = array_map($this->decodeEntry(...), $data['commands']);
        if (!is_array($data['operation_commands']) || !array_is_list($data['operation_commands'])) {
            throw new InvalidArgumentException('Application command manifest operation commands must be a list.');
        }
        $operationCommands = array_map($this->decodeOperationEntry(...), $data['operation_commands']);

        $normalized = $this->normalizeCommands($commands);
        if ($normalized !== $commands) {
            throw new InvalidArgumentException(
                'Application command manifest commands are not deterministically ordered.',
            );
        }
        if ($this->normalizeOperationCommands($operationCommands) !== $operationCommands) {
            throw new InvalidArgumentException(
                'Application command manifest operation commands are not deterministically ordered.',
            );
        }

        return new ApplicationCommandManifestArtifact(
            self::SCHEMA_VERSION,
            $data['application_build_id'],
            $commands,
            $operationCommands,
        );
    }

    private function requireFile(string $path): mixed
    {
        $level = ob_get_level();
        ob_start();

        try {
            return (static fn(string $file): mixed => require $file)($path);
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /** @param mixed $entry */
    private function decodeEntry(mixed $entry): ApplicationCommandMetadata
    {
        $keys = ['class', 'name', 'description', 'aliases', 'hidden', 'help', 'usages'];
        if (!is_array($entry) || array_keys($entry) !== $keys) {
            throw new InvalidArgumentException('Application command manifest entry has an invalid shape.');
        }
        if (
            !is_string($entry['class'])
            || !is_string($entry['name'])
            || $entry['description'] !== null && !is_string($entry['description'])
            || !is_bool($entry['hidden'])
            || $entry['help'] !== null && !is_string($entry['help'])
        ) {
            throw new InvalidArgumentException('Application command manifest entry has invalid values.');
        }

        /** @var class-string<\Symfony\Component\Console\Command\Command> $commandClass */
        $commandClass = $entry['class'];

        return new ApplicationCommandMetadata(
            $commandClass,
            $entry['name'],
            $entry['description'],
            $this->stringList($entry['aliases'], 'aliases'),
            $entry['hidden'],
            $entry['help'],
            $this->stringList($entry['usages'], 'usages'),
        );
    }

    private function decodeOperationEntry(mixed $entry): OperationConsoleCommandMetadata
    {
        $keys = ['type_id', 'definition', 'value', 'outcome', 'strategy', 'name', 'description', 'options'];
        if (!is_array($entry) || array_keys($entry) !== $keys) {
            throw new InvalidArgumentException('Operation console command manifest entry has an invalid shape.');
        }
        foreach (array_slice(array: $keys, offset: 0, length: 7) as $key) {
            if (!is_string($entry[$key])) {
                throw new InvalidArgumentException('Operation console command manifest entry has invalid values.');
            }
        }
        if (!is_array($entry['options']) || !array_is_list($entry['options'])) {
            throw new InvalidArgumentException('Operation console command manifest options must be a list.');
        }

        /** @var class-string<\BlackOps\Core\Operation> $definition */
        $definition = $entry['definition'];
        /** @var class-string<\BlackOps\Core\OperationValue> $value */
        $value = $entry['value'];
        /** @var class-string<\BlackOps\Core\Outcome> $outcome */
        $outcome = $entry['outcome'];
        /** @var class-string<\BlackOps\Core\Execution\ExecutionStrategy> $strategy */
        $strategy = $entry['strategy'];
        /** @var string $typeId */
        $typeId = $entry['type_id'];
        /** @var string $name */
        $name = $entry['name'];
        /** @var string $description */
        $description = $entry['description'];
        $options = array_map($this->decodeOption(...), $entry['options']);

        return new OperationConsoleCommandMetadata(
            $typeId,
            $definition,
            $value,
            $outcome,
            $strategy,
            $name,
            $description,
            $options,
        );
    }

    private function decodeOption(mixed $option): OperationConsoleOptionMetadata
    {
        $keys = ['property', 'name', 'type', 'nullable', 'required', 'default'];
        if (!is_array($option) || array_keys($option) !== $keys) {
            throw new InvalidArgumentException('Operation console command manifest option has an invalid shape.');
        }
        if (
            !is_string($option['property'])
            || !is_string($option['name'])
            || !in_array($option['type'], ['string', 'int', 'float', 'bool'], strict: true)
            || !is_bool($option['nullable'])
            || !is_bool($option['required'])
            || !is_string($option['default'])
            && !is_int($option['default'])
            && !is_float($option['default'])
            && !is_bool($option['default'])
            && $option['default'] !== null
        ) {
            throw new InvalidArgumentException('Operation console command manifest option has invalid values.');
        }

        $metadata = new OperationConsoleOptionMetadata(
            $option['property'],
            $option['name'],
            $option['type'],
            $option['nullable'],
            $option['required'],
            $option['default'],
        );
        $this->assertOption($metadata);

        return $metadata;
    }

    /**
     * @param list<ApplicationCommandMetadata> $commands
     * @return list<ApplicationCommandMetadata>
     */
    private function normalizeCommands(array $commands): array
    {
        usort(
            $commands,
            static fn(ApplicationCommandMetadata $left, ApplicationCommandMetadata $right): int => (
                [$left->name, $left->class] <=> [$right->name, $right->class]
            ),
        );
        $classes = [];
        $names = [];
        foreach ($commands as $command) {
            if (array_key_exists($command->class, $classes)) {
                throw new InvalidArgumentException('Application command manifest contains a duplicate command class.');
            }
            $classes[$command->class] = true;
            foreach ([$command->name, ...$command->aliases] as $name) {
                if (array_key_exists($name, $names)) {
                    throw new InvalidArgumentException(
                        'Application command manifest contains a duplicate command name or alias.',
                    );
                }
                $names[$name] = true;
            }
        }

        return $commands;
    }

    /**
     * @param list<OperationConsoleCommandMetadata> $commands
     * @return list<OperationConsoleCommandMetadata>
     */
    private function normalizeOperationCommands(array $commands): array
    {
        usort(
            $commands,
            static fn($left, $right): int => [$left->name, $left->typeId] <=> [$right->name, $right->typeId],
        );
        $types = [];
        $definitions = [];
        $names = [];
        foreach ($commands as $command) {
            $this->assertOperationCommand($command);
            if (
                array_key_exists($command->typeId, $types)
                || array_key_exists($command->definition, $definitions)
                || array_key_exists($command->name, $names)
            ) {
                throw new InvalidArgumentException('Operation console command manifest contains a duplicate identity.');
            }
            $types[$command->typeId] = true;
            $definitions[$command->definition] = true;
            $names[$command->name] = true;
            $properties = [];
            $options = [];
            foreach ($command->options as $option) {
                $this->assertOption($option);
                if (array_key_exists($option->property, $properties) || array_key_exists($option->name, $options)) {
                    throw new InvalidArgumentException(
                        'Operation console command manifest contains a duplicate option.',
                    );
                }
                $properties[$option->property] = true;
                $options[$option->name] = true;
            }
        }

        return $commands;
    }

    private function assertOperationCommand(OperationConsoleCommandMetadata $command): void
    {
        if (
            preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/D', $command->typeId) !== 1
            || preg_match('/^[^:]++(:[^:]++)*$/D', $command->name) !== 1
            || preg_match('/[\x00-\x20\x7f|]/D', $command->name) === 1
            || !$this->className($command->definition)
            || !$this->className($command->value)
            || !$this->className($command->outcome)
            || !$this->className($command->strategy)
        ) {
            throw new InvalidArgumentException('Operation console command manifest identity is invalid.');
        }
    }

    private function className(string $class): bool
    {
        return (
            preg_match(
                '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/D',
                $class,
            ) === 1
        );
    }

    private function assertOption(OperationConsoleOptionMetadata $option): void
    {
        if (
            preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $option->property) !== 1
            || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $option->name) !== 1
            || in_array($option->name, ['json', ...OperationConsoleMetadataCompiler::globalOptions()], strict: true)
        ) {
            throw new InvalidArgumentException('Operation console command manifest option identity is invalid.');
        }
        if ($option->required && $option->default !== null) {
            throw new InvalidArgumentException('Required operation console option cannot have a default.');
        }
        if ($option->default === null && !$option->required && !$option->nullable) {
            throw new InvalidArgumentException('Operation console option default is invalid.');
        }
        if ($option->default !== null && get_debug_type($option->default) !== $option->type) {
            throw new InvalidArgumentException('Operation console option default type is invalid.');
        }
    }

    /** @param mixed $values
     * @return list<string>
     */
    private function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException('Application command manifest ' . $label . ' must be a list.');
        }
        $strings = [];
        array_walk($values, static function (mixed $value) use (&$strings, $label): void {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Application command manifest ' . $label . ' must contain strings.');
            }
            $strings[] = $value;
        });

        return $strings;
    }

    /**
     * @param list<ApplicationCommandMetadata> $commands
     * @param list<OperationConsoleCommandMetadata> $operationCommands
     */
    private function source(array $commands, array $operationCommands, string $applicationBuildId): string
    {
        $entries = array_map(static fn(ApplicationCommandMetadata $command): array => [
            'class' => $command->class,
            'name' => $command->name,
            'description' => $command->description,
            'aliases' => $command->aliases,
            'hidden' => $command->hidden,
            'help' => $command->help,
            'usages' => $command->usages,
        ], $commands);
        $operationEntries = array_map(static fn(OperationConsoleCommandMetadata $command): array => [
            'type_id' => $command->typeId,
            'definition' => $command->definition,
            'value' => $command->value,
            'outcome' => $command->outcome,
            'strategy' => $command->strategy,
            'name' => $command->name,
            'description' => $command->description,
            'options' => array_map(static fn(OperationConsoleOptionMetadata $option): array => [
                'property' => $option->property,
                'name' => $option->name,
                'type' => $option->type,
                'nullable' => $option->nullable,
                'required' => $option->required,
                'default' => $option->default,
            ], $command->options),
        ], $operationCommands);

        return (
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export([
                'schema_version' => self::SCHEMA_VERSION,
                'application_build_id' => $applicationBuildId,
                'commands' => $entries,
                'operation_commands' => $operationEntries,
            ], return: true)
            . ";\n"
        );
    }

    private function assertBuildId(string $applicationBuildId): void
    {
        if (trim($applicationBuildId) === '') {
            throw new InvalidArgumentException('Application command manifest build ID must be a non-empty string.');
        }
    }
}
