<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Time\TimeCodec;
use DateTimeImmutable;
use Throwable;

final readonly class ExecutionContextHydrator
{
    public function __construct(
        private JsonObjectReader $reader = new JsonObjectReader(),
        private TimeCodec $time = new TimeCodec(),
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function hydrate(array $context): ExecutionContext
    {
        $attempt = $this->reader->optionalObject($context, 'attempt');

        return new ExecutionContext(
            OperationId::fromString($this->reader->string($context, 'operation_id')),
            $this->parseTime($this->reader->string($context, 'received_at')),
            CorrelationId::fromString($this->reader->string($context, 'correlation_id')),
            $this->optionalCausationId($context, 'causation_id'),
            $attempt === null ? null : $this->hydrateAttempt($attempt),
            $this->optionalTime($context, 'deadline'),
        );
    }

    /**
     * @param array<string, mixed> $attempt
     */
    private function hydrateAttempt(array $attempt): AttemptContext
    {
        return new AttemptContext(
            AttemptId::fromString($this->reader->string($attempt, 'id')),
            $this->reader->int($attempt, 'number'),
            $this->parseTime($this->reader->string($attempt, 'started_at')),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalCausationId(array $data, string $key): ?CausationId
    {
        $value = $this->reader->optionalString($data, $key);

        if ($value === null) {
            return null;
        }

        try {
            return CausationId::fromString($value);
        } catch (Throwable $exception) {
            throw new OperationCodecException('Encoded context contains an invalid identifier.', previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalTime(array $data, string $key): ?DateTimeImmutable
    {
        $value = $this->reader->optionalString($data, $key);

        return $value === null ? null : $this->parseTime($value);
    }

    private function parseTime(string $value): DateTimeImmutable
    {
        try {
            return $this->time->parse($value);
        } catch (Throwable $exception) {
            throw new OperationCodecException('Encoded context contains an invalid time value.', previous: $exception);
        }
    }
}
