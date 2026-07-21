<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationCommandMetadata;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;

final readonly class ExplicitApplicationCommands
{
    /** @param list<Command> $commands */
    private function __construct(
        private array $commands,
    ) {}

    /** @param iterable<Command|class-string<Command>> $entries */
    public static function from(iterable $entries): self
    {
        $commands = [];
        foreach ($entries as $entry) {
            $commands[] = $entry instanceof Command ? $entry : new ReflectionClass($entry)->newInstance();
        }

        return new self($commands);
    }

    /** @return list<Command> */
    public function commands(): array
    {
        return $this->commands;
    }

    /** @return list<ApplicationCommandMetadata> */
    public function metadata(): array
    {
        return array_map(ApplicationCommandMetadata::fromCommand(...), $this->commands);
    }
}
