<?php

declare(strict_types=1);

namespace BlackOps\Core\Exception;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class DeferredTransportException extends RuntimeException {}
