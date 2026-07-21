<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationCommandMetadata;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;

final readonly class ApplicationCommandValidator
{
    /**
     * @param iterable<array-key, mixed> $entries
     * @return list<Command|class-string<Command>>
     */
    public function validate(iterable $entries): array
    {
        $commands = [];
        $identities = [];
        $names = [];

        $values = [...$entries];
        array_walk($values, function (mixed $entry) use (&$commands, &$identities, &$names): void {
            $this->append($entry, $commands, $identities, $names);
        });

        return $commands;
    }

    /**
     * @param list<Command|class-string<Command>> $commands
     * @param array<class-string<Command>, true> $identities
     * @param array<string, class-string<Command>> $names
     */
    private function append(mixed $entry, array &$commands, array &$identities, array &$names): void
    {
        if (!$entry instanceof Command && (!is_string($entry) || !is_a($entry, Command::class, allow_string: true))) {
            throw new InvalidArgumentException(
                'Application command must be a Symfony command instance or command class name.',
            );
        }

        $class = is_string($entry) ? $entry : $entry::class;
        if (($identities[$class] ?? false) === true) {
            return;
        }

        $command = $entry instanceof Command ? $entry : $this->instantiate($class);
        $metadata = ApplicationCommandMetadata::fromCommand($command);
        foreach ([$metadata->name, ...$metadata->aliases] as $name) {
            $registered = $names[$name] ?? null;
            if ($registered !== null && $registered !== $class) {
                throw new InvalidArgumentException(sprintf(
                    'Application command name or alias "%s" is registered by more than one command.',
                    $name,
                ));
            }
            $names[$name] = $class;
        }

        $identities[$class] = true;
        $commands[] = $entry;
    }

    /** @param class-string<Command> $class */
    private function instantiate(string $class): Command
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (
            !$reflection->isInstantiable()
            || $constructor !== null && $constructor->getNumberOfRequiredParameters() > 0
        ) {
            throw new InvalidArgumentException('Application command class must be instantiable without arguments.');
        }

        return $reflection->newInstance();
    }
}
