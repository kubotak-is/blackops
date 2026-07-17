<?php

declare(strict_types=1);

namespace BlackOps\Database;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ExecutionContext;
use Throwable;

#[PublicApi]
final readonly class AfterCommitFailure
{
    public function __construct(
        private string $serviceClass,
        private string $method,
        private Throwable $cause,
        private ?ExecutionContext $context = null,
    ) {}

    public function serviceClass(): string
    {
        return $this->serviceClass;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function cause(): Throwable
    {
        return $this->cause;
    }

    public function context(): ?ExecutionContext
    {
        return $this->context;
    }
}
