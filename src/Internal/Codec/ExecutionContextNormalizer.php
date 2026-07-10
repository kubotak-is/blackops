<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

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
}
