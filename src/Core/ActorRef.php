<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class ActorRef
{
    private string $id;
    private string $type;

    public function __construct(string $id, string $type)
    {
        $id = trim($id);
        $type = trim($type);

        if ($id === '') {
            throw new InvalidArgumentException('ActorRef id must not be empty.');
        }

        if ($type === '') {
            throw new InvalidArgumentException('ActorRef type must not be empty.');
        }

        $this->id = $id;
        $this->type = $type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }
}
