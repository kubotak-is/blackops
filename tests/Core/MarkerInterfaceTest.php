<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MarkerInterfaceTest extends TestCase
{
    public function testOperationIsMarkerInterfaceWithoutMethods(): void
    {
        $reflection = new ReflectionClass(Operation::class);

        self::assertTrue($reflection->isInterface(), 'Operation must be an interface.');
        self::assertSame([], $reflection->getMethods(), 'Operation must not declare any methods.');
    }

    public function testOperationValueIsMarkerInterfaceWithoutMethods(): void
    {
        $reflection = new ReflectionClass(OperationValue::class);

        self::assertTrue($reflection->isInterface(), 'OperationValue must be an interface.');
        self::assertSame([], $reflection->getMethods(), 'OperationValue must not declare any methods.');
    }

    public function testOutcomeIsMarkerInterfaceWithoutMethods(): void
    {
        $reflection = new ReflectionClass(Outcome::class);

        self::assertTrue($reflection->isInterface(), 'Outcome must be an interface.');
        self::assertSame([], $reflection->getMethods(), 'Outcome must not declare any methods.');
    }

    public function testOutcomeDataIsMarkerInterfaceWithoutMethods(): void
    {
        $reflection = new ReflectionClass(OutcomeData::class);

        self::assertTrue($reflection->isInterface(), 'OutcomeData must be an interface.');
        self::assertSame([], $reflection->getMethods(), 'OutcomeData must not declare any methods.');
    }

    public function testMarkerInterfacesAreMarkedPublicApi(): void
    {
        foreach ([Operation::class, OperationValue::class, Outcome::class, OutcomeData::class] as $marker) {
            $reflection = new ReflectionClass($marker);
            $attributes = $reflection->getAttributes(PublicApi::class);

            self::assertCount(1, $attributes, sprintf('%s must be marked with #[PublicApi].', $marker));
        }
    }

    public function testPublicApiIsAnAttributeTargetingClass(): void
    {
        $reflection = new ReflectionClass(PublicApi::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes, 'PublicApi must be an Attribute.');

        /** @var \Attribute $instance */
        $instance = $attributes[0]->newInstance();
        $targets = $instance->flags;

        self::assertSame(
            \Attribute::TARGET_CLASS,
            $targets,
            'PublicApi must target class (TARGET_CLASS covers class and interface in PHP).',
        );
    }

    public function testPublicApiCanBeAppliedToClassAndInterface(): void
    {
        $classFixture = new ReflectionClass(PublicApiClassFixture::class);
        $interfaceFixture = new ReflectionClass(PublicApiInterfaceFixture::class);

        self::assertCount(
            1,
            $classFixture->getAttributes(PublicApi::class),
            'PublicApi must be applicable to a class.',
        );
        self::assertCount(
            1,
            $interfaceFixture->getAttributes(PublicApi::class),
            'PublicApi must be applicable to an interface.',
        );
    }
}

#[PublicApi]
final readonly class PublicApiClassFixture {}

#[PublicApi]
interface PublicApiInterfaceFixture {}
