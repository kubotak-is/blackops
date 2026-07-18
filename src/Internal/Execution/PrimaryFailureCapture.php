<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use Throwable;

final class PrimaryFailureCapture
{
    private ?Throwable $failure = null;

    public function capture(Throwable $failure): void
    {
        $this->failure ??= $failure;
    }

    public function getOr(Throwable $fallback): Throwable
    {
        return $this->failure ?? $fallback;
    }
}
