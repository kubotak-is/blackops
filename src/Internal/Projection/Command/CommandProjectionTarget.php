<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

final readonly class CommandProjectionTarget
{
    /** @param list<string> $aliases */
    public function __construct(
        public string $name,
        public array $aliases,
        public string $description,
        public bool $hidden,
    ) {}

    /** @return array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool} */
    public function record(string $origin): array
    {
        return [
            'name' => $this->name,
            'aliases' => $this->aliases,
            'description' => $this->description,
            'origin' => $origin,
            'hidden' => $this->hidden,
        ];
    }
}
