<?php

declare(strict_types=1);

namespace BlackOps\Core\Supervision;

use BlackOps\Core\Attribute\PublicApi;
use Throwable;

#[PublicApi]
interface RetryableException extends Throwable {}
