<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class RetentionPolicy
{
    public function __construct(
        private RetentionPeriod $transportPayloadRetention,
        private RetentionPeriod $journalRetention,
        private RetentionPeriod $outcomeRetention,
        private RetentionPeriod $deadLetterRetention,
    ) {}

    public function transportPayloadRetention(): RetentionPeriod
    {
        return $this->transportPayloadRetention;
    }

    public function journalRetention(): RetentionPeriod
    {
        return $this->journalRetention;
    }

    public function outcomeRetention(): RetentionPeriod
    {
        return $this->outcomeRetention;
    }

    public function deadLetterRetention(): RetentionPeriod
    {
        return $this->deadLetterRetention;
    }

    public function forTarget(RetentionTarget $target): RetentionPeriod
    {
        return match ($target) {
            RetentionTarget::TransportPayload => $this->transportPayloadRetention,
            RetentionTarget::Journal => $this->journalRetention,
            RetentionTarget::Outcome => $this->outcomeRetention,
            RetentionTarget::DeadLetter => $this->deadLetterRetention,
        };
    }
}
