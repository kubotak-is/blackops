<?php

declare(strict_types=1);

namespace BlackOps\Console\Observability;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Observability\OperationalHealthReport;
use JsonException;
use RuntimeException;

#[PublicApi]
final readonly class OperationalHealthCliFormatter
{
    /** @mago-expect lint:no-boolean-flag-parameter */
    public function format(OperationalHealthReport $report, bool $json = false): string
    {
        if ($json) {
            return $this->json($report);
        }

        $lines = [
            'kind: ' . $report->kind->value,
            'status: ' . $report->status->value,
            'checkedAt: ' . $report->toArray()['checkedAt'],
        ];
        foreach ($report->checks as $check) {
            $lines[] = 'check.' . $check->code . ': ' . $check->status->value;
        }

        return implode("\n", $lines) . "\n";
    }

    public function exitCode(OperationalHealthReport $report): int
    {
        return $report->isPassing() ? 0 : 1;
    }

    private function json(OperationalHealthReport $report): string
    {
        try {
            return json_encode($report->toArray(), JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode operational health output.', previous: $exception);
        }
    }
}
