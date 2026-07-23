<?php

declare(strict_types=1);

namespace BlackOps\Tests\Identifier;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class Uuidv7GeneratorTest extends TestCase
{
    public function testPublicContractAndDefaultBindingProduceCanonicalUuidV7(): void
    {
        self::assertCount(1, new ReflectionClass(Uuidv7Generator::class)->getAttributes(PublicApi::class));

        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, []);
        $compiler->registerUuidv7Generator($builder);
        $generator = $compiler->compile($builder)->get(Uuidv7Generator::class);
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first,
        );
        self::assertNotSame($first, $second);
    }

    public function testExplicitOverrideIsValidatedAndDeterministic(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new FixedUuidv7Provider()]);
        $compiler->registerUuidv7Generator($builder);
        $generator = $compiler->compile($builder)->get(Uuidv7Generator::class);

        self::assertSame('018f2f5e-2f3c-7abc-8def-0123456789ab', $generator->generate());
    }

    public function testObjectOverrideIsValidatedAndDeterministic(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new ObjectUuidv7Provider()]);
        $compiler->registerUuidv7Generator($builder);
        $generator = $compiler->compile($builder)->get(Uuidv7Generator::class);

        self::assertSame('018f2f5e-2f3c-7abc-8def-0123456789ab', $generator->generate());
    }

    public function testInvalidOverrideFailsBeforeApplicationDataUsesIt(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new InvalidUuidv7Provider()]);
        $compiler->registerUuidv7Generator($builder);
        $generator = $compiler->compile($builder)->get(Uuidv7Generator::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UUID generator returned an invalid value.');
        $generator->generate();
    }
}

final readonly class FixedUuidv7Provider implements \BlackOps\Core\DependencyInjection\ServiceProvider
{
    public function register(\BlackOps\Core\DependencyInjection\ServiceRegistry $services): void
    {
        $services->autowire(Uuidv7Generator::class, FixedUuidv7::class);
    }
}

final readonly class InvalidUuidv7Provider implements \BlackOps\Core\DependencyInjection\ServiceProvider
{
    public function register(\BlackOps\Core\DependencyInjection\ServiceRegistry $services): void
    {
        $services->autowire(Uuidv7Generator::class, InvalidUuidv7::class);
    }
}

final readonly class ObjectUuidv7Provider implements \BlackOps\Core\DependencyInjection\ServiceProvider
{
    public function register(\BlackOps\Core\DependencyInjection\ServiceRegistry $services): void
    {
        $services->set(Uuidv7Generator::class, new FixedUuidv7());
    }
}

final readonly class FixedUuidv7 implements Uuidv7Generator
{
    public function generate(): string
    {
        return '018f2f5e-2f3c-7abc-8def-0123456789ab';
    }
}

final readonly class InvalidUuidv7 implements Uuidv7Generator
{
    public function generate(): string
    {
        return 'not-a-uuid';
    }
}
