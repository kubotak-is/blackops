<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Scheduling\ScheduledOperationEnvelopeFactory;
use BlackOps\Internal\Scheduling\ScheduledRuntimeActor;
use BlackOps\Internal\Scheduling\ScheduleOccurrence;
use BlackOps\Scheduling\ScheduledActorProvider;
use BlackOps\Scheduling\ScheduledTenantProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class ScheduledOperationEnvelopeFactoryTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687701';

    public function testScheduledRootUsesOccurrenceIdentityAndFixedExecutionActor(): void
    {
        $actor = new ActorRef('scheduler-user', 'user');
        $factory = new ScheduledOperationEnvelopeFactory($this->contexts(), new class($actor) implements
            ScheduledActorProvider {
            public function __construct(
                private readonly ActorRef $actor,
            ) {}

            public function actor(ScheduleContext $context): ?ActorRef
            {
                return $this->actor;
            }
        });
        $occurrence = $this->occurrence();
        $envelope = $factory->create(
            $this->metadata(authorization: AuthorizedSchedulePolicy::class),
            new ScheduledTestOperation(),
            new ScheduledTestValue(),
            $occurrence,
            new Inline(),
        );

        self::assertSame(self::OPERATION_ID, $envelope->id()->toString());
        self::assertSame(self::OPERATION_ID, $envelope->context()->correlationId()->toString());
        self::assertSame($occurrence->evaluatedAt->format('c'), $envelope->receivedAt()->format('c'));
        self::assertSame('reports.daily', $envelope->context()->schedule()?->name());
        self::assertSame($actor, $envelope->context()->actorContext()?->origin());
        self::assertSame($actor, $envelope->context()->actorContext()?->authorization());
        self::assertEquals(ScheduledRuntimeActor::ref(), $envelope->context()->actorContext()?->execution());
    }

    public function testAuthorizedScheduleRequiresProviderWithoutAnonymousFallback(): void
    {
        $factory = new ScheduledOperationEnvelopeFactory($this->contexts());

        $this->expectExceptionMessage('requires an actor provider');
        $factory->create(
            $this->metadata(authorization: AuthorizedSchedulePolicy::class),
            new ScheduledTestOperation(),
            new ScheduledTestValue(),
            $this->occurrence(),
            new Inline(),
        );
    }

    public function testProviderMayExplicitlyReturnNullForAnonymousAuthorizationBoundary(): void
    {
        $factory = new ScheduledOperationEnvelopeFactory($this->contexts(), new class implements
            ScheduledActorProvider {
            public function actor(ScheduleContext $context): ?ActorRef
            {
                return null;
            }
        });
        $envelope = $factory->create(
            $this->metadata(authorization: AuthorizedSchedulePolicy::class),
            new ScheduledTestOperation(),
            new ScheduledTestValue(),
            $this->occurrence(),
            new Inline(),
        );

        self::assertNull($envelope->context()->actorContext()?->authorization());
        self::assertEquals(ScheduledRuntimeActor::ref(), $envelope->context()->actorContext()?->execution());
    }

    public function testTenantProviderIsIndependentAndPropagatesTenant(): void
    {
        $tenant = new TenantRef('account', 'tenant-secret-id');
        $factory = new ScheduledOperationEnvelopeFactory($this->contexts(), null, new class($tenant) implements
            ScheduledTenantProvider {
            public function __construct(
                private TenantRef $tenant,
            ) {}

            public function tenant(ScheduleContext $context): ?TenantRef
            {
                return $this->tenant;
            }
        });
        $envelope = $factory->create(
            $this->metadata(),
            new ScheduledTestOperation(),
            new ScheduledTestValue(),
            $this->occurrence(),
            new Inline(),
        );
        self::assertSame($tenant, $envelope->context()->tenant());
    }

    public function testTenantProviderFailureDoesNotFallbackToTenantlessContext(): void
    {
        $factory = new ScheduledOperationEnvelopeFactory($this->contexts(), null, new class implements
            ScheduledTenantProvider {
            public function tenant(ScheduleContext $context): ?TenantRef
            {
                throw new \RuntimeException('tenant provider failed');
            }
        });
        $this->expectException(\LogicException::class);
        $factory->create(
            $this->metadata(),
            new ScheduledTestOperation(),
            new ScheduledTestValue(),
            $this->occurrence(),
            new Inline(),
        );
    }

    public function testValueConstructionRejectsConstructorRequiredValue(): void
    {
        $factory = new ScheduledOperationEnvelopeFactory($this->contexts());

        $this->expectExceptionMessage('could not be constructed');
        $factory->value($this->metadata(value: ConstructorRequiredValue::class));
    }

    private function contexts(): ExecutionContextFactory
    {
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-23T00:00:00.123456Z');
            }
        };
        $generator = new class implements Uuidv7Generator {
            public function generate(DateTimeImmutable $time): string
            {
                return '019f32ab-2be0-7b38-a0a7-1ab2f9687702';
            }
        };

        return new ExecutionContextFactory(new IdentifierFactory($generator, $clock), $clock);
    }

    private function metadata(
        ?string $authorization = null,
        string $value = ScheduledTestValue::class,
    ): OperationMetadata {
        return new OperationMetadata(
            'reports.daily.run',
            ScheduledTestOperation::class,
            $value,
            ScheduledTestOperation::class,
            ScheduledTestOutcome::class,
            Inline::class,
            authorizationPolicy: $authorization,
            schedule: new OperationScheduleMetadata('reports.daily', '* * * * *', 'Asia/Tokyo'),
        );
    }

    private function occurrence(): ScheduleOccurrence
    {
        return new ScheduleOccurrence(
            'reports.daily',
            new DateTimeImmutable('2026-07-22T09:00:00.654321Z'),
            new DateTimeImmutable('2026-07-23T00:00:00.123456Z'),
            'claimed',
            null,
            OperationId::fromString(self::OPERATION_ID),
        );
    }
}

final class ScheduledTestOperation implements Operation {}

final class ScheduledTestValue implements OperationValue {}

final class ConstructorRequiredValue implements OperationValue
{
    public function __construct(string $required) {}
}

final class ScheduledTestOutcome {}

final class AuthorizedSchedulePolicy {}
