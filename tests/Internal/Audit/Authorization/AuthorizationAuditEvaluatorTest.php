<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authorization;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditPolicyDecision;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluation;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyBinding;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyRegistry;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class AuthorizationAuditEvaluatorTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string CORRELATION_ID = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';

    public function testNoPolicyIsDistinctAndDoesNoResolverOrPolicyWork(): void
    {
        $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow());
        $container = new EvaluatorAuditContainer($policy);
        $auditEvaluator = $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([]));

        $evaluation = $auditEvaluator->evaluate($this->metadata(null), $this->envelope());

        self::assertFalse($evaluation->hasPolicy());
        self::assertFalse($evaluation->isAllowed());
        self::assertFalse($evaluation->isDenied());
        self::assertNull($evaluation->rejection());
        self::assertNull($evaluation->policy());
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
        self::assertSame(0, $policy->calls);
    }

    public function testAllowUsesTheRealEvaluatorOncePerEvaluationAndKeepsExplicitPolicyValues(): void
    {
        $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow());
        $container = new EvaluatorAuditContainer($policy);
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata(EvaluatorAuditPolicy::class),
            'orders.authorization',
            8,
            AuditDelivery::Required,
        );
        $auditEvaluator = $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([$binding]));

        $evaluation = $auditEvaluator->evaluate(
            $this->metadata(EvaluatorAuditPolicy::class),
            $this->envelope(new ActorRef('authorization-user', 'user'), new ActorRef('worker', 'worker')),
        );
        $again = $auditEvaluator->evaluate(
            $this->metadata(EvaluatorAuditPolicy::class),
            $this->envelope(new ActorRef('authorization-user', 'user'), new ActorRef('worker', 'worker')),
        );

        self::assertTrue($evaluation->hasPolicy());
        self::assertTrue($evaluation->isAllowed());
        self::assertFalse($evaluation->isDenied());
        self::assertNull($evaluation->rejection());
        self::assertSame(AuditPolicyDecision::Allow, $evaluation->policy()?->decision);
        self::assertSame('authorization_allowed', $evaluation->policy()?->reasonCode);
        self::assertSame('orders.authorization', $evaluation->policy()?->id);
        self::assertSame(8, $evaluation->policy()?->version);
        self::assertSame(AuditDelivery::Required, $evaluation->policy()?->auditDelivery);
        self::assertTrue($again->isAllowed());
        self::assertSame(2, $policy->calls);
        self::assertSame(2, $container->hasCalls);
        self::assertSame(2, $container->getCalls);
        self::assertNotNull($policy->request);
        self::assertInstanceOf(EvaluatorAuditOperation::class, $policy->request->operation());
        self::assertSame('authorization-user', $policy->request->actor()->id());
    }

    public function testUnauthorizedAndForbiddenRemainDeniedWithOriginalReasonSeparateFromAuditReason(): void
    {
        foreach ([
            AuthorizationDecision::unauthorized('authorization.session_expired'),
            AuthorizationDecision::forbid('authorization.order_forbidden'),
        ] as $decision) {
            $policy = new EvaluatorAuditPolicy($decision);
            $container = new EvaluatorAuditContainer($policy);
            $binding = new AuthorizationAuditPolicyBinding(
                $this->metadata(EvaluatorAuditPolicy::class),
                'orders.authorization',
                1,
                AuditDelivery::Durable,
            );
            $evaluation = $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([$binding]))->evaluate(
                $this->metadata(EvaluatorAuditPolicy::class),
                $this->envelope(new ActorRef('user', 'user')),
            );

            self::assertTrue($evaluation->hasPolicy());
            self::assertFalse($evaluation->isAllowed());
            self::assertTrue($evaluation->isDenied());
            self::assertSame('authorization_denied', $evaluation->policy()?->reasonCode);
            self::assertSame(AuditPolicyDecision::Deny, $evaluation->policy()?->decision);
            self::assertSame(AuditDelivery::Durable, $evaluation->policy()?->auditDelivery);
            self::assertNotNull($evaluation->rejection());
            self::assertSame($decision->code(), $evaluation->rejection()?->code());
            self::assertSame(
                $decision->isUnauthorized() ? 'unauthorized' : 'forbidden',
                $evaluation->rejection()?->category()->value,
            );
        }
    }

    public function testMissingAuthorizationActorStillBindsButDoesNotInvokePolicy(): void
    {
        $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow());
        $container = new EvaluatorAuditContainer($policy);
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata(EvaluatorAuditPolicy::class),
            'orders.authorization',
            1,
            AuditDelivery::Durable,
        );

        $evaluation = $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([$binding]))->evaluate(
            $this->metadata(EvaluatorAuditPolicy::class),
            $this->envelope(),
        );

        self::assertTrue($evaluation->isDenied());
        self::assertSame('unauthorized', $evaluation->rejection()?->category()->value);
        self::assertSame('authorization.authentication_required', $evaluation->rejection()?->code());
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
        self::assertSame(0, $policy->calls);
    }

    public function testExecutionOnlyWorkerActorCannotAuthorizeAndDoesNotInvokePolicy(): void
    {
        $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow());
        $container = new EvaluatorAuditContainer($policy);
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata(EvaluatorAuditPolicy::class),
            'orders.authorization',
            1,
            AuditDelivery::Durable,
        );

        $evaluation = $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([$binding]))->evaluate(
            $this->metadata(EvaluatorAuditPolicy::class),
            $this->envelope(null, new ActorRef('worker', 'worker')),
        );

        self::assertTrue($evaluation->isDenied());
        self::assertSame('unauthorized', $evaluation->rejection()?->category()->value);
        self::assertSame('authorization.authentication_required', $evaluation->rejection()?->code());
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
        self::assertSame(0, $policy->calls);
    }

    public function testMissingBindingFailsBeforePrincipalValidation(): void
    {
        $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow());
        $container = new EvaluatorAuditContainer($policy);

        try {
            $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([]))->evaluate(
                $this->metadata(EvaluatorAuditPolicy::class),
                $this->envelope(),
            );
            self::fail('Expected missing binding failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            self::assertSame(0, $exception->getCode());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
        self::assertSame(0, $policy->calls);
    }

    public function testDefinitionMismatchAndMissingBindingFailBeforeDelegate(): void
    {
        $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow());
        $container = new EvaluatorAuditContainer($policy);
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata(EvaluatorAuditPolicy::class),
            'orders.authorization',
            1,
            AuditDelivery::Durable,
        );
        $auditEvaluator = $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([$binding]));

        try {
            $auditEvaluator->evaluate(
                $this->metadata(EvaluatorAuditPolicy::class),
                $this->envelope(new ActorRef('user', 'user'), operation: new EvaluatorOtherOperation()),
            );
            self::fail('Expected envelope definition failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
        self::assertSame(0, $policy->calls);

        try {
            $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([]))->evaluate(
                $this->metadata(EvaluatorAuditPolicy::class),
                $this->envelope(new ActorRef('user', 'user')),
            );
            self::fail('Expected missing binding failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
        self::assertSame(0, $policy->calls);
    }

    public function testDelegateBackendFailureBecomesFreshResourceFailure(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow(), fail: true);
            $container = new EvaluatorAuditContainer($policy);
            $binding = new AuthorizationAuditPolicyBinding(
                $this->metadata(EvaluatorAuditPolicy::class),
                'orders.authorization',
                1,
                AuditDelivery::Durable,
            );
            $envelope = $this->envelope(new ActorRef('user', 'user'));

            try {
                $this->auditEvaluator($container, new AuthorizationAuditPolicyRegistry([$binding]))->evaluate(
                    $this->metadata(EvaluatorAuditPolicy::class),
                    $envelope,
                );
                self::fail('Expected resource failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('policy backend secret', $exception->getMessage());
                self::assertStringNotContainsString('payload-secret', print_r($exception->getTrace(), true));
            }

            self::assertNotNull($policy->request);
            self::assertSame($envelope->value(), $policy->request->value());
            self::assertInstanceOf(EvaluatorAuditValue::class, $policy->request->value());
            self::assertSame('payload-secret', $policy->request->value()->secret);
            self::assertSame(1, $policy->calls);
            self::assertSame(1, $container->hasCalls);
            self::assertSame(1, $container->getCalls);

            $failingContainer = new EvaluatorAuditContainer(
                new EvaluatorAuditPolicy(AuthorizationDecision::allow()),
                fail: true,
            );
            try {
                $this->auditEvaluator($failingContainer, new AuthorizationAuditPolicyRegistry([$binding]))->evaluate(
                    $this->metadata(EvaluatorAuditPolicy::class),
                    $envelope,
                );
                self::fail('Expected container resource failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('container backend secret', $exception->getMessage());
                self::assertStringNotContainsString('payload-secret', print_r($exception->getTrace(), true));
            }
            self::assertSame(1, $failingContainer->hasCalls);
            self::assertSame(1, $failingContainer->getCalls);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testUnmarkedOriginalEvaluatorCallerCanRetainItsRequestFrame(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $policy = new EvaluatorAuditPolicy(AuthorizationDecision::allow(), fail: true);
            $envelope = $this->envelope(new ActorRef('user', 'user'));
            try {
                $this->callOriginalEvaluator(
                    new AuthorizationEvaluator(new AuthorizationPolicyResolver(new EvaluatorAuditContainer($policy))),
                    $this->metadata(EvaluatorAuditPolicy::class),
                    $envelope,
                );
                self::fail('Expected original policy failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('payload-secret', var_export($exception->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testInvalidEvaluationCategoryCannotBecomeAnAuditDecision(): void
    {
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata(EvaluatorAuditPolicy::class),
            'orders.authorization',
            1,
            AuditDelivery::Durable,
        );

        try {
            AuthorizationAuditEvaluation::fromDecision($binding, RejectionReason::businessRule('order.rejected'));
            self::fail('Expected policy shape failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testNativeSerializationAndCorruptDependencySnapshotFailClosed(): void
    {
        $binding = new AuthorizationAuditPolicyBinding(
            $this->metadata(EvaluatorAuditPolicy::class),
            'orders.authorization',
            1,
            AuditDelivery::Durable,
        );
        $auditEvaluator = $this->auditEvaluator(
            new EvaluatorAuditContainer(new EvaluatorAuditPolicy(AuthorizationDecision::allow())),
            new AuthorizationAuditPolicyRegistry([$binding]),
        );

        self::assertSame([], $auditEvaluator->__debugInfo());
        self::assertSame([], $auditEvaluator->__serialize());
        try {
            unserialize(serialize($auditEvaluator));
            self::fail('Expected evaluator unserialization failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $evaluation = AuthorizationAuditEvaluation::fromDecision($binding, null);
        self::assertSame([], $evaluation->__debugInfo());
        self::assertSame([], $evaluation->__serialize());
        try {
            unserialize(serialize($evaluation));
            self::fail('Expected evaluation unserialization failure.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $snapshots = [
            null,
            ['evaluator' => new \stdClass(), 'policies' => new \stdClass()],
            [
                'evaluator' => new AuthorizationEvaluator(new AuthorizationPolicyResolver(new EvaluatorAuditContainer(
                    new EvaluatorAuditPolicy(AuthorizationDecision::allow()),
                ))),
            ],
            [
                'evaluator' => new AuthorizationEvaluator(new AuthorizationPolicyResolver(new EvaluatorAuditContainer(
                    new EvaluatorAuditPolicy(AuthorizationDecision::allow()),
                ))),
                'policies' => new AuthorizationAuditPolicyRegistry([]),
                'extra' => true,
            ],
            [
                'policies' => new AuthorizationAuditPolicyRegistry([]),
                'evaluator' => new AuthorizationEvaluator(new AuthorizationPolicyResolver(new EvaluatorAuditContainer(
                    new EvaluatorAuditPolicy(AuthorizationDecision::allow()),
                ))),
            ],
        ];
        foreach ($snapshots as $snapshot) {
            $candidate = new \ReflectionClass(AuthorizationAuditEvaluator::class)->newInstanceWithoutConstructor();
            if ($snapshot !== null) {
                $property = new \ReflectionClass(AuthorizationAuditEvaluator::class)->getProperty('snapshot');
                $property->setValue($candidate, new \SensitiveParameterValue($snapshot));
            }
            try {
                $candidate->evaluate($this->metadata(null), $this->envelope());
                self::fail('Expected corrupt dependency snapshot failure.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    private function auditEvaluator(
        EvaluatorAuditContainer $container,
        AuthorizationAuditPolicyRegistry $registry,
    ): AuthorizationAuditEvaluator {
        return new AuthorizationAuditEvaluator(
            new AuthorizationEvaluator(new AuthorizationPolicyResolver($container)),
            $registry,
        );
    }

    private function callOriginalEvaluator(
        AuthorizationEvaluator $evaluator,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
    ): void {
        $evaluator->evaluate($metadata, $envelope);
    }

    private function metadata(?string $policy): OperationMetadata
    {
        return new OperationMetadata(
            'authorization.audit.evaluate',
            EvaluatorAuditOperation::class,
            EvaluatorAuditValue::class,
            EvaluatorAuditOperation::class,
            EvaluatorAuditOperation::class,
            Inline::class,
            authorizationPolicy: $policy,
        );
    }

    private function envelope(
        ?ActorRef $authorization = null,
        ?ActorRef $execution = null,
        ?Operation $operation = null,
    ): OperationEnvelope {
        $execution ??= $authorization;
        return new OperationEnvelope(
            $operation ?? new EvaluatorAuditOperation(),
            new EvaluatorAuditValue(),
            new ExecutionContext(
                OperationId::fromString(self::OPERATION_ID),
                new DateTimeImmutable('2026-07-17T00:00:00Z'),
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: $execution === null && $authorization === null
                    ? null
                    : new ActorContext($authorization, $authorization, $execution ?? new ActorRef('worker', 'worker')),
            ),
            new Inline(),
        );
    }
}

final readonly class EvaluatorAuditOperation implements Operation {}

final readonly class EvaluatorOtherOperation implements Operation {}

final readonly class EvaluatorAuditValue implements OperationValue
{
    public function __construct(
        public string $secret = 'payload-secret',
    ) {}
}

final class EvaluatorAuditPolicy implements AuthorizationPolicy
{
    public int $calls = 0;
    public ?AuthorizationRequest $request = null;

    public function __construct(
        private readonly AuthorizationDecision $decision,
        private readonly bool $fail = false,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        ++$this->calls;
        $this->request = $request;
        if ($this->fail) {
            throw new RuntimeException('policy backend secret');
        }

        return $this->decision;
    }
}

final class EvaluatorAuditContainer implements ContainerInterface
{
    public int $hasCalls = 0;
    public int $getCalls = 0;

    public function __construct(
        private readonly object $service,
        private readonly bool $available = true,
        private readonly bool $fail = false,
    ) {}

    public function get(string $id): mixed
    {
        ++$this->getCalls;
        if ($this->fail) {
            throw new RuntimeException('container backend secret');
        }

        return $this->service;
    }

    public function has(string $id): bool
    {
        ++$this->hasCalls;

        return $this->available;
    }
}
