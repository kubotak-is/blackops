<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Order\CreateOrder;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;

final readonly class OrderOwner implements OutcomeData
{
    public function __construct(
        public string $displayName,
        public string $id,
    ) {}
}

final readonly class OrderLine implements OutcomeData
{
    public function __construct(
        public ?OrderOwner $owner,
        public string $productId,
        public int $quantity,
    ) {}
}

final readonly class EmptyMetadata implements OutcomeData {}

final readonly class OrderCreated implements Outcome
{
    /**
     * @param list<EmptyMetadata> $emptyMetadataItems
     * @param list<OrderLine> $lines
     */
    public function __construct(
        public EmptyMetadata $__proto__,
        public EmptyMetadata $emptyMetadata,
        #[ListOf(EmptyMetadata::class)]
        public array $emptyMetadataItems,
        public ?EmptyMetadata $optionalEmptyMetadata,
        public string $orderId,
        public int $sequence,
        public float $total,
        public bool $active,
        public OrderOwner $owner,
        public ?OrderOwner $optionalOwner,
        #[ListOf(OrderLine::class)]
        public array $lines,
    ) {}
}
