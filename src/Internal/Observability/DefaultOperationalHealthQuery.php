<?php

declare(strict_types=1);

namespace BlackOps\Internal\Observability;

use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthCheckProvider;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class DefaultOperationalHealthQuery implements OperationalHealthQuery
{
    /** @var list<OperationalHealthCheckProvider> */
    private array $readinessChecks;

    /** @param iterable<OperationalHealthCheckProvider> $readinessChecks */
    public function __construct(
        iterable $readinessChecks = [],
        private ClockInterface $clock = new SystemClock(),
    ) {
        $normalized = [];
        foreach ($readinessChecks as $check) {
            $normalized[] = $check;
        }
        $this->readinessChecks = $normalized;
    }

    public function check(OperationalHealthKind $kind): OperationalHealthReport
    {
        $checks = $kind === OperationalHealthKind::Liveness ? [] : $this->readinessChecks();
        $status = array_reduce(
            $checks,
            static fn(
                OperationalHealthStatus $status,
                OperationalHealthCheck $check,
            ): OperationalHealthStatus => $check->status === OperationalHealthStatus::Fail
                ? OperationalHealthStatus::Fail
                : $status,
            OperationalHealthStatus::Pass,
        );

        return new OperationalHealthReport($kind, $status, $this->now(), $checks);
    }

    /** @return list<OperationalHealthCheck> */
    private function readinessChecks(): array
    {
        $checks = [];
        foreach ($this->readinessChecks as $provider) {
            try {
                $code = $provider->code();
            } catch (Throwable) {
                $checks[] = OperationalHealthCheck::fail('check.invalid');
                continue;
            }
            try {
                $passes = $provider->check();
            } catch (Throwable) {
                $checks[] = OperationalHealthCheck::fail($code);
                continue;
            }
            try {
                $checks[] = $passes ? OperationalHealthCheck::pass($code) : OperationalHealthCheck::fail($code);
            } catch (Throwable) {
                $checks[] = OperationalHealthCheck::fail('check.invalid');
            }
        }

        return $checks;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
