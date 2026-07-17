<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class PostgreSqlJournalRecordCodec
{
    public function __construct(
        private TimeCodec $time = new TimeCodec(),
        private PostgreSqlJournalDataCodec $data = new PostgreSqlJournalDataCodec(),
        private PostgreSqlJson $json = new PostgreSqlJson(),
    ) {}

    public function encode(JournalRecord $record): string
    {
        return $this->json->encode([
            'recordId' => $record->recordId->toString(),
            'schemaVersion' => $record->schemaVersion,
            'event' => $record->event->value,
            'occurredAt' => $this->time->format($record->occurredAt),
            'sequence' => $record->sequence,
            'operation' => $this->encodeOperation($record->operation),
            'attempt' => $record->attempt === null ? null : $this->encodeAttempt($record->attempt),
            'data' => $this->data->encode($record->data),
        ]);
    }

    public function decode(string $payload): JournalRecord
    {
        $record = $this->json->decode($payload);

        return new JournalRecord(
            JournalRecordId::fromString($this->json->string($record, 'recordId')),
            $this->json->int($record, 'schemaVersion'),
            JournalEvent::from($this->json->string($record, 'event')),
            new DateTimeImmutable($this->json->string($record, 'occurredAt')),
            $this->json->int($record, 'sequence'),
            $this->decodeOperation($this->json->array($record, 'operation')),
            $this->decodeOptionalAttempt($record),
            $this->data->decode($this->json->array($record, 'data')),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function encodeOperation(JournalOperation $operation): array
    {
        return [
            'id' => $operation->id->toString(),
            'type' => $operation->type,
            'schemaVersion' => $operation->schemaVersion,
            'strategy' => $operation->strategy,
            'correlationId' => $operation->correlationId->toString(),
            'causationId' => $operation->causationId?->toString(),
            'actors' => $this->encodeActors($operation->actorContext),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function encodeActors(?ActorContext $actors): ?array
    {
        if ($actors === null) {
            return null;
        }
        /** @var callable(?ActorRef): (array{id: string, type: string}|null) $encode */
        $encode = static fn(?ActorRef $actor): ?array => (
            $actor === null ? null : ['id' => $actor->id(), 'type' => $actor->type()]
        );

        return [
            'origin' => $encode($actors->origin()),
            'authorization' => $encode($actors->authorization()),
            'execution' => $encode($actors->execution()),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function encodeAttempt(JournalAttempt $attempt): array
    {
        return [
            'id' => $attempt->id->toString(),
            'number' => $attempt->number,
            'startedAt' => $this->time->format($attempt->startedAt),
        ];
    }

    private function decodeOperation(array $operation): JournalOperation
    {
        return new JournalOperation(
            OperationId::fromString($this->json->string($operation, 'id')),
            $this->json->string($operation, 'type'),
            $this->json->int($operation, 'schemaVersion'),
            $this->json->string($operation, 'strategy'),
            CorrelationId::fromString($this->json->string($operation, 'correlationId')),
            $this->decodeCausationId($operation),
            $this->decodeActors($operation),
        );
    }

    private function decodeActors(array $operation): ?ActorContext
    {
        /** @var mixed $encodedActors */
        $encodedActors = $operation['actors'] ?? null;
        if ($encodedActors === null) {
            return null;
        }

        if (!is_array($encodedActors)) {
            throw new RuntimeException('Stored journal actor context must be an object.');
        }

        $actors = $encodedActors;
        $fields = array_keys($actors);
        sort($fields);
        if ($fields !== ['authorization', 'execution', 'origin']) {
            throw new RuntimeException('Stored journal actor context contains unknown or missing fields.');
        }
        $decode = function (string $key) use ($actors): ?ActorRef {
            /** @var mixed $actor */
            $actor = $actors[$key];
            if ($actor === null) {
                return null;
            }

            if (!is_array($actor)) {
                throw new RuntimeException('Stored journal actor must be an object or null.');
            }

            $fields = array_keys($actor);
            sort($fields);
            if ($fields !== ['id', 'type']) {
                throw new RuntimeException('Stored journal actor contains unknown or missing fields.');
            }

            try {
                $id = $this->json->string($actor, 'id');
                $type = $this->json->string($actor, 'type');
                if ($id === '' || $type === '' || trim($id) !== $id || trim($type) !== $type) {
                    throw new RuntimeException('Stored journal actor is invalid.');
                }

                return new ActorRef($id, $type);
            } catch (Throwable $exception) {
                throw new RuntimeException('Stored journal actor is invalid.', previous: $exception);
            }
        };
        $execution = $decode('execution');
        if ($execution === null) {
            throw new RuntimeException('Stored journal actor context requires an execution actor.');
        }

        return new ActorContext($decode('origin'), $decode('authorization'), $execution);
    }

    private function decodeOptionalAttempt(array $record): ?JournalAttempt
    {
        if (($record['attempt'] ?? null) === null) {
            return null;
        }

        $attempt = $this->json->array($record, 'attempt');

        return new JournalAttempt(
            AttemptId::fromString($this->json->string($attempt, 'id')),
            $this->json->int($attempt, 'number'),
            new DateTimeImmutable($this->json->string($attempt, 'startedAt')),
        );
    }

    private function decodeCausationId(array $operation): ?CausationId
    {
        if (!array_key_exists('causationId', $operation) || $operation['causationId'] === null) {
            return null;
        }

        if (!is_string($operation['causationId'])) {
            throw new \RuntimeException('Stored causation identifier must be a string.');
        }

        return CausationId::fromString($this->json->string($operation, 'causationId'));
    }
}
