<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Order\CreateOrder;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;

final readonly class CreateOrderValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public int $accountId,
        #[FromQuery]
        public bool $active,
        #[FromHeader('X-Trace-ID')]
        #[NotBlank]
        public string $traceId,
        #[FromBody]
        #[NotBlank]
        #[Length(max: 64)]
        public string $reference,
        #[FromBody]
        #[Range(min: 0)]
        public float $amount,
        #[FromBody]
        #[Sensitive]
        public string $secretNote,
        #[FromQuery('q')]
        public ?string $filter = null,
    ) {}
}
