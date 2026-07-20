<?php

declare(strict_types=1);

namespace App\Domain\Board;

final readonly class DigestService
{
    public function __construct(
        private DigestRepository $repository,
        private BoardClock $clock,
        private BoardIdGenerator $identifiers,
    ) {}

    public function generate(string $requestedUserId, IsoWeek $week): GeneratedDigest
    {
        $snapshot = $this->repository->snapshot($week);
        $digest = new GeneratedDigest(
            $this->identifiers->generate(),
            $requestedUserId,
            $week->value(),
            sprintf(
                'Weekly digest for %s: %d %s and %d %s.',
                $week->value(),
                $snapshot->postCount,
                $snapshot->postCount === 1 ? 'post' : 'posts',
                $snapshot->commentCount,
                $snapshot->commentCount === 1 ? 'comment' : 'comments',
            ),
            $snapshot->postCount,
            $snapshot->commentCount,
            $this->clock->now(),
        );
        $this->repository->save($digest);

        return $digest;
    }

    public function show(string $digestId, string $requestedUserId): GeneratedDigest
    {
        if (!BoardId::isValid($digestId)) {
            throw new DigestNotFound();
        }

        return $this->repository->findOwned($digestId, $requestedUserId) ?? throw new DigestNotFound();
    }
}
