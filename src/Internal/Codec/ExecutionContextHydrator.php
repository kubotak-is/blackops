<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
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
    private const string RESERVED_SECURITY_FIELD_PATTERN = '/(?:^|[^a-z0-9])(?:password|token|secret|credential|session|api[_-]?key|bearer|jwt|claims?|roles?|permissions?)(?:[^a-z0-9]|$)/i';

    public function __construct(
        private JsonObjectReader $reader = new JsonObjectReader(),
        private TimeCodec $time = new TimeCodec(),
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function hydrate(array $context): ExecutionContext
    {
        $this->assertNoReservedSecurityFields($context);

        $attempt = $this->reader->optionalObject($context, 'attempt');

        return new ExecutionContext(
            OperationId::fromString($this->reader->string($context, 'operation_id')),
            $this->parseTime($this->reader->string($context, 'received_at')),
            CorrelationId::fromString($this->reader->string($context, 'correlation_id')),
            $this->optionalCausationId($context, 'causation_id'),
            $attempt === null ? null : $this->hydrateAttempt($attempt),
            $this->optionalTime($context, 'deadline'),
            $this->hydrateActors($context),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function assertNoReservedSecurityFields(array $context): void
    {
        $reserved = array_filter(
            array_keys($context),
            static fn(string $field): bool => (
                preg_match(
                    self::RESERVED_SECURITY_FIELD_PATTERN,
                    preg_replace(
                        pattern: ['/(?<=[a-z0-9])(?=[A-Z])/', '/(?<=[A-Z])(?=[A-Z][a-z])/'],
                        replacement: '_',
                        subject: $field,
                    ) ?? $field,
                ) === 1
            ),
        );

        if ($reserved !== []) {
            throw new OperationCodecException('Encoded context contains a reserved security field.');
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function hydrateActors(array $context): ?ActorContext
    {
        $actors = $this->reader->optionalObject($context, 'actors');

        if ($actors === null) {
            return null;
        }

        $this->assertFields($actors, ['origin', 'authorization', 'execution']);

        $origin = $this->reader->optionalObject($actors, 'origin');
        $authorization = $this->reader->optionalObject($actors, 'authorization');
        $execution = $this->reader->optionalObject($actors, 'execution');

        if ($execution === null) {
            throw new OperationCodecException('Encoded actor context is missing its execution actor.');
        }

        return new ActorContext(
            $origin === null ? null : $this->hydrateActor($origin),
            $authorization === null ? null : $this->hydrateActor($authorization),
            $this->hydrateActor($execution),
        );
    }

    /**
     * @param array<string, mixed> $actor
     */
    private function hydrateActor(array $actor): ActorRef
    {
        $this->assertFields($actor, ['id', 'type']);

        try {
            return new ActorRef($this->reader->string($actor, 'id'), $this->reader->string($actor, 'type'));
        } catch (Throwable $exception) {
            throw new OperationCodecException('Encoded context contains an invalid actor.', previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $expected
     */
    private function assertFields(array $data, array $expected): void
    {
        $fields = array_keys($data);
        sort($fields);
        sort($expected);

        if ($fields !== $expected) {
            throw new OperationCodecException('Encoded actor context contains unknown or missing fields.');
        }
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
