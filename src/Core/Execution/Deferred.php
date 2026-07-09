<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class Deferred implements ExecutionStrategy {}
