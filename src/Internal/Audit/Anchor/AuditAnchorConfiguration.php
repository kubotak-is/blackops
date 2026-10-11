<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

final readonly class AuditAnchorConfiguration
{
    public int $leaseDurationSeconds;
    public int $retryDelaySeconds;

    public function __construct(
        #[\SensitiveParameter]
        mixed $leaseDurationSeconds = 300,
        #[\SensitiveParameter]
        mixed $retryDelaySeconds = 300,
    ) {
        try {
            $leaseDuration = self::assertSeconds($leaseDurationSeconds);
            $retryDelay = self::assertSeconds($retryDelaySeconds);
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::ConfigurationInvalid);
        }
        $this->leaseDurationSeconds = $leaseDuration;
        $this->retryDelaySeconds = $retryDelay;
    }

    private static function assertSeconds(#[\SensitiveParameter] mixed $value): int
    {
        if (!is_int($value) || $value < 1 || $value > 86_400) {
            throw new \InvalidArgumentException();
        }
        return $value;
    }
}
