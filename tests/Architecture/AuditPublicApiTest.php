<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AuditPublicApiTest extends TestCase
{
    public function testA1TypesHaveNoPublicApiMarker(): void
    {
        foreach ([
            'BlackOps\\Core\\Audit\\AuditActionPhase',
            'BlackOps\\Core\\Audit\\AuditCatalogHash',
            'BlackOps\\Core\\Audit\\AuditDelivery',
            'BlackOps\\Core\\Audit\\AuditEventCatalog',
            'BlackOps\\Core\\Audit\\AuditEventCatalogLookup',
            'BlackOps\\Core\\Audit\\AuditEventCategory',
            'BlackOps\\Core\\Audit\\AuditEventDefinition',
            'BlackOps\\Core\\Audit\\AuditEvidenceClassification',
            'BlackOps\\Core\\Audit\\AuditEvidenceFieldDefinition',
            'BlackOps\\Core\\Audit\\AuditEvidenceType',
            'BlackOps\\Core\\Audit\\AuditOpaqueId',
            'BlackOps\\Core\\Audit\\AuditOpaqueIdPurpose',
            'BlackOps\\Core\\Audit\\AuditOutcomeStatus',
            'BlackOps\\Core\\Audit\\AuditPolicyDecision',
            'BlackOps\\Core\\Audit\\AuditReadAuthorizationDecision',
            'BlackOps\\Core\\Audit\\AuditReadContext',
            'BlackOps\\Core\\Audit\\AuditReadPurpose',
            'BlackOps\\Core\\Audit\\AuditReadRequestBasis',
            'BlackOps\\Core\\Audit\\AuditSemanticTuple',
            'BlackOps\\Core\\Audit\\AuditSourceOwner',
            'BlackOps\\Core\\Audit\\AuditTypedReference',
            'BlackOps\\Core\\Audit\\AuditValidationException',
            'BlackOps\\Core\\Audit\\AuditValidationFailure',
            'BlackOps\\Internal\\Audit\\AuditJcsCodec',
            'BlackOps\\Internal\\Audit\\AuditJcsObject',
            'BlackOps\\Internal\\Audit\\AuditLengthPrefix',
            'BlackOps\\Internal\\Audit\\AuditReadContextCodec',
            'BlackOps\\Internal\\Audit\\AuditStringValidator',
            'BlackOps\\Internal\\Audit\\Catalog\\AuditEventCatalogCodec',
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkAuditEventCatalog',
            'BlackOps\\Internal\\Audit\\Catalog\\FrameworkOperationalAuditEventCatalog',
            'BlackOps\\Internal\\Audit\\Catalog\\ImmutableAuditEventCatalogLookup',
            'BlackOps\\Internal\\Audit\\HmacAuditOpaqueIdDeriver',
        ] as $class) {
            self::assertCount(0, new \ReflectionClass($class)->getAttributes('BlackOps\\Core\\Attribute\\PublicApi'));
        }
    }

    public function testEveryPublicCoreAuditSignatureIsClosedAndInternalFree(): void
    {
        foreach (glob(__DIR__ . '/../../src/Core/Audit/*.php') ?: [] as $path) {
            $class = 'BlackOps\\Core\\Audit\\' . basename($path, '.php');
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getNamespaceName() !== 'BlackOps\\Core\\Audit')
                    continue;
                foreach ($method->getParameters() as $parameter)
                    self::assertClosedType($parameter->getType(), $class . '::' . $method->getName() . ' parameter');
                self::assertClosedType($method->getReturnType(), $class . '::' . $method->getName() . ' return');
            }
        }
    }

    private static function assertClosedType(?\ReflectionType $type, string $subject): void
    {
        if ($type === null)
            return;
        $types =
            $type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType
                ? $type->getTypes()
                : [$type];
        foreach ($types as $member) {
            $name = $member->getName();
            self::assertNotSame('mixed', $name, $subject);
            self::assertNotSame('object', $name, $subject);
            self::assertNotSame('Throwable', $name, $subject);
            self::assertFalse(str_starts_with($name, 'BlackOps\\Internal\\'), $subject);
        }
    }
}
