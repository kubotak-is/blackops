<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use ReflectionClass;
use ReflectionProperty;

final readonly class SensitiveProjectionFilter
{
    private const MASK = '[masked]';

    /** @var list<class-string> */
    private const FRAMEWORK_IDENTIFIER_TYPES = [
        AttemptId::class,
        CausationId::class,
        CorrelationId::class,
        JournalRecordId::class,
        OperationId::class,
        OutboxRecordId::class,
        RetentionHoldId::class,
        RetentionPurgeAuditId::class,
    ];

    private SensitiveValueHasher $hasher;

    private SensitiveKeyMatcher $keys;

    /**
     * @param list<string> $reservedKeyPatterns
     */
    public function __construct(
        ?string $hmacKey = null,
        array $reservedKeyPatterns = ['password', 'token', 'secret'],
        ?SensitiveValueHasher $hasher = null,
        ?SensitiveKeyMatcher $keys = null,
    ) {
        $this->hasher = $hasher ?? new SensitiveValueHasher($hmacKey);
        $this->keys = $keys ?? new SensitiveKeyMatcher($reservedKeyPatterns);
    }

    /**
     * @return array<string, mixed>
     */
    public function projectObject(object $value): array
    {
        $projection = [];

        foreach (new ReflectionClass($value)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $sensitive = $this->sensitiveAttribute($property);

            if ($sensitive?->mode === SensitiveMode::Omit) {
                continue;
            }

            $projection[$property->getName()] = $this->projectValue($property->getValue($value), $sensitive?->mode);
        }

        return $projection;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    public function projectArray(array $values): array
    {
        if (array_is_list($values)) {
            return array_map($this->projectValue(...), $values);
        }

        $projection = [];

        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                continue;
            }

            if ($this->keys->matches($key)) {
                continue;
            }

            $projection[$key] = $this->projectValue($values[$key]);
        }

        return $projection;
    }

    private function sensitiveAttribute(ReflectionProperty $property): ?Sensitive
    {
        $attributes = $property->getAttributes(Sensitive::class);

        return ($attributes[0] ?? null)?->newInstance();
    }

    private function projectValue(mixed $value, ?SensitiveMode $mode = null): mixed
    {
        if ($mode === SensitiveMode::Mask) {
            return self::MASK;
        }

        if ($mode === SensitiveMode::Hash) {
            return $this->hasher->hash($value);
        }

        if (is_array($value)) {
            return $this->projectArray($value);
        }

        if (is_object($value)) {
            return $this->projectObjectValue($value);
        }

        return $value;
    }

    private function projectObjectValue(object $value): object|array
    {
        if ($this->isWireScalar($value)) {
            return $value;
        }

        $projection = $this->projectObject($value);

        return $projection === [] ? new \stdClass() : $projection;
    }

    private function isWireScalar(object $value): bool
    {
        return in_array(
            true,
            [
                $value instanceof \DateTimeInterface,
                in_array($value::class, self::FRAMEWORK_IDENTIFIER_TYPES, strict: true),
            ],
            strict: true,
        );
    }
}
