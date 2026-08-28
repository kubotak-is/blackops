<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Route;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

final readonly class RouteProjectionRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    /**
     * @param list<array{method: string, path: string, strategy: string|null, operationType: string|null, origin: string}> $records
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
            throw new InvalidArgumentException('Route projection format is unsupported.');
        }

        $projection = $this->codec->projection(
            kind: 'blackops_route_projection',
            applicationBuildId: 'renderer',
            records: $records,
        );
        /** @var list<array{method: string, path: string, strategy: string|null, operationType: string|null, origin: string}> $validatedRecords */
        $validatedRecords = $projection->records;

        return $format === 'json' ? $this->json($validatedRecords) : $this->human($validatedRecords);
    }

    /**
     * @param list<array{method: string, path: string, strategy: string|null, operationType: string|null, origin: string}> $records
     */
    private function human(array $records): string
    {
        $output = new BufferedOutput();
        new Table($output)
            ->setHeaders(['Method', 'Path', 'Strategy', 'Operation Type', 'Origin'])
            ->setRows(array_map(static fn(array $record): array => [
                OutputFormatter::escape($record['method']),
                OutputFormatter::escape($record['path']),
                OutputFormatter::escape($record['strategy'] ?? ''),
                OutputFormatter::escape($record['operationType'] ?? ''),
                OutputFormatter::escape($record['origin']),
            ], $records))
            ->render();

        return $output->fetch();
    }

    /**
     * @param list<array{method: string, path: string, strategy: string|null, operationType: string|null, origin: string}> $records
     */
    private function json(array $records): string
    {
        return $this->codec->canonicalJson([
            'schemaVersion' => 1,
            'routes' => $records,
        ]) . "\n";
    }
}
