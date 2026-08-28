<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

use BlackOps\Core\Outbox\OutboxStatusSnapshot;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use InvalidArgumentException;

final readonly class OutboxStatusRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    public function render(OutboxStatusSnapshot $snapshot, string|bool $format = 'txt', ?bool $json = null): string
    {
        if ($json !== null) {
            $format = $json ? 'json' : 'txt';
        }
        if (is_bool($format)) {
            $format = $format ? 'json' : 'txt';
        }
        if (!in_array($format, ['txt', 'json'], strict: true)) {
            throw new InvalidArgumentException('Outbox status format is unsupported.');
        }

        return $format === 'json' ? $this->json($snapshot) : $this->human($snapshot);
    }

    private function human(OutboxStatusSnapshot $snapshot): string
    {
        $lines = [
            'schemaVersion=1',
            'scope=installation',
            'observedAt=' . $snapshot->toArray()['observedAt'],
            'counts.deadLettered=' . $snapshot->deadLettered,
            'counts.leasedActive=' . $snapshot->leasedActive,
            'counts.leasedExpired=' . $snapshot->leasedExpired,
            'counts.pendingDelayed=' . $snapshot->pendingDelayed,
            'counts.pendingReady=' . $snapshot->pendingReady,
            'counts.retryDelayed=' . $snapshot->retryDelayed,
            'counts.retryReady=' . $snapshot->retryReady,
            'agesSeconds.oldestDeadLettered=' . $this->value($snapshot->oldestDeadLettered),
            'agesSeconds.oldestLeasedExpired=' . $this->value($snapshot->oldestLeasedExpired),
            'agesSeconds.oldestReady=' . $this->value($snapshot->oldestReady),
        ];

        return implode("\n", $lines) . "\n";
    }

    private function json(OutboxStatusSnapshot $snapshot): string
    {
        return $this->codec->canonicalJson($snapshot->toArray()) . "\n";
    }

    private function value(?int $value): string
    {
        return $value === null ? 'null' : (string) $value;
    }
}
