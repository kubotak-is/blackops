<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Schedule;

use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetProjection;
use BlackOps\Internal\Projection\BuildSet\ProjectionExecutionStrategyWire;

final readonly class ScheduleProjectionProducer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
        private ProjectionExecutionStrategyWire $strategyWire = new ProjectionExecutionStrategyWire(),
    ) {}

    public function produce(string $applicationBuildId, OperationRegistry $registry): ProjectionBuildSetProjection
    {
        $records = [];
        foreach ($registry->all() as $metadata) {
            if (!$metadata instanceof OperationMetadata || $metadata->schedule === null) {
                continue;
            }
            $records[] = [
                'name' => $metadata->schedule->name,
                'operationType' => $metadata->typeId,
                'cron' => $metadata->schedule->cron,
                'timezone' => $metadata->schedule->timezone,
                'strategy' => $this->strategyWire->map($metadata->strategy),
            ];
        }

        return $this->codec->projection(
            kind: 'blackops_schedule_projection',
            applicationBuildId: $applicationBuildId,
            records: $records,
        );
    }
}
