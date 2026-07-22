<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use App\Domain\Board\BoardService;
use App\Domain\Identity\PasswordHasher;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use App\Infrastructure\Persistence\DoctrineBoardRepository;
use BlackOps\Database\Seeder;
use Doctrine\DBAL\Connection;

/** @mago-expect lint:kan-defect */
final readonly class CommunityBoardSeeder implements Seeder
{
    public function __construct(
        private Connection $connection,
        private UserRepository $users,
        private PasswordHasher $passwords,
        private CommunityBoardSeedDataset $dataset = new CommunityBoardSeedDataset(),
    ) {}

    public function run(): void
    {
        $this->seed();
    }

    public function seed(): SeedResult
    {
        return $this->connection->transactional($this->seedInTransaction(...));
    }

    private function seedInTransaction(): SeedResult
    {
        $state = new SeedStateRepository($this->connection);
        $boardRepository = new DoctrineBoardRepository($this->connection);

        foreach ($this->dataset->users() as $user) {
            if ($state->userExists($user)) {
                continue;
            }

            $this->users->save(
                new User(
                    $user->id,
                    $user->email,
                    strtolower($user->email),
                    $user->displayName,
                    $this->passwords->hash($user->password),
                    $user->createdAt,
                    $user->createdAt,
                ),
            );
        }

        foreach ($this->dataset->posts() as $post) {
            if ($state->postExists($post)) {
                continue;
            }

            $board = new BoardService(
                $boardRepository,
                new FixedSeedClock($post->createdAt),
                new FixedSeedIdentifierGenerator($post->id),
            );
            $board->createPost($post->authorId, $post->title, $post->body);
        }

        foreach ($this->dataset->comments() as $comment) {
            if ($state->commentExists($comment)) {
                continue;
            }

            $board = new BoardService(
                $boardRepository,
                new FixedSeedClock($comment->createdAt),
                new FixedSeedIdentifierGenerator($comment->id),
            );
            $board->addComment($comment->postId, $comment->authorId, $comment->body);
        }

        $users = $this->dataset->users();
        $posts = $this->dataset->posts();
        $comments = $this->dataset->comments();
        foreach ($users as $user) {
            $state->userExists($user);
        }
        foreach ($posts as $post) {
            $state->postExists($post);
        }
        foreach ($comments as $comment) {
            $state->commentExists($comment);
        }

        return new SeedResult(count($users), count($posts), count($comments));
    }
}
