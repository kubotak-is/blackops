<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Projection\Command\CommandProjectionReservationRegistry;
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

        $names = array_fill_keys(
            keys: array_values(array_unique([...$frameworkNames, ...CommandProjectionReservationRegistry::names()])),
            value: 'framework',
        );
        foreach ([...$explicit, ...$discovered] as $command) {
            foreach ([$command->name, ...$command->aliases] as $name) {
                if (array_key_exists($name, $names)) {
                    if ($names[$name] === 'framework') {
                        throw new InvalidArgumentException(sprintf(
                            'Application command class "%s" name or alias "%s" conflicts with a framework command; rename the application command or alias "%s".',
                            $command->class,
                            $name,
                            $name,
                        ));
                    }

                    throw new InvalidArgumentException(sprintf(
                        'Application command class "%s" name or alias "%s" conflicts with another command (application command class "%s"); rename the application command or alias "%s".',
                        $command->class,
                        $name,
                        $names[$name],
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
        $names = array_fill_keys(
            keys: array_values(array_unique([...$frameworkNames, ...CommandProjectionReservationRegistry::names()])),
            value: 'framework',
        );
        foreach ($application as $command) {
            foreach ([$command->name, ...$command->aliases] as $name) {
                $names[$name] = $command->class;
            }
        }
        foreach ($operations as $command) {
            if (!array_key_exists($command->name, $names)) {
                $names[$command->name] = 'operation:' . $command->typeId;
                continue;
            }

            $owner = $names[$command->name];
            $ownerDescription = match (true) {
                $owner === 'framework' => 'a framework command',
                str_starts_with($owner, 'operation:') => 'operation ' . substr($owner, strlen('operation:')),
                default => 'application command class "' . $owner . '"',
            };
            throw new InvalidArgumentException(sprintf(
                'Operation console command "%s" conflicts with %s; rename the operation command to avoid "%s".',
                $command->name,
                $ownerDescription,
                $command->name,
            ));
        }
    }
}
