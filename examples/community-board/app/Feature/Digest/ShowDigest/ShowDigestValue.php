<?php

declare(strict_types=1);

namespace App\Feature\Digest\ShowDigest;

use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromPath;

final readonly class ShowDigestValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $digestId,
    ) {}
}
