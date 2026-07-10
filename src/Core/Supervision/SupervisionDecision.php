<?php

declare(strict_types=1);

namespace BlackOps\Core\Supervision;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use LogicException;

#[PublicApi]
final readonly class SupervisionDecision
{
    private function __construct(
        private SupervisionAction $action,
        private ?int $delayMilliseconds = null,
    ) {}

    public static function retry(int $delayMilliseconds): self
    {
        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException('Retry delay must be greater than or equal to zero.');
        }

        return new self(SupervisionAction::Retry, $delayMilliseconds);
    }

    public static function fail(): self
    {
        return new self(SupervisionAction::Fail);
    }

    public static function deadLetter(): self
    {
        return new self(SupervisionAction::DeadLetter);
    }

    public function action(): SupervisionAction
    {
        return $this->action;
    }

    public function delayMilliseconds(): int
    {
        if ($this->delayMilliseconds === null) {
            throw new LogicException('Supervision decision does not include a retry delay.');
        }

        return $this->delayMilliseconds;
    }
}
