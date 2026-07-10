<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Internal\Console\ListOperationsCommand;
use BlackOps\Tests\Internal\Console\Fixture\DevelopmentDeferredOperation;
use BlackOps\Tests\Internal\Console\Fixture\DevelopmentInlineOperation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListOperationsCommandTest extends TestCase
{
    public function testListsDiscoveredMetadataInTypeIdOrder(): void
    {
        $tester = new CommandTester(new ListOperationsCommand());

        $status = $tester->execute($this->discoveryOptions());
        $display = $tester->getDisplay();

        self::assertSame(0, $status);
        self::assertStringContainsString('Type ID', $display);
        self::assertStringContainsString('Definition', $display);
        self::assertStringContainsString('Execution Strategy', $display);
        self::assertStringContainsString(DevelopmentDeferredOperation::class, $display);
        self::assertStringContainsString(Deferred::class, $display);
        self::assertStringContainsString(DevelopmentInlineOperation::class, $display);
        self::assertStringContainsString(Inline::class, $display);
        self::assertLessThan(strpos($display, 'development.inline'), strpos($display, 'development.deferred'));
    }

    public function testRequiresCompleteDiscoveryInput(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CommandTester(new ListOperationsCommand())->execute([
            '--discovery-root' => [$this->fixtureRoot()],
        ]);
    }

    /**
     * @return array<string, string|list<string>>
     */
    private function discoveryOptions(): array
    {
        $directory = sys_get_temp_dir() . '/blackops-list-operations-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $psr4 = $directory . '/autoload_psr4.php';
        $classmap = $directory . '/autoload_classmap.php';
        file_put_contents(
            $psr4,
            '<?php return '
            . var_export([
                'BlackOps\\Tests\\Internal\\Console\\Fixture\\' => [$this->fixtureRoot()],
            ], return: true)
            . ';',
        );
        file_put_contents($classmap, '<?php return [];');

        return [
            '--discovery-root' => [$this->fixtureRoot()],
            '--composer-base' => dirname(__DIR__, 3),
            '--composer-psr4' => $psr4,
            '--composer-classmap' => $classmap,
        ];
    }

    private function fixtureRoot(): string
    {
        return __DIR__ . '/Fixture';
    }
}
