<?php

declare(strict_types=1);

namespace BlackOps\Core\Queue;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class QueueStatusSnapshot
{
    public const int SCHEMA_VERSION = 1;
    public const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        public DateTimeImmutable $observedAt,
        public int $acceptedReady,
        public int $acceptedDelayed,
        public int $retryReady,
        public int $retryDelayed,
        public int $runningActive,
        public int $runningExpired,
        public int $supervising,
        public int $deadLettered,
        public ?int $oldestReady,
        public ?int $oldestRunningExpired,
        public ?int $oldestSupervising,
        public ?int $oldestDeadLettered,
    ) {
        foreach ([
            $acceptedReady,
            $acceptedDelayed,
            $retryReady,
            $retryDelayed,
            $runningActive,
            $runningExpired,
            $supervising,
            $deadLettered,
        ] as $count) {
            $this->nonNegativeSafeInteger($count);
        }
        $this->ageForCount($oldestReady, $acceptedReady + $retryReady);
        $this->ageForCount($oldestRunningExpired, $runningExpired);
        $this->ageForCount($oldestSupervising, $supervising);
        $this->ageForCount($oldestDeadLettered, $deadLettered);
    }

    /** @return array{agesSeconds: array{oldestDeadLettered: ?int, oldestReady: ?int, oldestRunningExpired: ?int, oldestSupervising: ?int}, counts: array{acceptedDelayed: int, acceptedReady: int, deadLettered: int, retryDelayed: int, retryReady: int, runningActive: int, runningExpired: int, supervising: int}, observedAt: string, schemaVersion: int, scope: string} */
    public function toArray(): array
    {
        return [
            'agesSeconds' => [
                'oldestDeadLettered' => $this->oldestDeadLettered,
                'oldestReady' => $this->oldestReady,
                'oldestRunningExpired' => $this->oldestRunningExpired,
                'oldestSupervising' => $this->oldestSupervising,
            ],
            'counts' => [
                'acceptedDelayed' => $this->acceptedDelayed,
                'acceptedReady' => $this->acceptedReady,
                'deadLettered' => $this->deadLettered,
                'retryDelayed' => $this->retryDelayed,
                'retryReady' => $this->retryReady,
                'runningActive' => $this->runningActive,
                'runningExpired' => $this->runningExpired,
                'supervising' => $this->supervising,
            ],
            'observedAt' => $this->observedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'schemaVersion' => self::SCHEMA_VERSION,
            'scope' => 'installation',
        ];
    }

    private function ageForCount(?int $age, int $count): void
    {
        if (($count === 0) !== ($age === null)) {
            throw new InvalidArgumentException('Queue status age nullability does not match its count.');
        }
        if ($age !== null) {
            $this->nonNegativeSafeInteger($age);
        }
    }

    private function nonNegativeSafeInteger(int $value): void
    {
        if ($value < 0 || $value > self::MAX_SAFE_INTEGER) {
            throw new InvalidArgumentException('Queue status integer is outside the safe range.');
        }
    }
}
