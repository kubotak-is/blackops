<?php

declare(strict_types=1);

namespace BlackOps\Internal\Queue;

use BlackOps\Core\Queue\QueueStatusSnapshot;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use InvalidArgumentException;

final readonly class QueueStatusRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    public function render(QueueStatusSnapshot $snapshot, string|bool $format = 'txt', ?bool $json = null): string
    {
        if ($json !== null) {
            $format = $json ? 'json' : 'txt';
        }
        if (is_bool($format)) {
            $format = $format ? 'json' : 'txt';
        }
        if (!in_array($format, ['txt', 'json'], strict: true)) {
            throw new InvalidArgumentException('Queue status format is unsupported.');
        }

        return $format === 'json' ? $this->json($snapshot) : $this->human($snapshot);
    }

    private function human(QueueStatusSnapshot $snapshot): string
    {
        $lines = [
            'schemaVersion=1',
            'scope=installation',
            'observedAt=' . $snapshot->toArray()['observedAt'],
            'counts.acceptedDelayed=' . $snapshot->acceptedDelayed,
            'counts.acceptedReady=' . $snapshot->acceptedReady,
            'counts.deadLettered=' . $snapshot->deadLettered,
            'counts.retryDelayed=' . $snapshot->retryDelayed,
            'counts.retryReady=' . $snapshot->retryReady,
            'counts.runningActive=' . $snapshot->runningActive,
            'counts.runningExpired=' . $snapshot->runningExpired,
            'counts.supervising=' . $snapshot->supervising,
            'agesSeconds.oldestDeadLettered=' . $this->value($snapshot->oldestDeadLettered),
            'agesSeconds.oldestReady=' . $this->value($snapshot->oldestReady),
            'agesSeconds.oldestRunningExpired=' . $this->value($snapshot->oldestRunningExpired),
            'agesSeconds.oldestSupervising=' . $this->value($snapshot->oldestSupervising),
        ];

        return implode("\n", $lines) . "\n";
    }

    private function json(QueueStatusSnapshot $snapshot): string
    {
        return $this->codec->canonicalJson($snapshot->toArray()) . "\n";
    }

    private function value(?int $value): string
    {
        return $value === null ? 'null' : (string) $value;
    }
}
