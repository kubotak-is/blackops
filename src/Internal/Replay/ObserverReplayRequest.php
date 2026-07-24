<?php

declare(strict_types=1);

namespace BlackOps\Internal\Replay;

use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use DateTimeImmutable;

final readonly class ObserverReplayRequest
{
    /** @param list<string> $targetNames */
    public function __construct(
        public PostgreSqlObserverReplaySelector $selector,
        public array $targetNames,
        public string $checkpoint,
        public string $actor,
        public string $reason,
        public ?DateTimeImmutable $now = null,
        public ?int $batchSize = null,
    ) {}

    public function hasUnsafeActorOrReason(): bool
    {
        return self::unsafeText($this->actor) || self::unsafeText($this->reason);
    }

    private static function unsafeText(string $value): bool
    {
        return trim($value) === '' || strlen($value) > 256 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }
}
