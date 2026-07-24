<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduler;

use BlackOps\Internal\Outbox\OutboxRelayRuntime;
use DateTimeImmutable;
use Throwable;

final readonly class OutboxRelayMaintenanceTask implements MaintenanceTask
{
    public const NAME = 'outbox-relay';

    public function __construct(
        private OutboxRelayRuntime $relay,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function run(DateTimeImmutable $now): MaintenanceTaskResult
    {
        try {
            $result = $this->relay->runBatch($now);
            return new MaintenanceTaskResult(self::NAME, $result->sent, 'Outbox relay completed.');
        } catch (Throwable) {
            return new MaintenanceTaskResult(self::NAME, 0, 'Outbox relay failed safely.');
        }
    }
}
