<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Database\Seeder;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use PHPUnit\Framework\TestCase;

final class SeederContainerCompilerTest extends TestCase
{
    public function testUnresolvedSeederConstructorDependencyFailsCompilation(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerSeeders($builder, [UnresolvedSeeder::class], UnresolvedSeeder::class);

        $this->expectException(\Throwable::class);

        $compiler->compile($builder);
    }
}

/** @mago-expect lint:single-class-per-file */
interface MissingSeederDependency {}

/** @mago-expect lint:single-class-per-file */
final readonly class UnresolvedSeeder implements Seeder
{
    public function __construct(
        private MissingSeederDependency $dependency,
    ) {}

    public function run(): void {}
}
