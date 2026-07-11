<?php

declare(strict_types=1);

namespace BlackOps\Outcome\Exception;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class OutcomeStoreException extends RuntimeException {}
