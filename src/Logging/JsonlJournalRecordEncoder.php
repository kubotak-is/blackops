<?php

declare(strict_types=1);

namespace BlackOps\Logging;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\ObservedJournalRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use Stringable;

#[PublicApi]
final readonly class JsonlJournalRecordEncoder
{
    /**
     * @throws JsonException
     */
    public function encode(ObservedJournalRecord $record): string
    {
        return json_encode($this->record($record), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function record(ObservedJournalRecord $record): array
    {
        return [
            'schemaVersion' => $record->schemaVersion,
            'kind' => 'journal',
            'event' => $record->event->value,
            'occurredAt' => $this->time($record->occurredAt),
            'sequence' => $record->sequence,
            'operation' => $this->operation($record->operation),
            'attempt' => $record->attempt === null ? null : $this->attempt($record->attempt),
            'data' => $this->normalize($record->data),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(JournalOperation $operation): array
    {
        return [
            'id' => (string) $operation->id,
            'type' => $operation->type,
            'schemaVersion' => $operation->schemaVersion,
            'strategy' => $operation->strategy,
            'correlationId' => (string) $operation->correlationId,
            'causationId' => $operation->causationId === null ? null : (string) $operation->causationId,
            'actors' => $this->actors($operation->actorContext),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actors(?ActorContext $actors): ?array
    {
        if ($actors === null) {
            return null;
        }

        return [
            'origin' => $this->actor($actors->origin()),
            'authorization' => $this->actor($actors->authorization()),
            'execution' => $this->actor($actors->execution()),
        ];
    }

    /**
     * @return array{id: string, type: string}|null
     */
    private function actor(?ActorRef $actor): ?array
    {
        return $actor === null ? null : ['id' => $actor->id(), 'type' => $actor->type()];
    }

    /**
     * @return array<string, mixed>
     */
    private function attempt(JournalAttempt $attempt): array
    {
        return [
            'id' => (string) $attempt->id,
            'number' => $attempt->number,
            'startedAt' => $this->time($attempt->startedAt),
        ];
    }

    private function time(DateTimeInterface $time): string
    {
        return DateTimeImmutable::createFromInterface($time)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    private function normalize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->time($value);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            return $this->array($value);
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function array(array $value): array
    {
        $normalized = [];

        foreach (array_keys($value) as $key) {
            $normalized[$key] = $this->normalize($value[$key]);
        }

        return $normalized;
    }
}
