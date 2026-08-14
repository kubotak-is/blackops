<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Core\Codec\EncodedOperationMessage;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Core\ScheduleContext;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Scheduling\PostgreSqlScheduledOccurrenceLifecycle;
use BlackOps\Internal\Scheduling\ScheduledDeferredAcceptor;
use BlackOps\Internal\Scheduling\ScheduledInlineDispatcher;
use BlackOps\Internal\Scheduling\ScheduledOperationEnvelopeFactory;
use BlackOps\Internal\Scheduling\ScheduledOperationRuntime;
use BlackOps\Internal\Scheduling\ScheduleOccurrence;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Scheduling\ScheduledActorProvider;
use BlackOps\Tests\Internal\Telemetry\RecordingTracerProvider;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class ScheduledOperationRuntimeTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687701';
    private const string SCHEMA = 'scheduled_runtime_test';

    public function testInlineInvocationUsesFixedScheduledEnvelope(): void
    {
        $dispatcher = $this->createMock(ScheduledInlineDispatcher::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatchScheduled')
            ->with(self::callback(function ($envelope): bool {
                self::assertSame(self::OPERATION_ID, $envelope->id()->toString());
                self::assertSame(self::OPERATION_ID, $envelope->context()->correlationId()->toString());
                self::assertSame('reports.daily', $envelope->context()->schedule()?->name());
                self::assertSame('2026-07-22T09:00:00.000000Z', $envelope->receivedAt()->format('Y-m-d\\TH:i:s.u\\Z'));
                return true;
            }), self::isInstanceOf(OperationMetadata::class))
            ->willReturn(OperationResult::completed(new RuntimeTestOutcome()));

        $result = $this->runtime($dispatcher, null)->invoke(
            $this->metadata(Inline::class),
            new RuntimeTestOperation(),
            $this->occurrence(),
        );

        self::assertTrue($result->isCompleted());
    }

    public function testDeferredInvocationEncodesSameScheduledContextAndOperationId(): void
    {
        $codec = $this->createMock(OperationCodec::class);
        $codec
            ->expects(self::once())
            ->method('encode')
            ->with(
                self::isInstanceOf(OperationMetadata::class),
                self::isInstanceOf(RuntimeTestValue::class),
                self::callback(
                    static fn($context): bool => (
                        $context->operationId()->toString() === self::OPERATION_ID
                        && $context->schedule()?->name() === 'reports.daily'
                    ),
                ),
            )
            ->willReturn(new EncodedOperationMessage('runtime.test', 1, '{"message":"ok"}', '{"schedule":{}}'));
        $deferred = $this->createMock(ScheduledDeferredAcceptor::class);
        $deferred
            ->expects(self::once())
            ->method('accept')
            ->with(
                self::callback(static fn($message): bool => $message->operationId()->toString() === self::OPERATION_ID),
                self::callback(
                    static fn($envelope): bool => $envelope->context()->schedule()?->name() === 'reports.daily',
                ),
                self::isInstanceOf(OperationMetadata::class),
            )
            ->willReturn(
                new DeferredAcknowledgement(
                    OperationId::fromString(self::OPERATION_ID),
                    new DateTimeImmutable('2026-07-22T09:00:00Z'),
                ),
            );

        $result = $this->runtime(null, $deferred, $codec)->invoke(
            $this->metadata(Deferred::class),
            new RuntimeTestOperation(),
            $this->occurrence(),
        );

        self::assertSame(self::OPERATION_ID, $result->operationId()->toString());
    }

    public function testDeferredProducerNestsUnderScheduleEvaluationAndPersistsItsContext(): void
    {
        $provider = new RecordingTracerProvider();
        $tracer = new TelemetryTracer($provider);
        $codec = $this->createStub(OperationCodec::class);
        $codec
            ->method('encode')
            ->willReturn(new EncodedOperationMessage('runtime.test', 1, '{"message":"ok"}', '{"schedule":{}}'));
        $deferred = $this->createMock(ScheduledDeferredAcceptor::class);
        $deferred
            ->expects(self::once())
            ->method('accept')
            ->willReturnCallback(function () use ($tracer, $provider): DeferredAcknowledgement {
                self::assertSame(
                    $provider->spans[1]->getContext()->getSpanId(),
                    explode('-', $tracer->currentContext()?->traceparent() ?? '')[2] ?? null,
                );

                return new DeferredAcknowledgement(
                    OperationId::fromString(self::OPERATION_ID),
                    new DateTimeImmutable('2026-07-22T09:00:00Z'),
                );
            });
        $runtime = $this->runtime(null, $deferred, $codec, telemetry: $tracer);
        $evaluate = $tracer->start('blackops.operation.schedule.evaluate', attributes: [
            'blackops.runtime.kind' => 'scheduler',
        ]);

        try {
            $runtime->invoke($this->metadata(Deferred::class), new RuntimeTestOperation(), $this->occurrence());
        } finally {
            $evaluate->result('completed');
            $evaluate->end();
        }

        self::assertCount(2, $provider->spans);
        self::assertSame('blackops.operation.schedule.evaluate', $provider->spans[0]->name);
        self::assertSame('blackops.operation.accept', $provider->spans[1]->name);
        self::assertSame(TelemetryTracer::KIND_PRODUCER, $provider->spans[1]->kind);
        self::assertSame($provider->spans[0]->getContext()->getSpanId(), $provider->spans[1]->parent?->getSpanId());
        self::assertSame('deferred', $provider->spans[1]->attributes['blackops.operation.strategy']);
        self::assertSame('completed', $provider->spans[1]->attributes['blackops.result']);
        self::assertTrue($provider->spans[1]->ended);
    }

    public function testValueConstructionFailureTransitionsOccurrenceWithClockInstant(): void
    {
        $connection = $this->connection();
        $this->migrateOccurrence($connection);
        $clock = $this->clock();
        $this->insertOccurrence($connection);
        $runtime = new ScheduledOperationRuntime(
            new ScheduledOperationEnvelopeFactory($this->contexts()),
            $this->createStub(ScheduledInlineDispatcher::class),
            $this->createStub(ScheduledDeferredAcceptor::class),
            $this->createStub(OperationCodec::class),
            $clock,
            new PostgreSqlScheduledOccurrenceLifecycle($connection, self::SCHEMA),
        );

        try {
            $runtime->invokeInline(
                $this->metadata(Inline::class, ConstructorRequiredRuntimeValue::class),
                new RuntimeTestOperation(),
                $this->occurrence(),
            );
            self::fail('Expected value construction failure.');
        } catch (\LogicException $exception) {
            self::assertSame('Scheduled operation value could not be constructed.', $exception->getMessage());
            self::assertSame('failed', $connection->fetchOne('SELECT state FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
            self::assertSame('2026-07-22 09:00:01+00', $connection->fetchOne('SELECT updated_at::text FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
        }
    }

    public function testActorProviderFailureTransitionsOccurrenceWithSafeCategory(): void
    {
        $connection = $this->connection();
        $this->migrateOccurrence($connection);
        $this->insertOccurrence($connection);
        $runtime = new ScheduledOperationRuntime(
            new ScheduledOperationEnvelopeFactory($this->contexts(), new class implements ScheduledActorProvider {
                public function actor(ScheduleContext $context): ?\BlackOps\Core\ActorRef
                {
                    throw new RuntimeException('provider credentials must not leak');
                }
            }),
            $this->createStub(ScheduledInlineDispatcher::class),
            $this->createStub(ScheduledDeferredAcceptor::class),
            $this->createStub(OperationCodec::class),
            $this->clock(),
            new PostgreSqlScheduledOccurrenceLifecycle($connection, self::SCHEMA),
        );

        try {
            $runtime->invokeInline($this->metadata(Inline::class), new RuntimeTestOperation(), $this->occurrence());
            self::fail('Expected actor resolution failure.');
        } catch (\LogicException $exception) {
            self::assertStringNotContainsString('provider credentials', $exception->getMessage());
            self::assertSame('failed', $connection->fetchOne('SELECT state FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
            self::assertSame('scheduled_actor_resolution_failed', $connection->fetchOne('SELECT category FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE operation_id = :operation', ['operation' => self::OPERATION_ID]));
        }
    }

    private function runtime(
        ?ScheduledInlineDispatcher $inline,
        ?ScheduledDeferredAcceptor $deferred,
        ?OperationCodec $codec = null,
        ?ScheduledActorProvider $actors = null,
        ?TelemetryTracer $telemetry = null,
    ): ScheduledOperationRuntime {
        return new ScheduledOperationRuntime(
            new ScheduledOperationEnvelopeFactory($this->contexts(), $actors),
            $inline ?? $this->createStub(ScheduledInlineDispatcher::class),
            $deferred ?? $this->createStub(ScheduledDeferredAcceptor::class),
            $codec ?? $this->createStub(OperationCodec::class),
            $this->clock(),
            null,
            $telemetry,
        );
    }

    private function metadata(string $strategy, string $value = RuntimeTestValue::class): OperationMetadata
    {
        return new OperationMetadata(
            'runtime.test',
            RuntimeTestOperation::class,
            $value,
            RuntimeTestOperation::class,
            RuntimeTestOutcome::class,
            $strategy,
            schedule: new OperationScheduleMetadata('reports.daily', '* * * * *', 'UTC'),
        );
    }

    private function occurrence(): ScheduleOccurrence
    {
        return new ScheduleOccurrence(
            'reports.daily',
            new DateTimeImmutable('2026-07-22T09:00:00Z'),
            new DateTimeImmutable('2026-07-22T09:00:00Z'),
            'claimed',
            null,
            OperationId::fromString(self::OPERATION_ID),
        );
    }

    private function contexts(): ExecutionContextFactory
    {
        $clock = $this->clock();
        $generator = new class implements Uuidv7Generator {
            public function generate(DateTimeImmutable $time): string
            {
                return '019f32ab-2be0-7b38-a0a7-1ab2f9687701';
            }
        };
        return new ExecutionContextFactory(new IdentifierFactory($generator, $clock), $clock);
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-22T09:00:01Z');
            }
        };
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
    }

    private function migrateOccurrence(Connection $connection): void
    {
        $connection->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        new \BlackOps\Internal\Scheduling\PostgreSqlScheduleStore($connection, self::SCHEMA)->migrate();
        $connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_states" (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (\'reports.daily\', \'runtime.test\', \'2026-07-22 09:00:00+00\', \'2026-07-22 09:00:00+00\', \'2026-07-22 09:00:00+00\')',
        );
    }

    private function insertOccurrence(Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_occurrences" (schedule_name, scheduled_at, evaluated_at, state, operation_id, created_at, updated_at) VALUES (\'reports.daily\', \'2026-07-22 09:00:00+00\', \'2026-07-22 09:00:00+00\', \'claimed\', :operation, \'2026-07-22 09:00:00+00\', \'2026-07-22 09:00:00+00\')',
            ['operation' => self::OPERATION_ID],
        );
    }
}

final class RuntimeTestOperation implements Operation {}

final class RuntimeTestValue implements OperationValue {}

final class ConstructorRequiredRuntimeValue implements OperationValue
{
    public function __construct(string $required) {}
}

final class RuntimeTestOutcome implements Outcome {}
