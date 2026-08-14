<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;
use DateTimeZone;

#[PublicApi]
final readonly class OperationalHealthReport
{
    public const int SCHEMA_VERSION = 1;

    /** @param list<OperationalHealthCheck> $checks */
    public function __construct(
        public OperationalHealthKind $kind,
        public OperationalHealthStatus $status,
        public DateTimeImmutable $checkedAt,
        public array $checks,
    ) {}

    /** @return array{schemaVersion: int, kind: string, status: string, checkedAt: string, checks: list<array{code: string, status: string}>} */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'checkedAt' => $this->checkedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'checks' => array_map(static fn(OperationalHealthCheck $check): array => $check->toArray(), $this->checks),
        ];
    }

    public function isPassing(): bool
    {
        return $this->status === OperationalHealthStatus::Pass;
    }
}
