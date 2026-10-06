<?php

declare(strict_types=1);

namespace BlackOps\Tests\Audit;

use BlackOps\Audit\AuditCatalogDefinition;
use BlackOps\Core\Audit\AuditActionPhase;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditEventCategory;
use BlackOps\Core\Audit\AuditEventDefinition;
use BlackOps\Core\Audit\AuditOutcomeStatus;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Audit\AuditSemanticTuple;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use PHPUnit\Framework\TestCase;

final class AuditCatalogDefinitionTest extends TestCase
{
    public function testMinimalDeclarationHasTheExactSupportedShape(): void
    {
        $definition = new AuditCatalogDefinition('application.catalog', 1, [$this->event()]);
        $reflection = new \ReflectionClass(AuditCatalogDefinition::class);
        $constructor = $reflection->getConstructor();

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertNotNull($constructor);
        self::assertSame(
            ['catalogId', 'catalogVersion', 'events'],
            array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                $constructor->getParameters(),
            ),
        );
        foreach ($constructor->getParameters() as $parameter) {
            self::assertFalse($parameter->isDefaultValueAvailable(), $parameter->getName());
        }
        self::assertCount(1, $constructor->getParameters()[0]->getAttributes(\SensitiveParameter::class));
        self::assertCount(0, $constructor->getParameters()[1]->getAttributes(\SensitiveParameter::class));
        self::assertCount(1, $constructor->getParameters()[2]->getAttributes(\SensitiveParameter::class));
        self::assertSame('application.catalog', $definition->catalogId);
        self::assertSame(1, $definition->catalogVersion);
        self::assertCount(1, $definition->events);
    }

    public function testWrongDeclarationShapeAndTextAreRejectedWithFiniteFailures(): void
    {
        $event = $this->event();
        foreach ([
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('application.catalog', 1, ['wrong']),
                AuditValidationFailure::ShapeInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('application.catalog', 1, [1]),
                AuditValidationFailure::ShapeInvalid,
            ],
            [static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('application.catalog', 1, [
                    0 => $event,
                    2 => $event,
                ]), AuditValidationFailure::ShapeInvalid],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('Bad', 0, ['wrong']),
                AuditValidationFailure::ShapeInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('Bad', 1, [$event]),
                AuditValidationFailure::GrammarInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('', 0, [$event]),
                AuditValidationFailure::ByteBoundInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition(str_repeat('a', 129), 0, [$event]),
                AuditValidationFailure::ByteBoundInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('application.catalog', 0, [$event]),
                AuditValidationFailure::SemanticInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition(
                    'application.catalog',
                    AuditValidationException::MAX_SAFE_INTEGER + 1,
                    [$event],
                ),
                AuditValidationFailure::SemanticInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition('application.catalog', 1, []),
                AuditValidationFailure::SemanticInvalid,
            ],
            [
                static fn(): AuditCatalogDefinition => new AuditCatalogDefinition(
                    'application.catalog',
                    1,
                    array_fill(0, 257, $event),
                ),
                AuditValidationFailure::SemanticInvalid,
            ],
        ] as [$operation, $failure]) {
            try {
                $operation();
                self::fail('Invalid declaration was accepted.');
            } catch (AuditValidationException $exception) {
                self::assertSame($failure, $exception->failure());
                self::assertSame($failure->value, $exception->getMessage());
                self::assertSame($failure->value, $exception->code());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testNativeScalarTypesAreRejectedBeforeValidationFailures(): void
    {
        try {
            new AuditCatalogDefinition('application.catalog', '1', [$this->event()]);
            self::fail('A string catalog version was accepted by a native int parameter.');
        } catch (\TypeError $exception) {
            self::assertStringContainsString('catalogVersion', $exception->getMessage());
        }

        try {
            new AuditCatalogDefinition([], 1, [$this->event()]);
            self::fail('An array catalog ID was accepted by a native string parameter.');
        } catch (\TypeError $exception) {
            self::assertStringContainsString('catalogId', $exception->getMessage());
        }
    }

    public function testMaximumValidVersionAndEventCountRemainAccepted(): void
    {
        $event = $this->event();
        $definition = new AuditCatalogDefinition(
            'a',
            AuditValidationException::MAX_SAFE_INTEGER,
            array_fill(0, 256, $event),
        );

        self::assertSame('a', $definition->catalogId);
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $definition->catalogVersion);
        self::assertCount(256, $definition->events);
    }

    private function event(): AuditEventDefinition
    {
        return new AuditEventDefinition(
            'application.event.created',
            1,
            1,
            AuditEventCategory::Business,
            ['application.event.created'],
            [new AuditSemanticTuple(
                'application.event.created',
                AuditActionPhase::Succeeded,
                'record',
                AuditOutcomeStatus::Succeeded,
                'created',
                AuditPolicyDecision::Allow,
                'created',
            )],
            [],
            1024,
            AuditDelivery::Required,
        );
    }
}
