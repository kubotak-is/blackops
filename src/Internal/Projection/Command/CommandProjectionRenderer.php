<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Keeps all projection formats on the same validated row set and status wire.
 * The format-specific branches and hidden-row flag are intentionally closed
 * here so text and structured output cannot drift apart.
 *
 * @mago-expect lint:kan-defect
 * @mago-expect lint:no-boolean-flag-parameter
 * @mago-expect lint:no-else-clause
 */
final readonly class CommandProjectionRenderer
{
    public function __construct(
        private ProjectionBuildSetCodec $codec = new ProjectionBuildSetCodec(),
    ) {}

    /**
     * @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records
     */
    public function render(
        array $records,
        ProjectionBuildSetRecoveryStatus $status,
        string $format = 'txt',
        bool $includeHidden = false,
    ): string {
        $projection = $this->codec->projection(
            kind: 'blackops_command_projection',
            applicationBuildId: 'renderer',
            records: $records,
        );
        /** @var list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $validatedRecords */
        $validatedRecords = $projection->records;
        $visible = array_values(array_filter(
            $validatedRecords,
            static fn(array $record): bool => $includeHidden || $record['hidden'] === false,
        ));

        return match ($format) {
            'txt' => $this->human($visible),
            'raw' => $this->raw($visible),
            'json' => $this->json($visible, $status, $includeHidden),
            'xml' => $this->xml($visible, $status, $includeHidden),
            'md' => $this->markdown($visible, $status, $includeHidden),
            default => throw new InvalidArgumentException('Command projection format is unsupported.'),
        };
    }

    /** @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records */
    private function human(array $records): string
    {
        $output = new BufferedOutput();
        new Table($output)
            ->setHeaders(['Name', 'Aliases', 'Description', 'Origin'])
            ->setRows(array_map(static fn(array $record): array => [
                OutputFormatter::escape($record['name']),
                OutputFormatter::escape(implode(',', $record['aliases'])),
                OutputFormatter::escape($record['description']),
                OutputFormatter::escape($record['origin']),
            ], $records))
            ->render();

        return $output->fetch();
    }

    /** @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records */
    private function raw(array $records): string
    {
        $bytes = '';
        foreach ($records as $record) {
            $bytes .= $record['name'] . "\t" . implode(',', $record['aliases']) . "\n";
        }

        return $bytes;
    }

    /** @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records */
    private function json(array $records, ProjectionBuildSetRecoveryStatus $status, bool $includeHidden): string
    {
        $commands = array_map(fn(array $record): array => $this->structuredRecord($record, $includeHidden), $records);

        return $this->codec->canonicalJson([
            'schemaVersion' => 1,
            'applicationProjectionStatus' => $status->value,
            'commands' => $commands,
        ]) . "\n";
    }

    /** @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records */
    private function xml(array $records, ProjectionBuildSetRecoveryStatus $status, bool $includeHidden): string
    {
        $bytes = '<blackopsCommandProjection version="1" status="' . $status->value . '"><commands>';
        foreach ($records as $record) {
            $bytes .= '<command><name>' . $this->xmlText($record['name']) . '</name>';
            if ($record['aliases'] === []) {
                $bytes .= '<aliases/>';
            } else {
                $bytes .= '<aliases>';
                foreach ($record['aliases'] as $alias) {
                    $bytes .= '<alias>' . $this->xmlText($alias) . '</alias>';
                }
                $bytes .= '</aliases>';
            }
            $bytes .= '<description>' . $this->xmlText($record['description']) . '</description>';
            $bytes .= '<origin>' . $record['origin'] . '</origin>';
            if ($includeHidden) {
                $bytes .= '<hidden>' . ($record['hidden'] ? 'true' : 'false') . '</hidden>';
            }
            $bytes .= '</command>';
        }

        return $bytes . '</commands></blackopsCommandProjection>' . "\n";
    }

    /** @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records */
    private function markdown(array $records, ProjectionBuildSetRecoveryStatus $status, bool $includeHidden): string
    {
        $columns = ['Name', 'Aliases', 'Description', 'Origin'];
        if ($includeHidden) {
            $columns[] = 'Hidden';
        }
        /** @var \Closure(list<string>): string $line */
        $line = static fn(array $cells): string => '| ' . implode(' | ', array_map('strval', $cells)) . " |\n";
        $bytes = '<!-- blackops-command-projection-v1 status=' . $status->value . " -->\n";
        $bytes .= $line($columns);
        $bytes .= $line(array_fill(0, count($columns), value: '---'));
        foreach ($records as $record) {
            $cells = [
                $this->markdownCell($record['name']),
                $this->markdownCell(implode(',', $record['aliases'])),
                $this->markdownCell($record['description']),
                $record['origin'],
            ];
            if ($includeHidden) {
                $cells[] = $record['hidden'] ? 'true' : 'false';
            }
            $bytes .= $line($cells);
        }

        return $bytes;
    }

    /**
     * @param array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool} $record
     * @return array{name: string, aliases: list<string>, description: string, origin: string, hidden?: bool}
     */
    private function structuredRecord(array $record, bool $includeHidden): array
    {
        $structured = [
            'name' => $record['name'],
            'aliases' => $record['aliases'],
            'description' => $record['description'],
            'origin' => $record['origin'],
        ];
        if ($includeHidden) {
            $structured['hidden'] = $record['hidden'];
        }

        return $structured;
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, encoding: 'UTF-8');
    }

    private function markdownCell(string $value): string
    {
        return CommandProjectionMarkdownCellEncoder::encode($value);
    }
}
