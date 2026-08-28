<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Command;

use BlackOps\Internal\Projection\Command\CommandProjectionNamespaceResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;

final class CommandProjectionNamespaceResolverTest extends TestCase
{
    public function testGlobalRowsAreSortedByUnsignedUtf8CanonicalName(): void
    {
        $rows = [
            $this->record('あ:run'),
            $this->record('é:run'),
            $this->record('a:run'),
        ];

        $resolved = new CommandProjectionNamespaceResolver()->select($rows);

        self::assertSame(['a:run', 'é:run', 'あ:run'], array_column($resolved, 'name'));
    }

    public function testExactNamespaceIncludesNestedCommandsAndKeepsHiddenRowsForCaller(): void
    {
        $rows = [
            $this->record('cache:pool:clear'),
            $this->record('cache:clear'),
            $this->record('cache:secret', hidden: true),
            $this->record('config:dump'),
        ];

        $resolved = new CommandProjectionNamespaceResolver()->select($rows, 'cache');

        self::assertSame(['cache:clear', 'cache:pool:clear', 'cache:secret'], array_column($resolved, 'name'));
    }

    public function testNestedExactNamespaceAndUniqueSegmentAbbreviationResolve(): void
    {
        $rows = [
            $this->record('cache:clear'),
            $this->record('cache:pool:clear'),
            $this->record('config:dump'),
        ];
        $resolver = new CommandProjectionNamespaceResolver();

        self::assertSame(['cache:pool:clear'], array_column($resolver->select($rows, 'cache:pool'), 'name'));
        self::assertSame(['cache:pool:clear'], array_column($resolver->select($rows, 'cach:poo'), 'name'));
    }

    public function testAmbiguousNamespaceUsesStableMessageAndAlternatives(): void
    {
        $rows = [
            $this->record('cache:clear:all'),
            $this->record('cache:config:all'),
        ];

        try {
            new CommandProjectionNamespaceResolver()->select($rows, 'cache:c');
            self::fail('An ambiguous namespace must throw.');
        } catch (NamespaceNotFoundException $exception) {
            self::assertSame(
                "The namespace \"cache:c\" is ambiguous.\nDid you mean one of these?\n    cache:clear\n    cache:config.",
                $exception->getMessage(),
            );
            self::assertSame(['cache:clear', 'cache:config'], $exception->getAlternatives());
        }
    }

    public function testUnknownNamespaceUsesStableMessageAndAlternatives(): void
    {
        $rows = [$this->record('cache:clear'), $this->record('config:dump')];

        try {
            new CommandProjectionNamespaceResolver()->select($rows, 'cahe');
            self::fail('An unknown namespace must throw.');
        } catch (NamespaceNotFoundException $exception) {
            self::assertSame(
                "There are no commands defined in the \"cahe\" namespace.\n\nDid you mean this?\n    cache",
                $exception->getMessage(),
            );
            self::assertSame(['cache'], $exception->getAlternatives());
        }
    }

    public function testAliasNamespaceSelectsCanonicalRowOnlyOnce(): void
    {
        $rows = [
            $this->record('operations:run', ['operations:execute']),
            $this->record('config:dump'),
        ];

        $resolved = new CommandProjectionNamespaceResolver()->select($rows, 'operations');

        self::assertCount(1, $resolved);
        self::assertSame('operations:run', $resolved[0]['name']);
    }

    public function testAliasCanDefineTheNamespaceForCanonicalRow(): void
    {
        $rows = [
            $this->record('cache:clear', ['cc:clear']),
            $this->record('config:dump'),
        ];

        $resolved = new CommandProjectionNamespaceResolver()->select($rows, 'cc');

        self::assertSame(['cache:clear'], array_column($resolved, 'name'));
    }

    public function testHiddenOnlyNamespaceIsNotInInventory(): void
    {
        $rows = [$this->record('secret:run', hidden: true), $this->record('public:run')];

        try {
            new CommandProjectionNamespaceResolver()->select($rows, 'secret');
            self::fail('A namespace provided only by a hidden row must be unknown.');
        } catch (NamespaceNotFoundException $exception) {
            self::assertSame('There are no commands defined in the "secret" namespace.', $exception->getMessage());
            self::assertSame([], $exception->getAlternatives());
        }
    }

    public function testNestedUnknownNamespaceUsesSegmentAwareAlternative(): void
    {
        $rows = [
            $this->record('cache:pool:clear'),
            $this->record('cache:queue:run'),
            $this->record('config:dump'),
        ];

        try {
            new CommandProjectionNamespaceResolver()->select($rows, 'cache:pool:clear');
            self::fail('A command name is not a namespace.');
        } catch (NamespaceNotFoundException $exception) {
            self::assertSame(
                "There are no commands defined in the \"cache:pool:clear\" namespace.\n\nDid you mean this?\n    cache:pool",
                $exception->getMessage(),
            );
            self::assertSame(['cache:pool'], $exception->getAlternatives());
        }
    }

    /**
     * @param list<string> $aliases
     * @return array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}
     */
    private function record(string $name, array $aliases = [], bool $hidden = false): array
    {
        return [
            'name' => $name,
            'aliases' => $aliases,
            'description' => $name,
            'origin' => 'application',
            'hidden' => $hidden,
        ];
    }
}
