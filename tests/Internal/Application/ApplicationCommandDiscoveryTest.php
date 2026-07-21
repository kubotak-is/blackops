<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationCommandDiscovery;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Console\ApplicationCommandMetadata;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationCommandDiscoveryTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testDiscoversAttributedCommandOnceWithoutCallingRequiredConstructor(): void
    {
        $root = $this->directory();
        $namespace = 'BlackOps\\Tests\\Generated\\Command' . bin2hex(random_bytes(4));
        $class = $namespace . '\\RequiredCommand';
        file_put_contents($root . '/Commands.php', <<<PHP
            <?php
            namespace {$namespace};
            use Symfony\Component\Console\Attribute\AsCommand;
            use Symfony\Component\Console\Command\Command;
            final readonly class Dependency {}
            #[AsCommand(
                name: 'fixture:required',
                description: 'Fixture description.',
                aliases: ['fixture:alias'],
                hidden: true,
                help: 'Fixture help.',
                usages: ['fixture:required --flag'],
            )]
            final class RequiredCommand extends Command {
                public static int \$constructions = 0;
                public function __construct(Dependency \$dependency) {
                    self::\$constructions++;
                    parent::__construct();
                }
            }
            final class IgnoredCommand extends Command {}
            PHP);
        $snapshot = $this->snapshotFor([$root, $root]);

        $commands = new ApplicationCommandDiscovery()->discover($snapshot);

        self::assertCount(1, $commands);
        self::assertSame($class, $commands[0]->class);
        self::assertSame('fixture:required', $commands[0]->name);
        self::assertSame(['fixture:alias'], $commands[0]->aliases);
        self::assertTrue($commands[0]->hidden);
        self::assertSame('Fixture help.', $commands[0]->help);
        self::assertSame(['fixture:required --flag'], $commands[0]->usages);
        self::assertSame(0, $class::$constructions);
    }

    public function testMissingOrEmptyDiscoveryConfigurationDiscoversNothing(): void
    {
        self::assertSame([], new ApplicationCommandDiscovery()->discover($this->snapshotFor(null)));
        self::assertSame([], new ApplicationCommandDiscovery()->discover($this->snapshotFor([])));
    }

    #[DataProvider('invalidCandidateProvider')]
    public function testRejectsInvalidAttributedCandidateWithSafeError(string $declaration): void
    {
        $root = $this->directory();
        $namespace = 'BlackOps\\Tests\\Generated\\InvalidCommand' . bin2hex(random_bytes(4));
        file_put_contents($root . '/Invalid.php', str_replace('__NAMESPACE__', $namespace, $declaration));

        try {
            new ApplicationCommandDiscovery()->discover($this->snapshotFor([$root]));
            self::fail('Expected invalid discovered command rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString($root, $exception->getMessage());
            self::assertStringNotContainsString('credential-value', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCandidateProvider(): iterable
    {
        yield 'non command' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                #[AsCommand(name: 'fixture:not-command')]
                final class InvalidCommand {}
                PHP];
        yield 'abstract command' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                use Symfony\Component\Console\Command\Command;
                #[AsCommand(name: 'fixture:abstract')]
                abstract class InvalidCommand extends Command {}
                PHP];
        yield 'multiple attributes' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                use Symfony\Component\Console\Command\Command;
                #[AsCommand(name: 'fixture:one')]
                #[AsCommand(name: 'fixture:two')]
                final class InvalidCommand extends Command {}
                PHP];
        yield 'missing name' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                use Symfony\Component\Console\Command\Command;
                #[AsCommand(name: '')]
                final class InvalidCommand extends Command {}
                PHP];
        yield 'invalid alias' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                use Symfony\Component\Console\Command\Command;
                #[AsCommand(name: 'fixture:valid', aliases: ['fixture::invalid'])]
                final class InvalidCommand extends Command {}
                PHP];
        yield 'self collision' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                use Symfony\Component\Console\Command\Command;
                #[AsCommand(name: 'fixture:same', aliases: ['fixture:same'])]
                final class InvalidCommand extends Command {}
                PHP];
        yield 'invalid attribute arguments' => [<<<'PHP'
                <?php
                namespace __NAMESPACE__;
                use Symfony\Component\Console\Attribute\AsCommand;
                use Symfony\Component\Console\Command\Command;
                #[AsCommand(name: ['credential-value'])]
                final class InvalidCommand extends Command {}
                PHP];
    }

    public function testClosesParseAndRootErrorsToSafeDiscoveryFailure(): void
    {
        $root = $this->directory();
        file_put_contents($root . '/Broken.php', '<?php credential-value syntax');

        try {
            new ApplicationCommandDiscovery()->discover($this->snapshotFor([$root]));
            self::fail('Expected source parse failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Application command discovery failed.', $exception->getMessage());
        }

        try {
            new ApplicationCommandDiscovery()->discover($this->snapshotFor(['relative/path']));
            self::fail('Expected invalid discovery root failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Application command discovery failed.', $exception->getMessage());
        }
    }

    /** @param list<string>|null $roots */
    private function snapshotFor(?array $roots): ApplicationConfigurationSnapshot
    {
        $configuration = $roots === null ? [] : ['app' => ['command_discovery' => $roots]];

        return new ApplicationConfigurationSnapshot($this->directory(), $configuration, [], [], []);
    }
}
