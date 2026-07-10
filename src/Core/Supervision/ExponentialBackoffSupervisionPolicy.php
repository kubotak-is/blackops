<?php

declare(strict_types=1);

namespace BlackOps\Core\Supervision;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;
use Throwable;

#[PublicApi]
final readonly class ExponentialBackoffSupervisionPolicy implements SupervisionPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $initialDelayMilliseconds = 1_000,
        private float $multiplier = 2.0,
        private int $maximumDelayMilliseconds = 60_000,
        private float $jitterRatio = 0.2,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('Maximum attempts must be greater than or equal to one.');
        }

        if ($initialDelayMilliseconds < 0) {
            throw new InvalidArgumentException('Initial delay must be greater than or equal to zero.');
        }

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException('Backoff multiplier must be greater than or equal to one.');
        }

        if ($maximumDelayMilliseconds < $initialDelayMilliseconds) {
            throw new InvalidArgumentException('Maximum delay must not be smaller than the initial delay.');
        }

        if ($jitterRatio < 0.0 || $jitterRatio > 1.0) {
            throw new InvalidArgumentException('Jitter ratio must be between zero and one.');
        }
    }

    public function decide(Throwable $error, AttemptContext $attempt): SupervisionDecision
    {
        if (!$error instanceof RetryableException || $attempt->number() >= $this->maxAttempts) {
            return SupervisionDecision::fail();
        }

        $base = $this->initialDelayMilliseconds * ($this->multiplier ** ($attempt->number() - 1));
        $capped = min($this->maximumDelayMilliseconds, (int) round($base));
        $jittered = $this->applyJitter($capped);

        return SupervisionDecision::retry($jittered);
    }

    private function applyJitter(int $delayMilliseconds): int
    {
        if ($this->jitterRatio === 0.0 || $delayMilliseconds === 0) {
            return $delayMilliseconds;
        }

        $ratio = (random_int(min: -1_000_000, max: 1_000_000) / 1_000_000) * $this->jitterRatio;

        return max(0, (int) round($delayMilliseconds * (1.0 + $ratio)));
    }
}
