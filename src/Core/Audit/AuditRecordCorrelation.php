<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:block-statement
 */
final readonly class AuditRecordCorrelation
{
    /**
     * @param array<string,string|array{number:int,kind:string}|bool|null> $members
     */
    private function __construct(
        ?string $operationId,
        ?string $correlationId,
        ?string $causationId,
        ?AuditRecordAttempt $attempt,
        ?string $traceId,
        ?string $spanId,
        ?bool $sampled,
        array $members,
    ) {
        $this->operationId = $operationId;
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
        $this->attempt = $attempt;
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->sampled = $sampled;
        $this->members = $members;
    }

    public ?string $operationId;
    public ?string $correlationId;
    public ?string $causationId;
    public ?AuditRecordAttempt $attempt;
    public ?string $traceId;
    public ?string $spanId;
    public ?bool $sampled;

    /** @var array<string,string|array{number:int,kind:string}|bool|null> */
    private array $members;

    /** @param array<string,mixed> $members */
    public static function fromArray(array $members): self
    {
        $allowed = ['operationId', 'correlationId', 'causationId', 'attempt', 'traceId', 'spanId', 'sampled'];
        if (array_diff(array_keys($members), $allowed) !== [])
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);

        $operationId = self::stringMember($members, 'operationId');
        $correlationId = self::stringMember($members, 'correlationId');
        $causationId = self::stringMember($members, 'causationId');
        $attempt = self::attemptMember($members);
        $traceId = self::hexMember($members, 'traceId', 32);
        $spanId = self::hexMember($members, 'spanId', 16);
        $sampled = self::sampledMember($members);

        $stored = [];
        foreach ($members as $name => $value) {
            $stored[$name] = match ($name) {
                'operationId' => $operationId,
                'correlationId' => $correlationId,
                'causationId' => $causationId,
                'attempt' => $attempt?->toArray(),
                'traceId' => $traceId,
                'spanId' => $spanId,
                'sampled' => $sampled,
                default => throw new AuditValidationException(AuditValidationFailure::ShapeInvalid),
            };
        }

        /** @var array<string,string|array{number:int,kind:string}|bool|null> $stored */
        return new self($operationId, $correlationId, $causationId, $attempt, $traceId, $spanId, $sampled, $stored);
    }

    /** @return array<string,string|array{number:int,kind:string}|bool|null> */
    public function toArray(): array
    {
        return $this->members;
    }

    private static function stringMember(array $members, string $name): ?string
    {
        if (!array_key_exists($name, $members))
            return null;
        $value = $members[$name];
        if ($value !== null && !is_string($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if ($value !== null)
            AuditValidationException::assertSafeString($value);
        return $value;
    }

    private static function attemptMember(array $members): ?AuditRecordAttempt
    {
        if (!array_key_exists('attempt', $members) || $members['attempt'] === null)
            return null;
        $value = $members['attempt'];
        if (!is_array($value))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        if (
            array_diff(array_keys($value), ['number', 'kind']) !== []
            || array_diff(['number', 'kind'], array_keys($value)) !== []
            || !is_int($value['number'] ?? null)
            || !is_string($value['kind'] ?? null)
        )
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        return new AuditRecordAttempt(
            $value['number'],
            AuditAttemptKind::tryFrom(
                $value['kind'],
            ) ?? throw new AuditValidationException(AuditValidationFailure::SemanticInvalid),
        );
    }

    private static function hexMember(array $members, string $name, int $length): ?string
    {
        $value = self::stringMember($members, $name);
        if ($value === null)
            return null;
        AuditValidationException::assertSafeString($value, $length, $length);
        if (preg_match('/^[a-f0-9]+$/', $value) !== 1)
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        return $value;
    }

    private static function sampledMember(array $members): ?bool
    {
        if (!array_key_exists('sampled', $members) || $members['sampled'] === null)
            return null;
        if (!is_bool($members['sampled']))
            throw new AuditValidationException(AuditValidationFailure::ShapeInvalid);
        return $members['sampled'];
    }
}
