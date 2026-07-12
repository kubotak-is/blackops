<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class RetentionPurgeResult
{
    public function __construct(
        private RetentionPlan $plan,
        private int $transportPayloadsPurged,
        private int $deadLettersDeleted,
        private int $outcomesDeleted = 0,
        private int $journalsDeleted = 0,
    ) {
        if ($transportPayloadsPurged < 0 || $deadLettersDeleted < 0 || $outcomesDeleted < 0 || $journalsDeleted < 0) {
            throw new InvalidArgumentException('Retention purge result counts must not be negative.');
        }
    }

    public function plan(): RetentionPlan
    {
        return $this->plan;
    }

    public function transportPayloadsPurged(): int
    {
        return $this->transportPayloadsPurged;
    }

    public function deadLettersDeleted(): int
    {
        return $this->deadLettersDeleted;
    }

    public function outcomesDeleted(): int
    {
        return $this->outcomesDeleted;
    }

    public function journalsDeleted(): int
    {
        return $this->journalsDeleted;
    }

    public function totalAffected(): int
    {
        return (
            $this->transportPayloadsPurged + $this->deadLettersDeleted + $this->outcomesDeleted + $this->journalsDeleted
        );
    }
}
