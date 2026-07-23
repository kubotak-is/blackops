<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\AttemptContext;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Time\TimeCodec;

final readonly class ExecutionContextNormalizer
{
    public function __construct(
        private TimeCodec $time = new TimeCodec(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function normalize(ExecutionContext $context): array
    {
        $deadline = $context->deadline();

        return [
            'operation_id' => $context->operationId()->toString(),
            'received_at' => $this->time->format($context->receivedAt()),
            'correlation_id' => $context->correlationId()->toString(),
            'causation_id' => $context->causationId()?->toString(),
            'attempt' => $this->normalizeAttempt($context->attempt()),
            'deadline' => $deadline === null ? null : $this->time->format($deadline),
            'actors' => $this->normalizeActors($context->actorContext()),
            'idempotency_key_hash' => $this->normalizeIdempotencyKeyHash($context),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeAttempt(?AttemptContext $attempt): ?array
    {
        if ($attempt === null) {
            return null;
        }

        return [
            'id' => $attempt->id()->toString(),
            'number' => $attempt->number(),
            'started_at' => $this->time->format($attempt->startedAt()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeActors(?ActorContext $actors): ?array
    {
        if ($actors === null) {
            return null;
        }

        return [
            'origin' => $this->normalizeActor($actors->origin()),
            'authorization' => $this->normalizeActor($actors->authorization()),
            'execution' => $this->normalizeActor($actors->execution()),
        ];
    }

    /**
     * @return array{version: int, digest: string}|null
     */
    private function normalizeIdempotencyKeyHash(ExecutionContext $context): ?array
    {
        $hash = $context->idempotencyKeyHash();

        if ($hash === null) {
            return null;
        }

        return ['version' => $hash->version(), 'digest' => $hash->digest()];
    }

    /**
     * @return array{id: string, type: string}|null
     */
    private function normalizeActor(?ActorRef $actor): ?array
    {
        if ($actor === null) {
            return null;
        }

        return ['id' => $actor->id(), 'type' => $actor->type()];
    }
}
