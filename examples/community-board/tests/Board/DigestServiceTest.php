<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\DigestNotFound;
use App\Domain\Board\DigestService;
use App\Domain\Board\DigestSnapshot;
use App\Domain\Board\IsoWeek;
use App\Tests\Support\FrozenBoardClock;
use App\Tests\Support\InMemoryDigestRepository;
use App\Tests\Support\SequenceBoardIdGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DigestServiceTest extends TestCase
{
    private const string ALICE = '019b1000-0000-7000-8000-000000000001';
    private const string BOB = '019b1000-0000-7000-8000-000000000002';
    private const string FIRST = '019b5000-0000-7000-8000-000000000001';
    private const string SECOND = '019b5000-0000-7000-8000-000000000002';

    /** @return iterable<string, array{int, int, string}> */
    public static function grammar(): iterable
    {
        yield 'empty' => [0, 0, 'Weekly digest for 2026-W30: 0 posts and 0 comments.'];
        yield 'singular' => [1, 1, 'Weekly digest for 2026-W30: 1 post and 1 comment.'];
        yield 'mixed plural' => [2, 1, 'Weekly digest for 2026-W30: 2 posts and 1 comment.'];
    }

    #[DataProvider('grammar')]
    public function testGeneratesDeterministicContentAndImmutableSnapshot(
        int $posts,
        int $comments,
        string $content,
    ): void {
        $repository = new InMemoryDigestRepository(new DigestSnapshot($posts, $comments));
        $service = new DigestService(
            $repository,
            new FrozenBoardClock(new DateTimeImmutable('2026-07-21T01:02:03.123456+09:00')),
            new SequenceBoardIdGenerator([self::FIRST]),
        );

        $digest = $service->generate(self::ALICE, IsoWeek::fromString('2026-W30'));
        self::assertSame(self::FIRST, $digest->id);
        self::assertSame(self::ALICE, $digest->requestedUserId);
        self::assertSame($content, $digest->content);
        self::assertSame($posts, $digest->postCount);
        self::assertSame($comments, $digest->commentCount);
        self::assertSame([$digest], $repository->digests);
    }

    public function testSameUserAndWeekCreatesMultipleRowsAndShowConcealsOwnership(): void
    {
        $repository = new InMemoryDigestRepository(new DigestSnapshot(1, 2));
        $service = new DigestService(
            $repository,
            new FrozenBoardClock(new DateTimeImmutable('2026-07-21T00:00:00Z')),
            new SequenceBoardIdGenerator([self::FIRST, self::SECOND]),
        );

        $first = $service->generate(self::ALICE, IsoWeek::fromString('2026-W30'));
        $second = $service->generate(self::ALICE, IsoWeek::fromString('2026-W30'));
        self::assertNotSame($first->id, $second->id);
        self::assertCount(2, $repository->digests);
        self::assertSame($first, $service->show(self::FIRST, self::ALICE));

        foreach ([[self::FIRST, self::BOB], [self::SECOND, self::BOB], ['malformed', self::ALICE]] as [$id, $user]) {
            try {
                $service->show($id, $user);
                self::fail('Expected concealed digest.');
            } catch (DigestNotFound) {
                self::assertTrue(true);
            }
        }
    }
}
