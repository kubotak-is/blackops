<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class OperationalHealthCheck
{
    public function __construct(
        public string $code,
        public OperationalHealthStatus $status,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/D', $code) !== 1) {
            throw new InvalidArgumentException('Operational health check code is invalid.');
        }
    }

    public static function pass(string $code): self
    {
        return new self($code, OperationalHealthStatus::Pass);
    }

    public static function fail(string $code): self
    {
        return new self($code, OperationalHealthStatus::Fail);
    }

    /** @return array{code: string, status: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'status' => $this->status->value];
    }
}
