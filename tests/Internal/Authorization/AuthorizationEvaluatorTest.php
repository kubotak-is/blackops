<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Authorization;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

final class AuthorizationEvaluatorTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const string CORRELATION_ID = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';

    public function testPolicylessOperationPassesWithoutContainerLookupOrActor(): void
    {
        $container = new EvaluatorContainer(new \stdClass());
        $evaluator = new AuthorizationEvaluator(new AuthorizationPolicyResolver($container));

        $reason = $evaluator->evaluate($this->metadata(null), $this->envelope());

        self::assertNull($reason);
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
    }

    public function testMissingAuthorizationActorRejectsWithoutResolvingPolicy(): void
    {
        $container = new EvaluatorContainer(new \stdClass());
        $reason = new AuthorizationEvaluator(new AuthorizationPolicyResolver($container))->evaluate(
            $this->metadata(EvaluatorPolicy::class),
            $this->envelope(),
        );

        self::assertSame('unauthorized', $reason?->category()->value);
        self::assertSame('authorization.authentication_required', $reason?->code());
        self::assertSame(0, $container->hasCalls);
        self::assertSame(0, $container->getCalls);
    }

    public function testAllowPassesCompleteAuthorizationRequest(): void
    {
        $policy = new EvaluatorPolicy(AuthorizationDecision::allow());
        $actor = new ActorRef('user-123', 'user');
        $envelope = $this->envelope($actor);
        $reason = $this->evaluator($policy)->evaluate($this->metadata(EvaluatorPolicy::class), $envelope);

        self::assertNull($reason);
        self::assertSame($envelope->definition(), $policy->request?->operation());
        self::assertSame($envelope->value(), $policy->request?->value());
        self::assertSame($envelope->context(), $policy->request?->context());
        self::assertSame($actor, $policy->request?->actor());
    }

    public function testUnauthorizedAndForbiddenDecisionsKeepStableCode(): void
    {
        $actor = new ActorRef('user-123', 'user');
        $unauthorized = $this->evaluator(new EvaluatorPolicy(AuthorizationDecision::unauthorized(
            'authorization.session_expired',
        )))->evaluate($this->metadata(EvaluatorPolicy::class), $this->envelope($actor));
        $forbidden = $this->evaluator(new EvaluatorPolicy(AuthorizationDecision::forbid(
            'authorization.order_forbidden',
        )))->evaluate($this->metadata(EvaluatorPolicy::class), $this->envelope($actor));

        self::assertSame('unauthorized', $unauthorized?->category()->value);
        self::assertSame('authorization.session_expired', $unauthorized?->code());
        self::assertSame('forbidden', $forbidden?->category()->value);
        self::assertSame('authorization.order_forbidden', $forbidden?->code());
    }

    public function testUnavailableAndInvalidPolicyServiceFailFastWithoutIdExposure(): void
    {
        $actor = new ActorRef('user-123', 'user');

        foreach ([
            new EvaluatorContainer(new \stdClass(), available: false),
            new EvaluatorContainer(new \stdClass()),
        ] as $container) {
            try {
                new AuthorizationEvaluator(new AuthorizationPolicyResolver($container))->evaluate(
                    $this->metadata(EvaluatorPolicy::class),
                    $this->envelope($actor),
                );
                self::fail('Expected policy resolution failure.');
            } catch (LogicException $exception) {
                self::assertStringNotContainsString(EvaluatorPolicy::class, $exception->getMessage());
            }
        }
    }

    public function testContainerAndPolicyExceptionsPropagateWithoutDenialConversion(): void
    {
        $actor = new ActorRef('user-123', 'user');
        $containerFailure = new RuntimeException('container backend credential detail');
        $policyFailure = new RuntimeException('policy backend credential detail');

        foreach ([
            [new EvaluatorContainer(new \stdClass(), failure: $containerFailure), $containerFailure],
            [
                new EvaluatorContainer(new EvaluatorPolicy(AuthorizationDecision::allow(), $policyFailure)),
                $policyFailure,
            ],
        ] as [$container, $expected]) {
            try {
                new AuthorizationEvaluator(new AuthorizationPolicyResolver($container))->evaluate(
                    $this->metadata(EvaluatorPolicy::class),
                    $this->envelope($actor),
                );
                self::fail('Expected authorization backend failure.');
            } catch (RuntimeException $exception) {
                self::assertSame($expected, $exception);
            }
        }
    }

    private function evaluator(AuthorizationPolicy $policy): AuthorizationEvaluator
    {
        return new AuthorizationEvaluator(new AuthorizationPolicyResolver(new EvaluatorContainer($policy)));
    }

    /** @param class-string<AuthorizationPolicy>|null $policy */
    private function metadata(?string $policy): OperationMetadata
    {
        return new OperationMetadata(
            'authorization.evaluate',
            EvaluatorOperation::class,
            EvaluatorValue::class,
            EvaluatorOperation::class,
            EmptyOutcome::class,
            Inline::class,
            authorizationPolicy: $policy,
        );
    }

    private function envelope(?ActorRef $actor = null): OperationEnvelope
    {
        return new OperationEnvelope(
            new EvaluatorOperation(),
            new EvaluatorValue(),
            new ExecutionContext(
                OperationId::fromString(self::OPERATION_ID),
                new DateTimeImmutable('2026-07-17T00:00:00Z'),
                CorrelationId::fromString(self::CORRELATION_ID),
                actorContext: $actor === null ? null : new ActorContext($actor, $actor, $actor),
            ),
            new Inline(),
        );
    }
}

final readonly class EvaluatorOperation implements Operation {}

final readonly class EvaluatorValue implements OperationValue {}

final class EvaluatorPolicy implements AuthorizationPolicy
{
    public ?AuthorizationRequest $request = null;

    public function __construct(
        private readonly AuthorizationDecision $decision,
        private readonly ?Throwable $failure = null,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        $this->request = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}

final class EvaluatorContainer implements ContainerInterface
{
    public int $hasCalls = 0;
    public int $getCalls = 0;

    public function __construct(
        private readonly object $service,
        private readonly bool $available = true,
        private readonly ?Throwable $failure = null,
    ) {}

    public function get(string $id): mixed
    {
        ++$this->getCalls;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->service;
    }

    public function has(string $id): bool
    {
        ++$this->hasCalls;

        return $this->available;
    }
}
