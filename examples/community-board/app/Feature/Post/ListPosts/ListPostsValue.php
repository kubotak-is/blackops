<?php

declare(strict_types=1);

namespace App\Feature\Post\ListPosts;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Http\Attribute\FromQuery;

final readonly class ListPostsValue implements OperationValue
{
    public function __construct(
        #[FromQuery]
        #[Range(min: 1, max: 10000)]
        public int $page = 1,
        #[FromQuery]
        #[Range(min: 1, max: 50)]
        public int $perPage = 20,
    ) {}
}
