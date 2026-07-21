<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class ApplicationCommandManifestFile
{
    public const int SCHEMA_VERSION = 1;

    /** @param list<ApplicationCommandMetadata> $commands */
    public function write(array $commands, string $path, string $applicationBuildId): void
    {
        $commands = $this->normalizeCommands($commands);
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
            $written = file_put_contents($temporary, $this->source($commands, $applicationBuildId));
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
        if (!is_array($data) || array_keys($data) !== ['schema_version', 'application_build_id', 'commands']) {
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

        $normalized = $this->normalizeCommands($commands);
        if ($normalized !== $commands) {
            throw new InvalidArgumentException(
                'Application command manifest commands are not deterministically ordered.',
            );
        }

        return new ApplicationCommandManifestArtifact(self::SCHEMA_VERSION, $data['application_build_id'], $commands);
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

    /** @param list<ApplicationCommandMetadata> $commands */
    private function source(array $commands, string $applicationBuildId): string
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

        return (
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export([
                'schema_version' => self::SCHEMA_VERSION,
                'application_build_id' => $applicationBuildId,
                'commands' => $entries,
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
