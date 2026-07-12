<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromHeader;

final readonly class WelcomeValue implements OperationValue
{
    public function __construct(
        #[FromHeader('X-Sample-Token')]
        #[Sensitive(SensitiveMode::Mask)]
        public string $sampleToken,
    ) {}
}
