<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use ReflectionClass;
use ReflectionProperty;

final readonly class SensitiveProjectionFilter
{
    private const MASK = '[masked]';

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

            if ($this->keyMatcher()->matches($key)) {
                continue;
            }

            $projection[$key] = $this->projectValue($values[$key]);
        }

        return $projection;
    }

    private function sensitiveAttribute(ReflectionProperty $property): ?Sensitive
    {
        $attributes = $property->getAttributes(Sensitive::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function projectValue(mixed $value, ?SensitiveMode $mode = null): mixed
    {
        if ($mode === SensitiveMode::Mask) {
            return self::MASK;
        }

        if ($mode === SensitiveMode::Hash) {
            return $this->valueHasher()->hash($value);
        }

        if (is_array($value)) {
            return $this->projectArray($value);
        }

        if (is_object($value)) {
            return $this->projectObject($value);
        }

        return $value;
    }

    private function valueHasher(): SensitiveValueHasher
    {
        return $this->hasher;
    }

    private function keyMatcher(): SensitiveKeyMatcher
    {
        return $this->keys;
    }
}
