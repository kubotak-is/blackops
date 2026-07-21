<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use InvalidArgumentException;

/** @mago-expect lint:kan-defect */
final readonly class ApplicationCommandCollisionValidator
{
    /**
     * @param list<ApplicationCommandMetadata> $discovered
     * @param list<ApplicationCommandMetadata> $explicit
     * @param list<string> $frameworkNames
     * @return list<ApplicationCommandMetadata>
     */
    public function merge(array $discovered, array $explicit, array $frameworkNames): array
    {
        $explicitClasses = [];
        foreach ($explicit as $command) {
            $explicitClasses[$command->class] = true;
        }
        $discovered = array_values(array_filter(
            $discovered,
            static fn(ApplicationCommandMetadata $command): bool => !array_key_exists(
                $command->class,
                $explicitClasses,
            ),
        ));

        $names = array_fill_keys(keys: $frameworkNames, value: 'framework');
        foreach ([...$explicit, ...$discovered] as $command) {
            foreach ([$command->name, ...$command->aliases] as $name) {
                if (array_key_exists($name, $names)) {
                    if ($names[$name] === 'framework') {
                        throw new InvalidArgumentException(sprintf(
                            'Application command name "%s" conflicts with a framework command.',
                            $name,
                        ));
                    }

                    throw new InvalidArgumentException(sprintf(
                        'Application command name or alias "%s" conflicts with another command.',
                        $name,
                    ));
                }
                $names[$name] = $command->class;
            }
        }

        return $discovered;
    }

    /**
     * @param list<ApplicationCommandMetadata> $application
     * @param list<OperationConsoleCommandMetadata> $operations
     * @param list<string> $frameworkNames
     */
    public function validateOperationCommands(array $application, array $operations, array $frameworkNames): void
    {
        $names = array_fill_keys(keys: $frameworkNames, value: 'framework');
        foreach ($application as $command) {
            foreach ([$command->name, ...$command->aliases] as $name) {
                $names[$name] = 'application';
            }
        }
        foreach ($operations as $command) {
            if (!array_key_exists($command->name, $names)) {
                $names[$command->name] = 'operation';
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Operation console command name "%s" conflicts with a %s command.',
                $command->name,
                $names[$command->name],
            ));
        }
    }
}
