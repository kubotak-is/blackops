<?php

declare(strict_types=1);

namespace App\Infrastructure\Deferred;

use App\Feature\Digest\DigestAttemptGate;

final readonly class NoOpDigestAttemptGate implements DigestAttemptGate
{
    public function beforeGeneration(int $attemptNumber): void
    {
        if ($attemptNumber < 1) {
            throw new \LogicException('Digest attempt number must be positive.');
        }
    }
}
