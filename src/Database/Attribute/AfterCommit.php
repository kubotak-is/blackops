<?php

declare(strict_types=1);

namespace BlackOps\Database\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;

#[Attribute(Attribute::TARGET_METHOD)]
#[PublicApi]
final readonly class AfterCommit {}
