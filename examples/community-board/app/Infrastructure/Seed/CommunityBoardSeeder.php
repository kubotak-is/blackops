<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use App\Domain\Board\BoardService;
use App\Http\DatabaseConnectionFactory;
use App\Identity\DoctrineIdentityRepository;
use App\Identity\IdentityService;
use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use App\Identity\SessionToken;
use App\Infrastructure\Persistence\DoctrineBoardRepository;
use Doctrine\DBAL\Connection;

final readonly class CommunityBoardSeeder
{
    public function __construct(
        private Connection $connection,
        private bool $closeConnection = false,
        private CommunityBoardSeedDataset $dataset = new CommunityBoardSeedDataset(),
    ) {}

    /** @param array<string, string> $environment */
    public static function fromEnvironment(array $environment): self
    {
        return new self(DatabaseConnectionFactory::fromEnvironment($environment)->create(), closeConnection: true);
    }

    public function seed(): SeedResult
    {
        try {
            return $this->connection->transactional(fn(): SeedResult => $this->seedInTransaction());
        } finally {
            if ($this->closeConnection) {
                $this->connection->close();
            }
        }
    }

    private function seedInTransaction(): SeedResult
    {
        $state = new SeedStateRepository($this->connection);
        $identityRepository = new DoctrineIdentityRepository($this->connection);
        $boardRepository = new DoctrineBoardRepository($this->connection);

        foreach ($this->dataset->users() as $user) {
            if ($state->userExists($user)) {
                continue;
            }

            $identity = new IdentityService(
                $identityRepository,
                new PasswordHasher(),
                new SessionToken(),
                new FixedSeedClock($user->createdAt),
                new FixedSeedIdentifierGenerator($user->id),
                new SessionSettings(28_800),
            );
            $identity->provisionUser($user->email, $user->displayName, $user->password);
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
