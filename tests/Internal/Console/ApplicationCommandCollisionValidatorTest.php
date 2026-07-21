<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Console\ApplicationCommandCollisionValidator;
use BlackOps\Internal\Console\ApplicationCommandMetadata;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

final class ApplicationCommandCollisionValidatorTest extends TestCase
{
    public function testExplicitSameClassOverridesDiscoveredCommand(): void
    {
        $command = $this->metadata(CollisionOneCommand::class, 'fixture:one');

        self::assertSame([], new ApplicationCommandCollisionValidator()->merge([$command], [$command], []));
    }

    public function testFormerFrameworkPrefixRemainsAvailable(): void
    {
        $command = $this->metadata(CollisionOneCommand::class, 'blackops:worker:run');

        self::assertSame([$command], new ApplicationCommandCollisionValidator()->merge([$command], [], ['worker:run']));
    }

    /** @param list<ApplicationCommandMetadata> $discovered
     * @param list<ApplicationCommandMetadata> $explicit
     * @param list<string> $framework
     */
    #[DataProvider('collisionProvider')]
    public function testRejectsCanonicalAndAliasCollisions(
        array $discovered,
        array $explicit,
        array $framework,
        string $message,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ApplicationCommandCollisionValidator()->merge($discovered, $explicit, $framework);
    }

    /** @return iterable<string, array{list<ApplicationCommandMetadata>, list<ApplicationCommandMetadata>, list<string>, string}> */
    public static function collisionProvider(): iterable
    {
        $one = self::command(CollisionOneCommand::class, 'fixture:one', ['fixture:shared']);
        $twoName = self::command(CollisionTwoCommand::class, 'fixture:shared');
        $twoAlias = self::command(CollisionTwoCommand::class, 'fixture:two', ['fixture:one']);

        yield 'discovered alias to name' => [[$one, $twoName], [], [], 'conflicts with another command'];
        yield 'explicit alias to discovered name' => [[$one], [$twoAlias], [], 'conflicts with another command'];
        yield 'framework canonical' => [[$one], [], ['fixture:one'], 'conflicts with a framework command'];
        yield 'framework alias' => [[$one], [], ['fixture:shared'], 'conflicts with a framework command'];
    }

    /**
     * @param class-string<Command> $class
     * @param list<string> $aliases
     */
    private function metadata(string $class, string $name, array $aliases = []): ApplicationCommandMetadata
    {
        return self::command($class, $name, $aliases);
    }

    /**
     * @param class-string<Command> $class
     * @param list<string> $aliases
     */
    private static function command(string $class, string $name, array $aliases = []): ApplicationCommandMetadata
    {
        return new ApplicationCommandMetadata($class, $name, null, $aliases, false, null, []);
    }
}

final class CollisionOneCommand extends Command {}

final class CollisionTwoCommand extends Command {}
