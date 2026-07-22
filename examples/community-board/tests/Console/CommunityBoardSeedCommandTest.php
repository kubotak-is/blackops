<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\CommunityBoardSeedCommand;
use App\Infrastructure\Seed\CommunityBoardSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;

final class CommunityBoardSeedCommandTest extends TestCase
{
    public function testCommandIsDiscoverableAndRequiresTheCompiledSeederService(): void
    {
        $reflection = new ReflectionClass(CommunityBoardSeedCommand::class);
        $attributes = $reflection->getAttributes(AsCommand::class);
        $constructor = $reflection->getConstructor();

        self::assertCount(1, $attributes);
        self::assertSame('app:seed', $attributes[0]->newInstance()->name);
        self::assertNotNull($constructor);
        self::assertSame(CommunityBoardSeeder::class, $constructor->getParameters()[0]->getType()?->getName());
    }
}
