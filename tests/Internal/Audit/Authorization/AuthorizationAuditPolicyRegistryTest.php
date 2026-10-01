<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authorization;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyBinding;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyRegistry;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use PHPUnit\Framework\TestCase;

final class AuthorizationAuditPolicyRegistryTest extends TestCase
{
    public function testBindingCopiesOnlyExplicitPolicyMetadataAndValues(): void
    {
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.bound'),
            'orders.authorization',
            7,
            AuditDelivery::Required,
        );

        self::assertSame('registry.bound', $binding->operationTypeId);
        self::assertSame(RegistryOperation::class, $binding->operationClass);
        self::assertSame(RegistryPolicy::class, $binding->policyClass);
        self::assertSame('orders.authorization', $binding->policyId);
        self::assertSame(7, $binding->policyVersion);
        self::assertSame(AuditDelivery::Required, $binding->delivery);
    }

    public function testRegistryResolvesOnlyAnExactDefinitionTypeAndPolicyTriple(): void
    {
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.bound'),
            'orders.authorization',
            7,
            AuditDelivery::Durable,
        );
        $registry = new AuthorizationAuditPolicyRegistry([$binding]);

        self::assertSame($binding, $registry->resolve($this->metadata('registry.bound')));

        foreach ([
            $this->metadata('registry.other'),
            $this->metadata('registry.bound', RegistryOtherOperation::class),
            $this->metadata('registry.bound', RegistryOperation::class, RegistryOtherPolicy::class),
        ] as $metadata) {
            try {
                $registry->resolve($metadata);
                self::fail('Expected an exact binding lookup failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
                self::assertSame(0, $exception->getCode());
            }
        }
    }

    public function testRegistryAllowsConsistentPolicyIdentityAcrossOperations(): void
    {
        $first = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.first'),
            'shared.authorization',
            3,
            AuditDelivery::Durable,
        );
        $second = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.second', RegistryOtherOperation::class),
            'shared.authorization',
            3,
            AuditDelivery::Durable,
        );
        $registry = new AuthorizationAuditPolicyRegistry([$first, $second]);

        self::assertSame($first, $registry->resolve($this->metadata('registry.first')));
        self::assertSame(
            $second,
            $registry->resolve($this->metadata('registry.second', RegistryOtherOperation::class)),
        );
    }

    public function testRegistryDoesNotRetainCallerArrayElementReferences(): void
    {
        $bindingA = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.alias'),
            'original.authorization',
            1,
            AuditDelivery::Required,
        );
        $bindingB = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.alias'),
            'replacement.authorization',
            2,
            AuditDelivery::Durable,
        );
        $slot = $bindingA;
        $registry = new AuthorizationAuditPolicyRegistry([&$slot]);

        $slot = $bindingB;

        self::assertSame($bindingA, $registry->resolve($this->metadata('registry.alias')));
    }

    public function testRegistryRejectsDuplicateDefinitionsTypesAndConflictingPolicyIdentity(): void
    {
        $definitionDuplicate = [
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.first'),
                'first.authorization',
                1,
                AuditDelivery::Durable,
            ),
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.second'),
                'second.authorization',
                1,
                AuditDelivery::Required,
            ),
        ];
        $typeDuplicate = [
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.same'),
                'first.authorization',
                1,
                AuditDelivery::Durable,
            ),
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.same', RegistryOtherOperation::class),
                'second.authorization',
                1,
                AuditDelivery::Durable,
            ),
        ];
        $conflictingIdentity = [
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.first'),
                'shared.authorization',
                3,
                AuditDelivery::Durable,
            ),
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.second', RegistryOtherOperation::class, RegistryOtherPolicy::class),
                'shared.authorization',
                3,
                AuditDelivery::Required,
            ),
        ];
        $conflictingPolicyClass = [
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.first'),
                'shared.authorization',
                3,
                AuditDelivery::Durable,
            ),
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.second', RegistryOtherOperation::class, RegistryOtherPolicy::class),
                'shared.authorization',
                3,
                AuditDelivery::Durable,
            ),
        ];
        $conflictingDelivery = [
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.first'),
                'shared.authorization',
                3,
                AuditDelivery::Durable,
            ),
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.second', RegistryOtherOperation::class),
                'shared.authorization',
                3,
                AuditDelivery::Required,
            ),
        ];

        foreach ([
            $definitionDuplicate,
            $typeDuplicate,
            $conflictingIdentity,
            $conflictingPolicyClass,
            $conflictingDelivery,
            [1 => $definitionDuplicate[0]],
            [$definitionDuplicate[0], new \stdClass()],
        ] as $bindings) {
            try {
                new AuthorizationAuditPolicyRegistry($bindings);
                self::fail('Expected registry configuration failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testRegistryRejectsMalformedNonNullMetadataFields(): void
    {
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.valid'),
            'valid.authorization',
            1,
            AuditDelivery::Durable,
        );
        $registry = new AuthorizationAuditPolicyRegistry([$binding]);

        foreach ([
            $this->metadata(''),
            $this->metadata('registry.valid', ''),
            $this->metadata('registry.valid', RegistryOperation::class, ''),
        ] as $metadata) {
            try {
                $registry->resolve($metadata);
                self::fail('Expected malformed metadata failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testBindingValidationFailsWithFreshValueFreeConfigurationFailure(): void
    {
        foreach ([
            ['policy' => null, 'id' => 'valid.policy', 'version' => 1],
            ['policy' => RegistryPolicy::class, 'id' => '', 'version' => 1],
            ['policy' => RegistryPolicy::class, 'id' => 'valid.policy', 'version' => 0],
            ['policy' => RegistryPolicy::class, 'id' => "bad\u{202E}policy", 'version' => 1],
        ] as $invalid) {
            try {
                new AuthorizationAuditPolicyBinding(
                    $this->metadata('registry.invalid', RegistryOperation::class, $invalid['policy']),
                    $invalid['id'],
                    $invalid['version'],
                    AuditDelivery::Durable,
                );
                self::fail('Expected binding configuration failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('bad', $exception->getMessage());
            }
        }

        $maximum = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.maximum'),
            'maximum.policy',
            AuditValidationException::MAX_SAFE_INTEGER,
            AuditDelivery::Durable,
        );
        self::assertSame(AuditValidationException::MAX_SAFE_INTEGER, $maximum->policyVersion);

        try {
            new AuthorizationAuditPolicyBinding(
                $this->metadata('registry.too_large'),
                'maximum.policy',
                AuditValidationException::MAX_SAFE_INTEGER + 1,
                AuditDelivery::Durable,
            );
            self::fail('Expected out of range policy version failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testNativeSerializationCannotReconstructBindingOrRegistry(): void
    {
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata('registry.serialized'),
            'serialized.authorization',
            1,
            AuditDelivery::Durable,
        );
        $registry = new AuthorizationAuditPolicyRegistry([$binding]);

        foreach ([$binding, $registry] as $value) {
            self::assertSame([], $value->__debugInfo());
            self::assertSame([], $value->__serialize());
            try {
                unserialize(serialize($value));
                self::fail('Expected native unserialization failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    private function metadata(
        string $typeId,
        string $definition = RegistryOperation::class,
        ?string $policy = RegistryPolicy::class,
    ): OperationMetadata {
        return new OperationMetadata(
            $typeId,
            $definition,
            RegistryValue::class,
            RegistryOperation::class,
            RegistryOperation::class,
            'BlackOps\\Core\\Execution\\Inline',
            authorizationPolicy: $policy,
        );
    }
}

final readonly class RegistryOperation implements Operation {}

final readonly class RegistryOtherOperation implements Operation {}

final readonly class RegistryValue implements OperationValue {}

final class RegistryPolicy implements AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}

final class RegistryOtherPolicy implements AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}
