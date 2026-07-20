<?php

declare(strict_types=1);

namespace App\Feature\Digest\GenerateWeeklyDigest;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Regex;
use BlackOps\Http\Attribute\FromBody;

final readonly class GenerateWeeklyDigestValue implements OperationValue
{
    public function __construct(
        #[FromBody]
        #[NotBlank]
        #[Regex('/^[0-9]{4}-W(?:0[1-9]|[1-4][0-9]|5[0-3])$/D')]
        public string $week,
    ) {}
}
