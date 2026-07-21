<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Throwable;

final readonly class ApplicationCommandMetadata
{
    /**
     * @param class-string<Command> $class
     * @param list<string> $aliases
     * @param list<string> $usages
     */
    public function __construct(
        public string $class,
        public string $name,
        public ?string $description,
        public array $aliases,
        public bool $hidden,
        public ?string $help,
        public array $usages,
    ) {
        self::assertClass($class);
        self::assertName($name);
        $names = [$name => true];
        foreach ($aliases as $alias) {
            self::assertName($alias);
            if (array_key_exists($alias, $names)) {
                throw new InvalidArgumentException('Application command name and aliases must be unique.');
            }
            $names[$alias] = true;
        }
    }

    /** @param ReflectionClass<object> $class */
    public static function fromAttribute(ReflectionClass $class): self
    {
        $attributes = $class->getAttributes(AsCommand::class);
        if (count($attributes) !== 1) {
            throw new InvalidArgumentException(
                'Discovered application command must define exactly one AsCommand attribute.',
            );
        }

        try {
            $attribute = $attributes[0]->newInstance();
        } catch (Throwable) {
            throw new InvalidArgumentException('Discovered application command AsCommand metadata is invalid.');
        }

        $parts = explode('|', $attribute->name);
        $name = array_shift($parts);
        $hidden = false;
        if ($name === '') {
            $hidden = true;
            $name = array_shift($parts);
        }

        if (!is_string($name)) {
            throw new InvalidArgumentException('Discovered application command must define a non-empty command name.');
        }

        /** @var class-string<Command> $commandClass */
        $commandClass = $class->getName();

        return new self(
            $commandClass,
            $name,
            $attribute->description,
            $parts,
            $hidden,
            $attribute->help,
            self::stringList($attribute->usages, 'usages'),
        );
    }

    public static function fromCommand(Command $command): self
    {
        $name = $command->getName();
        if ($name === null || $name === '') {
            throw new InvalidArgumentException('Application command must define a non-empty command name.');
        }

        return new self(
            $command::class,
            $name,
            $command->getDescription(),
            self::stringList($command->getAliases(), 'aliases'),
            $command->isHidden(),
            $command->getHelp(),
            self::stringList($command->getUsages(), 'usages'),
        );
    }

    /** @param mixed $values
     * @return list<string>
     */
    private static function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new InvalidArgumentException('Application command ' . $label . ' must be a list of strings.');
        }

        $strings = [];
        array_walk($values, static function (mixed $value) use (&$strings, $label): void {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Application command ' . $label . ' must be a list of strings.');
            }
            $strings[] = $value;
        });

        return $strings;
    }

    private static function assertClass(string $class): void
    {
        if (
            $class === ''
            || preg_match(
                '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/D',
                $class,
            ) !== 1
        ) {
            throw new InvalidArgumentException('Application command class name is invalid.');
        }
    }

    private static function assertName(string $name): void
    {
        if (preg_match('/^[^:]++(:[^:]++)*$/D', $name) !== 1) {
            throw new InvalidArgumentException('Application command name or alias is invalid.');
        }
    }
}
