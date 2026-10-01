<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authorization;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\TenantRef;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEvaluation;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditEventFactory;
use BlackOps\Internal\Audit\Authorization\AuthorizationAuditPolicyBinding;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;
use ReflectionProperty;

final class AuthorizationAuditEventFactoryTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string CORRELATION_ID = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';
    private const string CAUSATION_ID = '019f32ad-2be0-7b38-a0a7-1ab2f9687697';

    public function testAllowAndDenyProjectExactBoundedFieldsAndVersions(): void
    {
        foreach ([
            [
                AuthorizationAuditEvaluation::fromDecision($this->binding(AuditDelivery::Durable), null),
                AuditDelivery::Durable,
                'security.authorization.allow',
                1,
                'succeeded',
                'authorization_allowed',
                null,
            ],
            [
                AuthorizationAuditEvaluation::fromDecision(
                    $this->binding(AuditDelivery::Required),
                    RejectionReason::forbidden('order.forbidden'),
                ),
                AuditDelivery::Required,
                'security.authorization.deny',
                2,
                'denied',
                'authorization_denied',
                'order.forbidden',
            ],
            [
                AuthorizationAuditEvaluation::fromDecision(
                    $this->binding(AuditDelivery::Required),
                    RejectionReason::unauthorized('user.unauthorized'),
                ),
                AuditDelivery::Required,
                'security.authorization.deny',
                2,
                'denied',
                'authorization_denied',
                'user.unauthorized',
            ],
        ] as [$evaluation, $delivery, $eventType, $version, $phase, $reason, $rejectionCode]) {
            $clock = new FactoryTestClock();
            $identifiers = new FactoryTestIdentifiers();
            $factory = $this->factory($clock, $identifiers);
            $request = $factory->create($evaluation, $this->context());

            self::assertNotNull($request);
            $draft = $request->draft();
            $value = $draft->toArray();
            self::assertSame($eventType, $value['event']['type']);
            self::assertSame($version, $value['event']['version']);
            self::assertSame($version, $value['evidence']['version']);
            self::assertSame($eventType, $value['action']['type']);
            self::assertSame($phase, $value['action']['phase']);
            self::assertSame('security', $value['event']['category']);
            self::assertSame(FrameworkAuditEventCatalog::catalog()->catalogHash, $value['event']['catalogHash']);
            self::assertSame('operation', $value['resource']['type']);
            self::assertSame($reason, $value['outcome']['code']);
            self::assertSame($reason === 'authorization_allowed' ? 'allow' : 'deny', $value['policy']['decision']);
            self::assertSame(8, $value['policy']['version']);
            self::assertSame($reason, $value['policy']['reasonCode']);
            self::assertSame($delivery->value, $value['policy']['auditDelivery']);
            self::assertSame(
                [
                    'owner' => 'framework',
                    'component' => 'operation-authorization',
                    'runtime' => 'http',
                    'buildId' => 'build-test',
                ],
                $value['source'],
            );
            self::assertSame(
                [
                    'principal' => ['type' => 'user', 'id' => 'principal-1'],
                    'execution' => ['type' => 'worker', 'id' => 'execution-1'],
                ],
                $value['actor'],
            );
            self::assertSame(['type' => 'tenant', 'id' => 'tenant-1'], $value['tenant']);
            self::assertMatchesRegularExpression('/^oid:v1:[0-9a-f]{64}$/', $value['action']['id']);
            self::assertMatchesRegularExpression('/^oid:v1:[0-9a-f]{64}$/', $value['resource']['id']);
            self::assertSame(
                [
                    'operationId' => self::OPERATION_ID,
                    'correlationId' => self::CORRELATION_ID,
                    'causationId' => self::CAUSATION_ID,
                ],
                $value['correlation'],
            );
            self::assertSame(['version' => $version], $value['evidence']);
            $serialized = json_encode($value, JSON_THROW_ON_ERROR);
            if ($rejectionCode !== null) {
                self::assertStringNotContainsString($rejectionCode, $serialized);
            }
            self::assertStringNotContainsString('payload-secret', $serialized);
            if ($delivery === AuditDelivery::Durable) {
                self::assertSame('operation-authorization:' . $value['action']['id'], $request->idempotencyKey());
            } else {
                self::assertNull($request->idempotencyKey());
            }
            self::assertSame(1, $clock->calls);
            self::assertSame(1, $identifiers->calls);
        }
    }

    public function testLongPublicRejectionCodeIsRetainedOnlyOutsideTheAuditProjection(): void
    {
        $publicCode = str_repeat('a', 512);
        $factory = $this->factory(new FactoryTestClock(), new FactoryTestIdentifiers());
        $request = $factory->create(
            AuthorizationAuditEvaluation::fromDecision(
                $this->binding(AuditDelivery::Required),
                RejectionReason::forbidden($publicCode),
            ),
            $this->context(),
        );

        self::assertNotNull($request);
        $serialized = json_encode($request->draft()->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($publicCode, $serialized);
        self::assertSame('authorization_denied', $request->draft()->outcome->code);
        self::assertSame('authorization_denied', $request->draft()->policy->reasonCode);
    }

    public function testExecutionFallbackAndNullTenantAreProjectedWithoutInference(): void
    {
        $clock = new FactoryTestClock();
        $identifiers = new FactoryTestIdentifiers();
        $factory = $this->factory($clock, $identifiers);
        $evaluation = AuthorizationAuditEvaluation::fromDecision($this->binding(AuditDelivery::Required), null);
        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-17T00:00:00Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: null,
        );
        $request = $factory->create($evaluation, $context);

        self::assertNotNull($request);
        self::assertNull($request->draft()->actor->principal);
        self::assertSame(['type' => 'worker', 'id' => 'fallback-1'], $request->draft()->actor->execution->toArray());
        self::assertNull($request->draft()->tenant);
    }

    public function testNoPolicyDoesNoUuidClockOrCatalogProjectionWork(): void
    {
        $clock = new FactoryTestClock();
        $identifiers = new FactoryTestIdentifiers();
        $factory = $this->factory($clock, $identifiers);

        self::assertNull($factory->create(AuthorizationAuditEvaluation::noPolicy(), $this->context()));
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $identifiers->calls);
    }

    public function testRepeatedCreationGetsFreshActionIdentities(): void
    {
        $clock = new FactoryTestClock();
        $identifiers = new FactoryTestIdentifiers([
            '019f32ae-2be0-7b38-a0a7-1ab2f9687697',
            '019f32af-2be0-7b38-a0a7-1ab2f9687697',
        ]);
        $factory = $this->factory($clock, $identifiers);
        $evaluation = AuthorizationAuditEvaluation::fromDecision($this->binding(AuditDelivery::Durable), null);

        $first = $factory->create($evaluation, $this->context());
        $second = $factory->create($evaluation, $this->context());

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->draft()->action->id, $second->draft()->action->id);
        self::assertNotSame($first->idempotencyKey(), $second->idempotencyKey());
        self::assertSame(2, $identifiers->calls);
        self::assertSame(2, $clock->calls);
    }

    public function testProjectionAndFactorySnapshotsFailSafely(): void
    {
        $factory = $this->factory(new FactoryTestClock(), new FactoryTestIdentifiers());
        self::assertSame([], $factory->__debugInfo());
        self::assertSame([], $factory->__serialize());

        try {
            unserialize(serialize($factory));
            self::fail('Factory unserialization must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $uuidFailure = $this->factory(new FactoryTestClock(), new FactoryTestIdentifiers(fail: true));
        try {
            $uuidFailure->create(
                AuthorizationAuditEvaluation::fromDecision($this->binding(AuditDelivery::Durable), null),
                $this->context(),
            );
            self::fail('UUID failure must not become a decision.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testInvalidReferencesEvaluationClockFallbackAndSnapshotsFailClosed(): void
    {
        $factory = $this->factory(new FactoryTestClock(), new FactoryTestIdentifiers());
        $evaluation = AuthorizationAuditEvaluation::fromDecision($this->binding(AuditDelivery::Required), null);

        $invalidActor = new ReflectionClass(ActorRef::class)->newInstanceWithoutConstructor();
        try {
            $factory->create(
                $evaluation,
                $this->contextWithActor(new ActorContext(null, $invalidActor, new ActorRef('execution-1', 'worker'))),
            );
            self::fail('Invalid actor references must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $invalidTenant = new ReflectionClass(TenantRef::class)->newInstanceWithoutConstructor();
        try {
            $factory->create($evaluation, $this->contextWithTenant($invalidTenant));
            self::fail('Invalid tenant references must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $uninitializedEvaluation = new ReflectionClass(AuthorizationAuditEvaluation::class)->newInstanceWithoutConstructor();
        try {
            $factory->create($uninitializedEvaluation, $this->context());
            self::fail('Invalid evaluations must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $previous = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $clockFailure = $this->factory(new FactoryTestClock(fail: true), new FactoryTestIdentifiers());
            $clockContext = $this->contextWithActor(
                new ActorContext(
                    new ActorRef('payload-secret', 'user'),
                    new ActorRef('principal-1', 'user'),
                    new ActorRef('execution-1', 'worker'),
                ),
            );
            self::assertSame('payload-secret', $clockContext->actorContext()?->origin()?->id());
            try {
                $clockFailure->create($evaluation, $clockContext);
                self::fail('Clock failure must fail closed.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('clock backend secret canary', print_r(
                    $exception->getTrace(),
                    true,
                ));
                self::assertStringNotContainsString('payload-secret', print_r($exception->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }

        $invalidFallback = new ReflectionClass(ActorRef::class)->newInstanceWithoutConstructor();
        try {
            new AuthorizationAuditEventFactory(
                new HmacAuditOpaqueIdDeriver(str_repeat('k', 32)),
                new FactoryTestIdentifiers(),
                new FactoryTestClock(),
                AuditSourceRuntime::Http,
                'build-test',
                $invalidFallback,
            );
            self::fail('Invalid execution fallback configuration must fail closed.');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }

        $snapshots = [
            null,
            ['opaqueIds' => new HmacAuditOpaqueIdDeriver(str_repeat('k', 32))],
            [
                'opaqueIds' => new HmacAuditOpaqueIdDeriver(str_repeat('k', 32)),
                'identifiers' => new FactoryTestIdentifiers(),
                'clock' => new FactoryTestClock(),
                'runtime' => AuditSourceRuntime::Http,
                'buildId' => 'build-test',
                'executionFallback' => new \stdClass(),
            ],
            [
                'opaqueIds' => new HmacAuditOpaqueIdDeriver(str_repeat('k', 32)),
                'identifiers' => new FactoryTestIdentifiers(),
                'clock' => new FactoryTestClock(),
                'runtime' => AuditSourceRuntime::Http,
                'buildId' => 'build-test',
                'executionFallback' => new ActorRef('fallback-1', 'worker'),
                'extra' => true,
            ],
            [
                'opaqueIds' => new HmacAuditOpaqueIdDeriver(str_repeat('k', 32)),
                'identifiers' => new FactoryTestIdentifiers(),
                'clock' => new FactoryTestClock(),
                'runtime' => AuditSourceRuntime::Http,
                'buildId' => '',
                'executionFallback' => new ActorRef('fallback-1', 'worker'),
            ],
        ];
        foreach ($snapshots as $snapshot) {
            $candidate = new ReflectionClass(AuthorizationAuditEventFactory::class)->newInstanceWithoutConstructor();
            if ($snapshot !== null) {
                new ReflectionProperty(AuthorizationAuditEventFactory::class, 'snapshot')->setValue(
                    $candidate,
                    new \SensitiveParameterValue($snapshot),
                );
            }
            try {
                $candidate->create(AuthorizationAuditEvaluation::noPolicy(), $this->context());
                self::fail('Corrupt factory snapshots must fail closed.');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testUnmarkedCallerFramePrivacyLimitRemainsExplicit(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $context = $this->contextWithActor(
                new ActorContext(
                    new ActorRef('origin-1', 'user'),
                    new ActorRef('caller-frame-secret', 'user'),
                    new ActorRef('execution-1', 'worker'),
                ),
            );
            try {
                $this->callFactoryFromUnmarkedFrame(
                    $this->factory(new FactoryTestClock(), new FactoryTestIdentifiers()),
                    AuthorizationAuditEvaluation::fromDecision($this->binding(AuditDelivery::Required), null),
                    $context,
                );
                self::fail('The caller-frame fixture must throw.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('caller-frame-secret', var_export($exception->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    private function factory(
        FactoryTestClock $clock,
        FactoryTestIdentifiers $identifiers,
    ): AuthorizationAuditEventFactory {
        return new AuthorizationAuditEventFactory(
            new HmacAuditOpaqueIdDeriver(str_repeat('k', 32)),
            $identifiers,
            $clock,
            AuditSourceRuntime::Http,
            'build-test',
            new ActorRef('fallback-1', 'worker'),
        );
    }

    private function binding(AuditDelivery $delivery): AuthorizationAuditPolicyBinding
    {
        return new AuthorizationAuditPolicyBinding($this->metadata(), 'orders.authorization', 8, $delivery);
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'authorization.audit.factory',
            FactoryAuditOperation::class,
            FactoryAuditValue::class,
            FactoryAuditOperation::class,
            FactoryAuditOperation::class,
            Inline::class,
            authorizationPolicy: FactoryAuditPolicy::class,
        );
    }

    private function context(): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-17T00:00:00Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            CausationId::fromString(self::CAUSATION_ID),
            actorContext: new ActorContext(
                new ActorRef('origin-1', 'user'),
                new ActorRef('principal-1', 'user'),
                new ActorRef('execution-1', 'worker'),
            ),
            tenant: new TenantRef('tenant', 'tenant-1'),
        );
    }

    private function contextWithActor(ActorContext $actorContext): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-17T00:00:00Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: $actorContext,
            tenant: new TenantRef('tenant', 'tenant-1'),
        );
    }

    private function contextWithTenant(TenantRef $tenant): ExecutionContext
    {
        return new ExecutionContext(
            OperationId::fromString(self::OPERATION_ID),
            new DateTimeImmutable('2026-07-17T00:00:00Z'),
            CorrelationId::fromString(self::CORRELATION_ID),
            actorContext: new ActorContext(
                new ActorRef('origin-1', 'user'),
                new ActorRef('principal-1', 'user'),
                new ActorRef('execution-1', 'worker'),
            ),
            tenant: $tenant,
        );
    }

    private function callFactoryFromUnmarkedFrame(
        AuthorizationAuditEventFactory $factory,
        AuthorizationAuditEvaluation $evaluation,
        ExecutionContext $context,
    ): void {
        $factory->create($evaluation, $context);
        throw new \RuntimeException('caller frame');
    }
}

final class FactoryTestClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly bool $fail = false,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;
        if ($this->fail) {
            throw new \RuntimeException('clock backend secret canary');
        }

        return new DateTimeImmutable('2026-07-17T01:02:03.123456+02:00');
    }
}

final class FactoryTestIdentifiers implements Uuidv7Generator
{
    public int $calls = 0;

    /** @var list<string> */
    private array $values;

    public function __construct(
        array $values = [],
        private readonly bool $fail = false,
    ) {
        $this->values = $values !== [] ? $values : ['019f32ad-2be0-7b38-a0a7-1ab2f9687697'];
    }

    public function generate(): string
    {
        ++$this->calls;
        if ($this->fail) {
            throw new \RuntimeException('identifier backend canary');
        }

        return array_shift($this->values) ?? '019f32b0-2be0-7b38-a0a7-1ab2f9687697';
    }
}

final readonly class FactoryAuditOperation implements Operation {}

final readonly class FactoryAuditValue implements OperationValue
{
    public function __construct(
        public string $secret = 'payload-secret',
    ) {}
}

final class FactoryAuditPolicy implements \BlackOps\Core\Authorization\AuthorizationPolicy
{
    public function decide(\BlackOps\Core\Authorization\AuthorizationRequest $request): \BlackOps\Core\Authorization\AuthorizationDecision
    {
        return \BlackOps\Core\Authorization\AuthorizationDecision::allow();
    }
}
