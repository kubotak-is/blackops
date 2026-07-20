<?php

declare(strict_types=1);

namespace App\Domain\Board;

interface DigestRepository
{
    public function snapshot(IsoWeek $week): DigestSnapshot;

    public function save(GeneratedDigest $digest): void;

    public function findOwned(string $digestId, string $requestedUserId): ?GeneratedDigest;
}
