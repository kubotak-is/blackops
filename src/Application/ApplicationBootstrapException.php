<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class ApplicationBootstrapException extends RuntimeException {}
