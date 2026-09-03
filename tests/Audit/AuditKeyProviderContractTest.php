<?php

declare(strict_types=1);

namespace BlackOps\Tests\Audit;

use BlackOps\Audit\AuditOpaqueIdKeyProvider;
use BlackOps\Audit\AuditProtectionKeyProvider;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\StorageProtection\StorageKey;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class AuditKeyProviderContractTest extends TestCase
{
    public function testOnlyTheTwoApplicationKeyProvidersArePublicApis(): void
    {
        self::assertTrue(new ReflectionClass(AuditOpaqueIdKeyProvider::class)->isInterface());
        self::assertTrue(new ReflectionClass(AuditProtectionKeyProvider::class)->isInterface());
        self::assertCount(1, new ReflectionClass(AuditOpaqueIdKeyProvider::class)->getAttributes(PublicApi::class));
        self::assertCount(1, new ReflectionClass(AuditProtectionKeyProvider::class)->getAttributes(PublicApi::class));
    }

    public function testOpaqueProviderHasOneExactMethod(): void
    {
        $reflection = new ReflectionClass(AuditOpaqueIdKeyProvider::class);
        self::assertSame(
            ['key'],
            array_map(static fn(ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()),
        );
        $method = $reflection->getMethod('key');
        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(
            StorageKey::class,
            $method->getReturnType() instanceof ReflectionNamedType ? $method->getReturnType()->getName() : null,
        );
        self::assertCount(0, $method->getParameters());
    }

    public function testProtectionProviderKeepsRecordAndOutboxActiveHistorySeparate(): void
    {
        $reflection = new ReflectionClass(AuditProtectionKeyProvider::class);
        $expected = [
            'activeRecordKey' => ['partitionId'],
            'recordKey' => ['keyId', 'partitionId'],
            'activeOutboxKey' => ['partitionId'],
            'outboxKey' => ['keyId', 'partitionId'],
        ];
        self::assertSame(
            array_keys($expected),
            array_map(static fn(ReflectionMethod $method): string => $method->getName(), $reflection->getMethods()),
        );
        foreach ($expected as $name => $parameters) {
            $method = $reflection->getMethod($name);
            self::assertSame(
                StorageKey::class,
                $method->getReturnType() instanceof ReflectionNamedType ? $method->getReturnType()->getName() : null,
                $name,
            );
            self::assertSame(
                $parameters,
                array_map(
                    static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                    $method->getParameters(),
                ),
                $name,
            );
            foreach ($method->getParameters() as $parameter) {
                self::assertCount(1, $parameter->getAttributes(\SensitiveParameter::class), $name);
            }
        }
    }
}
