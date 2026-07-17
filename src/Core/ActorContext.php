<?php

declare(strict_types=1);

namespace BlackOps\Core;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class ActorContext
{
    public function __construct(
        private ?ActorRef $origin,
        private ?ActorRef $authorization,
        private ActorRef $execution,
    ) {}

    public function origin(): ?ActorRef
    {
        return $this->origin;
    }

    public function authorization(): ?ActorRef
    {
        return $this->authorization;
    }

    public function execution(): ActorRef
    {
        return $this->execution;
    }
}
