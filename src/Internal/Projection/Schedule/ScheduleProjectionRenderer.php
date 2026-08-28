<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Schedule;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

final readonly class ScheduleProjectionRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    /**
     * @param list<array{name: string, operationType: string, cron: string, timezone: string, strategy: string}> $records
     */
    public function render(array $records, string|bool $format = 'txt', ?bool $json = null): string
    {
        if ($json !== null) {
            $format = $json ? 'json' : 'txt';
        }
        if (is_bool($format)) {
            $format = $format ? 'json' : 'txt';
        }
        if (!in_array($format, ['txt', 'json'], strict: true)) {
            throw new InvalidArgumentException('Schedule projection format is unsupported.');
        }

        $projection = $this->codec->projection(
            kind: 'blackops_schedule_projection',
            applicationBuildId: 'renderer',
            records: $records,
        );
        /** @var list<array{name: string, operationType: string, cron: string, timezone: string, strategy: string}> $validatedRecords */
        $validatedRecords = $projection->records;

        return $format === 'json' ? $this->json($validatedRecords) : $this->human($validatedRecords);
    }

    /**
     * @param list<array{name: string, operationType: string, cron: string, timezone: string, strategy: string}> $records
     */
    private function human(array $records): string
    {
        $output = new BufferedOutput();
        new Table($output)
            ->setHeaders(['Name', 'Operation Type', 'Cron', 'Timezone', 'Strategy'])
            ->setRows(array_map(static fn(array $record): array => [
                OutputFormatter::escape($record['name']),
                OutputFormatter::escape($record['operationType']),
                OutputFormatter::escape($record['cron']),
                OutputFormatter::escape($record['timezone']),
                OutputFormatter::escape($record['strategy']),
            ], $records))
            ->render();

        return $output->fetch();
    }

    /**
     * @param list<array{name: string, operationType: string, cron: string, timezone: string, strategy: string}> $records
     */
    private function json(array $records): string
    {
        return $this->codec->canonicalJson([
            'schemaVersion' => 1,
            'schedules' => $records,
        ]) . "\n";
    }
}
