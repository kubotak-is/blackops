<?php

declare(strict_types=1);

namespace App\Feature\Digest;

use BlackOps\Core\Supervision\RetryableException;
use RuntimeException;

final class DigestGenerationTemporarilyUnavailable extends RuntimeException implements RetryableException
{
    public function __construct()
    {
        parent::__construct('Digest generation is temporarily unavailable.');
    }
}
