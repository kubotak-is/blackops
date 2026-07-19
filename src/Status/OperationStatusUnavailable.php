<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class OperationStatusUnavailable implements OperationStatusResult {}
