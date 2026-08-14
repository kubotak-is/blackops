<?php

declare(strict_types=1);

namespace BlackOps\Console\Observability;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use Throwable;

#[PublicApi]
final readonly class OperationalHealthCliAdapter
{
    public function __construct(
        private OperationalHealthQuery $query,
        private OperationalHealthCliFormatter $formatter = new OperationalHealthCliFormatter(),
    ) {}

    /** @return array{output: string, exitCode: int} */
    public function run(OperationalHealthKind $kind, bool $json = false): array
    {
        try {
            $report = $this->query->check($kind);
        } catch (Throwable) {
            $report = new OperationalHealthReport(
                $kind,
                OperationalHealthStatus::Fail,
                new DateTimeImmutable('now'),
                [OperationalHealthCheck::fail('query_failed')],
            );
        }

        return [
            'output' => $this->formatter->format($report, $json),
            'exitCode' => $this->formatter->exitCode($report),
        ];
    }
}
