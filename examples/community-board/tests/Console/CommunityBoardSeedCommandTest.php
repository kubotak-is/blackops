<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\CommunityBoardSeedCommand;
use App\Infrastructure\Seed\CommunityBoardSeedDataset;
use App\Infrastructure\Seed\SeedResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommunityBoardSeedCommandTest extends TestCase
{
    public function testCommandHasCanonicalNameAndReportsDeterministicCounts(): void
    {
        $command = new CommunityBoardSeedCommand(static fn(): SeedResult => new SeedResult(3, 3, 4));
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('app:seed', $command->getName());
        self::assertStringContainsString('3 users, 3 posts, 4 comments', $tester->getDisplay());
        self::assertStringNotContainsString(CommunityBoardSeedDataset::DEMO_PASSWORD, $tester->getDisplay());
    }

    public function testFailureIsNonZeroAndDoesNotExposeTheUnderlyingError(): void
    {
        $credential = 'database-password-must-not-appear';
        $tester = new CommandTester(
            new CommunityBoardSeedCommand(
                static fn(): never => throw new RuntimeException("Unable to connect with {$credential}"),
            ),
        );

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('database is available and migrations are applied', $tester->getDisplay());
        self::assertStringNotContainsString($credential, $tester->getDisplay());
    }
}
