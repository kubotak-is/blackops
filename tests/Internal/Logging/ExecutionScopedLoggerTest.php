<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Logging;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\AttemptContext;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class ExecutionScopedLoggerTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testAddsExecutionScopeContextAndFiltersUserContext(): void
    {
        $inner = new RecordingPsrLogger();
        $scope = new ExecutionScopeProvider();
        $logger = new ExecutionScopedLogger($inner, $scope);

        $scope->run(
            self::envelope(),
            static function () use ($logger): void {
                $logger->info('hello', [
                    'orderId' => 'order-123',
                    'password' => 'secret',
                    'operation' => ['id' => 'user-supplied'],
                ]);
            },
            'dispatch.test',
        );

        self::assertCount(1, $inner->records);
        $context = $inner->records[0]['context'];
        self::assertSame(self::ID, $context['operation']['id']);
        self::assertSame('dispatch.test', $context['operation']['type']);
        self::assertSame(self::ID, $context['operation']['attemptId']);
        self::assertSame(Inline::class, $context['operation']['strategy']);
        self::assertSame('order-123', $context['context']['orderId']);
        self::assertArrayNotHasKey('password', $context['context']);
        self::assertSame(['id' => 'user-supplied'], $context['context']['operation']);
    }

    public function testOutsideScopeDoesNotAddOperationContext(): void
    {
        $inner = new RecordingPsrLogger();
        $logger = new ExecutionScopedLogger($inner, new ExecutionScopeProvider());

        $logger->warning('outside', ['token' => 'secret', 'safe' => 'ok']);

        self::assertCount(1, $inner->records);
        self::assertArrayNotHasKey('operation', $inner->records[0]['context']);
        self::assertSame(['safe' => 'ok'], $inner->records[0]['context']['context']);
    }

    public function testFrameworkErrorUsesSafeClassificationAndMaskedActorCorrelation(): void
    {
        $inner = new RecordingPsrLogger();
        $scope = new ExecutionScopeProvider();
        $logger = new ExecutionScopedLogger($inner, $scope);
        $actor = new ActorRef('user-credential-id', 'user');

        $scope->run(
            self::envelope(new ActorContext($actor, $actor, $actor)),
            static function () use ($logger): void {
                $logger->frameworkError(\RuntimeException::class, false, \LogicException::class);
            },
            'dispatch.test',
        );

        $context = $inner->records[0]['context'];
        self::assertSame('framework', $context['kind']);
        self::assertSame('internal_error', $context['context']['failure']['classification']);
        self::assertSame(\RuntimeException::class, $context['context']['failure']['type']);
        self::assertFalse($context['context']['failure']['journalRecorded']);
        self::assertSame('failure_recording_failed', $context['context']['failure']['secondary']['classification']);
        self::assertSame(\LogicException::class, $context['context']['failure']['secondary']['type']);
        self::assertSame(self::ID, $context['operation']['id']);
        self::assertSame('[masked]', $context['operation']['actors']['origin']['id']);
        self::assertSame('user', $context['operation']['actors']['origin']['type']);
        self::assertStringNotContainsString('user-credential-id', serialize($context));
    }

    public function testBackendFailureDoesNotEscapeApplicationOrFrameworkLogging(): void
    {
        $backend = new class extends AbstractLogger {
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('logger backend credential detail');
            }
        };
        $logger = new ExecutionScopedLogger($backend, new ExecutionScopeProvider());

        $logger->info('application');
        $logger->frameworkError(\RuntimeException::class, false);
        $logger->frameworkSystemError(\RuntimeException::class);

        self::addToAssertionCount(1);
    }

    public function testSystemFailureNeverAddsOperationFieldEvenWhenScopeIsActive(): void
    {
        $inner = new RecordingPsrLogger();
        $scope = new ExecutionScopeProvider();
        $logger = new ExecutionScopedLogger($inner, $scope);

        $scope->run(
            self::envelope(),
            static fn() => $logger->frameworkSystemError(\RuntimeException::class),
            'dispatch.test',
        );

        self::assertCount(1, $inner->records);
        self::assertArrayNotHasKey('operation', $inner->records[0]['context']);
        self::assertSame('framework', $inner->records[0]['context']['kind']);
        self::assertSame(
            ['classification' => 'internal_error', 'type' => \RuntimeException::class],
            $inner->records[0]['context']['context']['failure'],
        );
    }

    private static function envelope(?ActorContext $actorContext = null): OperationEnvelope
    {
        return new OperationEnvelope(
            new LoggingOperation(),
            new LoggingValue('hello'),
            new ExecutionContext(
                OperationId::fromString(self::ID),
                new DateTimeImmutable('2026-07-07T00:00:00Z'),
                CorrelationId::fromString(self::ID),
                attempt: new AttemptContext(
                    AttemptId::fromString(self::ID),
                    1,
                    new DateTimeImmutable('2026-07-07T00:00:01Z'),
                ),
                actorContext: $actorContext,
            ),
            new Inline(),
        );
    }
}

final class RecordingPsrLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|Stringable, context: array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}

final readonly class LoggingOperation implements Operation {}

final readonly class LoggingValue implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}
