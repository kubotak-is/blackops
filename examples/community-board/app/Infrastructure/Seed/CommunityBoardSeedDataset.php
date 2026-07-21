<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use DateTimeImmutable;
use DateTimeZone;

final readonly class CommunityBoardSeedDataset
{
    public const DEMO_EMAIL = 'ada@blackops.local';
    public const DEMO_DISPLAY_NAME = 'Ada Lovelace';
    public const DEMO_PASSWORD = 'BlackOpsBoardDemo!2026';

    /** @return list<SeedUser> */
    public function users(): array
    {
        return [
            new SeedUser(
                '019b1000-0000-7000-8000-000000000001',
                self::DEMO_EMAIL,
                self::DEMO_DISPLAY_NAME,
                self::DEMO_PASSWORD,
                $this->time('2026-07-18T09:00:00.000000Z'),
            ),
            new SeedUser(
                '019b1000-0000-7000-8000-000000000002',
                'grace@blackops.local',
                'Grace Hopper',
                'BlackOpsBoardGrace!2026',
                $this->time('2026-07-18T09:05:00.000000Z'),
            ),
            new SeedUser(
                '019b1000-0000-7000-8000-000000000003',
                'linus@blackops.local',
                'Linus Torvalds',
                'BlackOpsBoardLinus!2026',
                $this->time('2026-07-18T09:10:00.000000Z'),
            ),
        ];
    }

    /** @return list<SeedPost> */
    public function posts(): array
    {
        return [
            new SeedPost(
                '019b1000-0001-7000-8000-000000000101',
                '019b1000-0000-7000-8000-000000000001',
                'Welcome to BlackOps Board',
                'This seeded thread demonstrates an owner post with replies from other community members.',
                $this->time('2026-07-19T08:00:00.000000Z'),
            ),
            new SeedPost(
                '019b1000-0001-7000-8000-000000000102',
                '019b1000-0000-7000-8000-000000000002',
                'How do you keep operations observable?',
                'Share the smallest set of signals you rely on when a deferred operation needs investigation.',
                $this->time('2026-07-19T10:30:00.000000Z'),
            ),
            new SeedPost(
                '019b1000-0001-7000-8000-000000000103',
                '019b1000-0000-7000-8000-000000000003',
                'Transaction boundary lessons',
                'What design choice has helped you keep database side effects predictable?',
                $this->time('2026-07-20T07:15:00.000000Z'),
            ),
        ];
    }

    /** @return list<SeedComment> */
    public function comments(): array
    {
        return [
            new SeedComment(
                '019b1000-0002-7000-8000-000000000201',
                '019b1000-0001-7000-8000-000000000101',
                '019b1000-0000-7000-8000-000000000002',
                'The separate application and framework boundaries make the example easy to follow.',
                $this->time('2026-07-19T08:20:00.000000Z'),
            ),
            new SeedComment(
                '019b1000-0002-7000-8000-000000000202',
                '019b1000-0001-7000-8000-000000000101',
                '019b1000-0000-7000-8000-000000000003',
                'I also like that the browser only talks to the SvelteKit boundary.',
                $this->time('2026-07-19T08:35:00.000000Z'),
            ),
            new SeedComment(
                '019b1000-0002-7000-8000-000000000203',
                '019b1000-0001-7000-8000-000000000102',
                '019b1000-0000-7000-8000-000000000001',
                'A stable operation identifier and ordered journal events are my starting point.',
                $this->time('2026-07-19T11:00:00.000000Z'),
            ),
            new SeedComment(
                '019b1000-0002-7000-8000-000000000204',
                '019b1000-0001-7000-8000-000000000103',
                '019b1000-0000-7000-8000-000000000002',
                'Keeping ownership checks and writes in one domain service has worked well for me.',
                $this->time('2026-07-20T08:00:00.000000Z'),
            ),
        ];
    }

    private function time(string $value): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new DateTimeZone('UTC'));
        if (!$time instanceof DateTimeImmutable) {
            throw new \LogicException('Seed timestamp is invalid.');
        }

        return $time;
    }
}
