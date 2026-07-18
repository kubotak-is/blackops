<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Internal\Diagnostics\DiagnosticsSafeActor;
use BlackOps\Internal\Diagnostics\OperationDiagnostics;
use JsonException;

final readonly class OperationInspectJsonEncoder
{
    /** @throws JsonException */
    public function encode(OperationDiagnostics $diagnostics): string
    {
        return json_encode([
            'schemaVersion' => 1,
            'status' => 'found',
            'operation' => [
                'operationId' => $diagnostics->identity->operationId,
                'type' => $diagnostics->identity->type,
                'schemaVersion' => $diagnostics->identity->schemaVersion,
                'strategy' => $diagnostics->identity->strategy,
                'correlationId' => $diagnostics->identity->correlationId,
                'causationId' => $diagnostics->identity->causationId,
                'actors' => $diagnostics->identity->actors === null
                    ? null
                    : [
                        'origin' => $this->actor($diagnostics->identity->actors->origin),
                        'authorization' => $this->actor($diagnostics->identity->actors->authorization),
                        'execution' => $this->actor($diagnostics->identity->actors->execution),
                    ],
            ],
            'state' => [
                'current' => $diagnostics->state->current->value,
                'terminal' => $diagnostics->state->terminal,
                'source' => $diagnostics->state->source,
            ],
            'availability' => [
                'transportPayload' => $diagnostics->availability->transportPayload->value,
                'journal' => $diagnostics->availability->journal->value,
                'outcome' => $diagnostics->availability->outcome->value,
                'deadLetter' => $diagnostics->availability->deadLetter->value,
            ],
            'timeline' => array_map(static fn($entry): array => [
                'sequence' => $entry->sequence,
                'event' => $entry->event,
                'occurredAt' => $entry->occurredAt,
                'attemptId' => $entry->attemptId,
                'attemptNumber' => $entry->attemptNumber,
                'data' => (object) $entry->data,
            ], $diagnostics->timeline),
            'attempts' => array_map(static fn($attempt): array => [
                'attemptId' => $attempt->attemptId,
                'number' => $attempt->number,
                'startedAt' => $attempt->startedAt,
                'events' => $attempt->events,
            ], $diagnostics->attempts),
            'outcome' => $diagnostics->outcome === null
                ? null
                : [
                    'type' => $diagnostics->outcome->type,
                    'completedAt' => $diagnostics->outcome->completedAt,
                    'source' => $diagnostics->outcome->source,
                    'data' => (object) $diagnostics->outcome->data,
                ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /** @return null|array{id: string, type: string} */
    private function actor(?DiagnosticsSafeActor $actor): ?array
    {
        return (
            $actor === null
                ? null
                : [
                    'id' => $actor->id,
                    'type' => $actor->type,
                ]
        );
    }
}
