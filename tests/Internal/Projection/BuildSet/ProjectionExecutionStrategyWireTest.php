<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\BuildSet;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\BuildSet\ProjectionExecutionStrategyWire;
use PHPUnit\Framework\TestCase;

final class ProjectionExecutionStrategyWireTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function strategyWires(): iterable
    {
        yield 'inline' => [Inline::class, 'inline'];
        yield 'deferred' => [Deferred::class, 'deferred'];
        yield 'custom' => ['\\Vendor\\Queue\\CustomStrategy', 'custom:Vendor\\Queue\\CustomStrategy'];
        yield 'custom without leading slash' => [
            'Vendor\\Queue\\CustomStrategy',
            'custom:Vendor\\Queue\\CustomStrategy',
        ];
    }

    /** @param class-string|non-empty-string $strategy */
    #[\PHPUnit\Framework\Attributes\DataProvider('strategyWires')]
    public function testMapsDeclaredStrategiesToTheClosedWire(string $strategy, string $expected): void
    {
        self::assertSame($expected, new ProjectionExecutionStrategyWire()->map($strategy));
    }

    public function testMappingDoesNotAutoloadTheDeclaredStrategy(): void
    {
        $autoloaded = [];
        $loader = static function (string $class) use (&$autoloaded): void {
            if (str_starts_with($class, 'BlackOps\\Tests\\NeverLoad\\')) {
                $autoloaded[] = $class;
            }
        };
        spl_autoload_register($loader);

        try {
            $wire = new ProjectionExecutionStrategyWire()->map('BlackOps\\Tests\\NeverLoad\\Strategy');
        } finally {
            spl_autoload_unregister($loader);
        }

        self::assertSame('custom:BlackOps\\Tests\\NeverLoad\\Strategy', $wire);
        self::assertSame([], $autoloaded);
    }

    public function testCodecRejectsAnEmptyCustomWire(): void
    {
        $this->expectException(ProjectionBuildSetValidationException::class);

        new ProjectionBuildSetCodec()->projection(
            kind: 'blackops_schedule_projection',
            applicationBuildId: 'build-1',
            records: [[
                'name' => 'fixture.run',
                'operationType' => 'fixture.run',
                'cron' => '* * * * *',
                'timezone' => 'UTC',
                'strategy' => 'custom:',
            ]],
        );
    }
}
