<?php

declare(strict_types=1);

namespace BlackOps\Core\Outbox;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class OutboxStatusSnapshot
{
    public const int SCHEMA_VERSION = 1;
    public const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        public DateTimeImmutable $observedAt,
        public int $pendingReady,
        public int $pendingDelayed,
        public int $retryReady,
        public int $retryDelayed,
        public int $leasedActive,
        public int $leasedExpired,
        public int $deadLettered,
        public ?int $oldestReady,
        public ?int $oldestLeasedExpired,
        public ?int $oldestDeadLettered,
    ) {
        foreach ([
            $pendingReady,
            $pendingDelayed,
            $retryReady,
            $retryDelayed,
            $leasedActive,
            $leasedExpired,
            $deadLettered,
        ] as $count) {
            $this->nonNegativeSafeInteger($count);
        }
        $this->ageForCount($oldestReady, $pendingReady + $retryReady);
        $this->ageForCount($oldestLeasedExpired, $leasedExpired);
        $this->ageForCount($oldestDeadLettered, $deadLettered);
    }

    /** @return array{agesSeconds: array{oldestDeadLettered: ?int, oldestLeasedExpired: ?int, oldestReady: ?int}, counts: array{deadLettered: int, leasedActive: int, leasedExpired: int, pendingDelayed: int, pendingReady: int, retryDelayed: int, retryReady: int}, observedAt: string, schemaVersion: int, scope: string} */
    public function toArray(): array
    {
        return [
            'agesSeconds' => [
                'oldestDeadLettered' => $this->oldestDeadLettered,
                'oldestLeasedExpired' => $this->oldestLeasedExpired,
                'oldestReady' => $this->oldestReady,
            ],
            'counts' => [
                'deadLettered' => $this->deadLettered,
                'leasedActive' => $this->leasedActive,
                'leasedExpired' => $this->leasedExpired,
                'pendingDelayed' => $this->pendingDelayed,
                'pendingReady' => $this->pendingReady,
                'retryDelayed' => $this->retryDelayed,
                'retryReady' => $this->retryReady,
            ],
            'observedAt' => $this->observedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'schemaVersion' => self::SCHEMA_VERSION,
            'scope' => 'installation',
        ];
    }

    private function ageForCount(?int $age, int $count): void
    {
        if (($count === 0) !== ($age === null)) {
            throw new InvalidArgumentException('Outbox status age nullability does not match its count.');
        }
        if ($age !== null) {
            $this->nonNegativeSafeInteger($age);
        }
    }

    private function nonNegativeSafeInteger(int $value): void
    {
        if ($value < 0 || $value > self::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException('Outbox status integer is outside the safe range.');
        }
    }
}
