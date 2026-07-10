<?php

declare(strict_types=1);

namespace BlackOps\Core\Codec;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class OperationCodecException extends RuntimeException {}
