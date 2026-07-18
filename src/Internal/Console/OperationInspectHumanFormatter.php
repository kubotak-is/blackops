<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Diagnostics\DiagnosticsSafeActor;
use BlackOps\Internal\Diagnostics\OperationDiagnostics;
use JsonException;

final readonly class OperationInspectHumanFormatter
{
    /** @throws JsonException */
    public function format(OperationDiagnostics $diagnostics): string
    {
        return implode("\n", [
            ...$this->summary($diagnostics),
            ...$this->timeline($diagnostics),
            ...$this->attempts($diagnostics),
            ...$this->outcome($diagnostics),
        ]) . "\n";
    }

    /** @return list<string> */
    private function summary(OperationDiagnostics $diagnostics): array
    {
        return [
            'Operation',
            '  ID: ' . $this->scalar($diagnostics->identity->operationId),
            '  Type: ' . $this->scalar($diagnostics->identity->type),
            '  Strategy: ' . $this->scalar($diagnostics->identity->strategy),
            '  Schema Version: ' . $diagnostics->identity->schemaVersion,
            '  Correlation ID: ' . $this->nullableScalar($diagnostics->identity->correlationId),
            '  Causation ID: ' . $this->nullableScalar($diagnostics->identity->causationId),
            'State',
            '  Current: ' . $this->scalar($diagnostics->state->current->value),
            '  Terminal: ' . ($diagnostics->state->terminal ? 'yes' : 'no'),
            '  Authority Source: ' . $this->scalar($diagnostics->state->source),
            'Availability',
            '  Transport Payload: ' . $this->scalar($diagnostics->availability->transportPayload->value),
            '  Journal: ' . $this->scalar($diagnostics->availability->journal->value),
            '  Outcome: ' . $this->scalar($diagnostics->availability->outcome->value),
            '  Dead Letter: ' . $this->scalar($diagnostics->availability->deadLetter->value),
            'Actors',
            '  Origin: ' . $this->actor($diagnostics->identity->actors?->origin),
            '  Authorization: ' . $this->actor($diagnostics->identity->actors?->authorization),
            '  Execution: ' . $this->actor($diagnostics->identity->actors?->execution),
        ];
    }

    /** @return list<string> */
    private function timeline(OperationDiagnostics $diagnostics): array
    {
        $lines = ['Timeline'];
        if ($diagnostics->timeline === []) {
            $lines[] = '  none';
        }
        foreach ($diagnostics->timeline as $entry) {
            $attempt = $entry->attemptId === null
                ? 'none'
                : sprintf('%s (#%s)', $this->scalar($entry->attemptId), $entry->attemptNumber ?? 'unknown');
            $lines[] = sprintf(
                '  #%d %s %s | Attempt: %s | Data: %s',
                $entry->sequence,
                $this->scalar($entry->occurredAt),
                $this->scalar($entry->event),
                $attempt,
                $this->data($entry->data),
            );
        }

        return $lines;
    }

    /** @return list<string> */
    private function attempts(OperationDiagnostics $diagnostics): array
    {
        $lines = ['Attempts'];
        if ($diagnostics->attempts === []) {
            $lines[] = '  none';
        }
        foreach ($diagnostics->attempts as $attempt) {
            $lines[] = sprintf(
                '  #%d %s | Started At: %s | Sequences: %s',
                $attempt->number,
                $this->scalar($attempt->attemptId),
                $this->scalar($attempt->startedAt),
                implode(', ', $attempt->events),
            );
        }

        return $lines;
    }

    /** @return list<string> */
    private function outcome(OperationDiagnostics $diagnostics): array
    {
        $lines = [
            'Outcome',
            '  Availability: ' . $this->scalar($diagnostics->availability->outcome->value),
        ];
        if ($diagnostics->outcome === null) {
            $lines[] = '  Value: none';
        }
        if ($diagnostics->outcome !== null) {
            $lines[] = '  Type: ' . $this->scalar($diagnostics->outcome->type);
            $lines[] = '  Completed At: ' . $this->nullableScalar($diagnostics->outcome->completedAt);
            $lines[] = '  Source: ' . $this->scalar($diagnostics->outcome->source);
            $lines[] = '  Data: ' . $this->data($diagnostics->outcome->data);
        }

        return $lines;
    }

    private function actor(?DiagnosticsSafeActor $actor): string
    {
        return $actor === null ? 'none' : sprintf('%s (%s)', $this->scalar($actor->id), $this->scalar($actor->type));
    }

    /** @param array<string, mixed> $data */
    private function data(array $data): string
    {
        return json_encode((object) $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function nullableScalar(?string $value): string
    {
        return $value === null ? 'none' : $this->scalar($value);
    }

    private function scalar(string $value): string
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $escaped = substr(string: $encoded, offset: 1, length: -1);

        return (
            preg_replace_callback(
                '/\p{Cc}/u',
                static fn(array $matches): string => sprintf('\\u%04x', mb_ord(string: $matches[0], encoding: 'UTF-8')),
                $escaped,
            ) ?? throw new JsonException('Failed to escape terminal control characters.')
        );
    }
}
