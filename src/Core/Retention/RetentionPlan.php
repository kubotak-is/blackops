<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class RetentionPlan
{
    /**
     * @var list<RetentionPlanItem>
     */
    private array $items;

    /**
     * @param iterable<RetentionPlanItem> $items
     */
    public function __construct(iterable $items)
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = $item;
        }

        $this->items = $list;
    }

    /**
     * @return list<RetentionPlanItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return list<RetentionPlanItem>
     */
    public function forTarget(RetentionTarget $target): array
    {
        return array_values(array_filter(
            $this->items,
            static fn(RetentionPlanItem $item): bool => $item->target() === $target,
        ));
    }
}
