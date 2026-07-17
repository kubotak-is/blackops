<?php

declare(strict_types=1);

namespace BlackOps\Database\Exception;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class TransactionException extends RuntimeException {}
