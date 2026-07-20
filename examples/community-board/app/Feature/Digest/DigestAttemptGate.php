<?php

declare(strict_types=1);

namespace App\Feature\Digest;

interface DigestAttemptGate
{
    public function beforeGeneration(int $attemptNumber): void;
}
