<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Audit;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditReadRequestBasis;
use BlackOps\Core\Audit\AuditTypedReference;
use PHPUnit\Framework\TestCase;

final class AuditReadContextTest extends TestCase
{
    public function testOnlyBasisFactoryCanCreateDistinctDecisionsForOneCorrelationReference(): void
    {
        $reflection = new \ReflectionClass(AuditReadContext::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $staticMethods = array_values(array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn(\ReflectionMethod $method): bool => $method->isStatic(),
        ));
        self::assertSame(
            ['fromBasis'],
            array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $staticMethods),
        );
        $factory = $reflection->getMethod('fromBasis');
        self::assertSame(
            ['basis', 'decision'],
            array_map(
                static fn(\ReflectionParameter $parameter): string => $parameter->getName(),
                $factory->getParameters(),
            ),
        );

        $basis = new AuditReadRequestBasis(
            AuditReadPurpose::Status,
            null,
            new AuditTypedReference('execution', 'e1'),
            null,
            '2026-08-28T01:02:03.123456Z',
            'nonce-1',
        );
        $allow = AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Allow);
        $deny = AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Deny);
        $contexts = [$allow, $deny];

        self::assertCount(2, $contexts);
        self::assertSame($allow->requestRef, $deny->requestRef);
        self::assertSame($basis->requestRef(), $allow->requestRef);
        self::assertSame(AuditReadAuthorizationDecision::Allow, $allow->authorizationDecision);
        self::assertSame(AuditReadAuthorizationDecision::Deny, $deny->authorizationDecision);
        self::assertNotSame($allow->toArray(), $deny->toArray());
        self::assertNotSame($allow, $deny);
    }

    public function testTrustedContextHasTenFieldsAndBasisByteEquality(): void
    {
        $basis = new AuditReadRequestBasis(
            AuditReadPurpose::Journal,
            new AuditTypedReference('actor', 'ID:AbC/01'),
            new AuditTypedReference('execution', 'e1'),
            new AuditTypedReference('tenant', 't1'),
            '2026-08-28T01:02:03.123456Z',
            'nonce-1',
        );
        $context = AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Allow);
        self::assertSame($basis->toArray(), array_intersect_key($context->toArray(), $basis->toArray()));
        self::assertSame('audit_read_context', $context->toArray()['kind']);
        self::assertSame($basis->requestRef(), $context->requestRef);
    }

    public function testPreservationCanarySurvivesEveryConsumerSlot(): void
    {
        $canary = new AuditTypedReference(' Type/V1 ', 'ID:AbC/01');
        $variants = [
            'actor' => [$canary, new AuditTypedReference('execution', 'e'), null],
            'execution' => [null, $canary, null],
            'tenant' => [null, new AuditTypedReference('execution', 'e'), $canary],
        ];
        foreach ($variants as [$actor, $execution, $tenant]) {
            $basis = new AuditReadRequestBasis(
                AuditReadPurpose::Status,
                $actor,
                $execution,
                $tenant,
                '2026-08-28T01:02:03.123456Z',
                'nonce-1',
            );
            $array = AuditReadContext::fromBasis($basis, AuditReadAuthorizationDecision::Deny)->toArray();
            self::assertSame($actor?->toArray(), $array['actor']);
            self::assertSame($execution->toArray(), $array['execution']);
            self::assertSame($tenant?->toArray(), $array['tenant']);
        }
    }
}
