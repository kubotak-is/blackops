<?php

declare(strict_types=1);

namespace App\Tests\Seed;

use App\Domain\Board\BoardId;
use App\Infrastructure\Seed\CommunityBoardSeedDataset;
use PHPUnit\Framework\TestCase;

final class CommunityBoardSeedDatasetTest extends TestCase
{
    public function testDatasetDefinesMultipleDeterministicUsersPostsAndComments(): void
    {
        $dataset = new CommunityBoardSeedDataset();
        $users = $dataset->users();
        $posts = $dataset->posts();
        $comments = $dataset->comments();
        $identifiers = [
            ...array_map(static fn($user): string => $user->id, $users),
            ...array_map(static fn($post): string => $post->id, $posts),
            ...array_map(static fn($comment): string => $comment->id, $comments),
        ];

        self::assertCount(3, $users);
        self::assertCount(3, $posts);
        self::assertCount(4, $comments);
        self::assertCount(10, array_unique($identifiers));
        foreach ($identifiers as $identifier) {
            self::assertIsString($identifier);
            self::assertTrue(BoardId::isValid($identifier));
        }

        self::assertSame(CommunityBoardSeedDataset::DEMO_EMAIL, $users[0]->email);
        self::assertSame(CommunityBoardSeedDataset::DEMO_DISPLAY_NAME, $users[0]->displayName);
        self::assertSame(CommunityBoardSeedDataset::DEMO_PASSWORD, $users[0]->password);
    }
}
