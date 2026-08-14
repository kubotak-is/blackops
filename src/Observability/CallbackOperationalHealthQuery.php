<?php

declare(strict_types=1);

namespace BlackOps\Observability;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;
use InvalidArgumentException;

#[PublicApi]
final readonly class CallbackOperationalHealthQuery implements OperationalHealthQuery
{
    /** @var array<string, \Closure(): bool> */
    private array $checks;

    /** @param array<string, callable(): bool> $checks */
    public function __construct(array $checks)
    {
        $normalized = [];
        foreach (OperationalHealthQueryFactory::requiredReadinessCheckCodes() as $code) {
            $callback = $checks[$code] ?? null;
            if (!is_callable($callback)) {
                throw new InvalidArgumentException('A callback is required for each operational readiness check.');
            }
            $normalized[$code] = static fn(): bool => $callback() === true;
        }
        $this->checks = $normalized;
    }

    public function check(OperationalHealthKind $kind): OperationalHealthReport
    {
        $checks = [];
        if ($kind === OperationalHealthKind::Readiness) {
            foreach (OperationalHealthQueryFactory::requiredReadinessCheckCodes() as $code) {
                try {
                    $checks[] = $this->checks[$code]()
                        ? OperationalHealthCheck::pass($code)
                        : OperationalHealthCheck::fail($code);
                } catch (\Throwable) {
                    $checks[] = OperationalHealthCheck::fail($code);
                }
            }
        }

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

        return new OperationalHealthReport($kind, $status, new DateTimeImmutable('now'), $checks);
    }
}
