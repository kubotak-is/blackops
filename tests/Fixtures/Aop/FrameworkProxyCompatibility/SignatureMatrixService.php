<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

use BlackOps\Database\Attribute\Transactional;

#[\AllowDynamicProperties]
#[Transactional(connection: 'app')]
class SignatureMatrixService extends \stdClass
{
    public function union(int|string $value): int|string
    {
        return $value;
    }

    public function intersection(\Countable&\IteratorAggregate $value): static
    {
        return $this;
    }

    public function variadic(string $prefix, int ...$values): string
    {
        return $prefix . implode(',', $values);
    }

    public function parentType(): parent
    {
        return new parent();
    }

    public function dnf((\Countable&\IteratorAggregate)|null $value = null): ?string
    {
        return $value === null ? null : 'dnf';
    }

    public function nullable(?string $value = null): ?string
    {
        return $value;
    }

    public function mixedValue(mixed $value = null): mixed
    {
        return $value;
    }

    public function staticReturn(): static
    {
        return $this;
    }

    public function selfReturn(): self
    {
        return $this;
    }

    public function defaults(int $scalar = 3, array $array = ['value'], int $constant = \PHP_INT_SIZE): self
    {
        return $this;
    }

    #[\ReturnTypeWillChange]
    public function unrelated(#[\SensitiveParameter] string $value = 'default'): string
    {
        return $value;
    }
}
