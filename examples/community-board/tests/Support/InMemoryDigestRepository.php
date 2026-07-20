<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Board\DigestRepository;
use App\Domain\Board\DigestSnapshot;
use App\Domain\Board\GeneratedDigest;
use App\Domain\Board\IsoWeek;

final class InMemoryDigestRepository implements DigestRepository
{
    /** @var list<GeneratedDigest> */
    public array $digests = [];

    public function __construct(
        public DigestSnapshot $nextSnapshot = new DigestSnapshot(0, 0),
    ) {}

    public function snapshot(IsoWeek $week): DigestSnapshot
    {
        return $this->nextSnapshot;
    }

    public function save(GeneratedDigest $digest): void
    {
        $this->digests[] = $digest;
    }

    public function findOwned(string $digestId, string $requestedUserId): ?GeneratedDigest
    {
        foreach ($this->digests as $digest) {
            if ($digest->id === $digestId && $digest->requestedUserId === $requestedUserId) {
                return $digest;
            }
        }

        return null;
    }
}
