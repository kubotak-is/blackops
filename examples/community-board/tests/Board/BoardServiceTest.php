<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\BoardService;
use App\Domain\Board\PostNotFound;
use App\Tests\Support\FrozenBoardClock;
use App\Tests\Support\InMemoryBoardRepository;
use App\Tests\Support\SequenceBoardIdGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BoardServiceTest extends TestCase
{
    private const string ALICE = '019b1000-0000-7000-8000-000000000001';
    private const string BOB = '019b1000-0000-7000-8000-000000000002';
    private const string POST = '019b2000-0000-7000-8000-000000000001';
    private const string COMMENT = '019b3000-0000-7000-8000-000000000001';

    public function testDomainServiceOwnsTheCompletePostAndCommentWorkflow(): void
    {
        $repository = new InMemoryBoardRepository();
        $clock = new FrozenBoardClock(new DateTimeImmutable('2026-07-20T12:34:56.123456+09:00'));
        $service = new BoardService($repository, $clock, new SequenceBoardIdGenerator([self::POST, self::COMMENT]));

        $created = $service->createPost(self::ALICE, 'First post', 'Hello board');
        self::assertSame(self::POST, $created->postId);
        self::assertSame('2026-07-20T12:34:56.123456+09:00', $created->createdAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(self::ALICE, $repository->posts[self::POST]['authorId']);

        $listed = $service->listPosts(1, 20);
        self::assertSame(1, $listed->total);
        self::assertSame(self::POST, $listed->posts[0]->id);
        self::assertSame('First post', $service->showPost(self::POST)->post->title);

        $comment = $service->addComment(self::POST, self::BOB, 'A comment');
        self::assertSame(self::COMMENT, $comment->commentId);
        self::assertSame(self::BOB, $repository->comments[self::COMMENT]['authorId']);

        $updated = $service->updatePost(self::POST, self::ALICE, 'Updated', 'Updated body');
        self::assertSame(self::POST, $updated->postId);
        self::assertSame('Updated', $repository->posts[self::POST]['title']);

        $service->deletePost(self::POST, self::ALICE);
        self::assertArrayNotHasKey(self::POST, $repository->posts);
        self::assertArrayNotHasKey(self::COMMENT, $repository->comments);
        self::assertSame([self::POST, self::POST, self::POST], $repository->lockedPostIds);
    }

    /** @return iterable<string, array{string, string}> */
    public static function concealedPostIds(): iterable
    {
        yield 'unknown' => ['019b2000-0000-7000-8000-000000000099', self::ALICE];
        yield 'malformed' => ['not-a-uuid', self::ALICE];
        yield 'non-owner' => [self::POST, self::BOB];
    }

    #[DataProvider('concealedPostIds')]
    public function testUpdateAndDeleteConcealUnknownMalformedAndNonOwnerPost(string $postId, string $actorId): void
    {
        foreach (['update', 'delete'] as $action) {
            [$service, $repository] = $this->serviceWithPost();

            try {
                if ($action === 'update') {
                    $service->updatePost($postId, $actorId, 'Changed', 'Changed body');
                } else {
                    $service->deletePost($postId, $actorId);
                }
                self::fail('Expected the domain to conceal the post.');
            } catch (PostNotFound) {
                self::assertSame('Original', $repository->posts[self::POST]['title']);
            }
        }
    }

    public function testShowAndAddCommentRejectMalformedAndUnknownPostWithoutWriting(): void
    {
        foreach (['not-a-uuid', self::POST] as $postId) {
            $repository = new InMemoryBoardRepository();
            $service = new BoardService(
                $repository,
                new FrozenBoardClock(new DateTimeImmutable('2026-07-20T00:00:00Z')),
                new SequenceBoardIdGenerator([self::COMMENT]),
            );

            foreach (['show', 'comment'] as $action) {
                try {
                    if ($action === 'show') {
                        $service->showPost($postId);
                    } else {
                        $service->addComment($postId, self::ALICE, 'Comment');
                    }
                    self::fail('Expected the domain to conceal the post.');
                } catch (PostNotFound) {
                    self::assertSame([], $repository->comments);
                }
            }
        }
    }

    public function testDomainBoardLayerRejectsFrameworkInfrastructureAndApplicationBoundaryDependencies(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                dirname(__DIR__, 2) . '/app/Domain/Board',
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            foreach ([
                'BlackOps\\',
                'Doctrine\\',
                'Symfony\\',
                'App\\Infrastructure\\',
                'App\\Feature\\',
                'App\\Http\\',
                'App\\Security\\',
                '#[',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    /** @return array{BoardService, InMemoryBoardRepository} */
    private function serviceWithPost(): array
    {
        $repository = new InMemoryBoardRepository();
        $service = new BoardService(
            $repository,
            new FrozenBoardClock(new DateTimeImmutable('2026-07-20T00:00:00Z')),
            new SequenceBoardIdGenerator([self::POST]),
        );
        $service->createPost(self::ALICE, 'Original', 'Original body');

        return [$service, $repository];
    }
}
