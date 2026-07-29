<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Scheduling\PostgreSqlScheduledOccurrenceLifecycle;
use BlackOps\Internal\Scheduling\PostgreSqlScheduleStore;
use BlackOps\Internal\Scheduling\ScheduledDeferredAcceptor;
use BlackOps\Internal\Scheduling\ScheduledInlineDispatcher;
use BlackOps\Internal\Scheduling\ScheduledOperationDefinitionResolver;
use BlackOps\Internal\Scheduling\ScheduledOperationEnvelopeFactory;
use BlackOps\Internal\Scheduling\ScheduledOperationRunner;
use BlackOps\Internal\Scheduling\ScheduledOperationRuntime;
use BlackOps\Internal\Scheduling\ScheduleEvaluator;
use BlackOps\Internal\Scheduling\ScheduleOccurrence;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class ScheduledOperationRunnerTest extends TestCase
{
    private const string SCHEMA = 'scheduled_runner_test';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        new PostgreSqlScheduleStore($this->connection, self::SCHEMA)->migrate();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "' . self::SCHEMA . '" CASCADE');
        $this->connection->close();
    }

    public function testRunsSchedulesInNameOrderRecoversBeforeEvaluationAndTerminalizesClaimFailure(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:05:00Z');
        $this->seedState('runner.a', 'runner.a', '2026-01-01 00:04:00+00');
        $this->seedState('runner.b', 'runner.b', '2026-01-01 00:04:00+00');
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_occurrences" (schedule_name, scheduled_at, evaluated_at, state, operation_id, created_at, updated_at) VALUES (\'runner.a\', \'2026-01-01 00:04:00+00\', \'2026-01-01 00:04:00+00\', \'claimed\', :id, \'2026-01-01 00:04:00+00\', \'2026-01-01 00:04:00+00\')',
            ['id' => '019f0000-0000-7000-8000-000000000001'],
        );
        $clock = new RunnerClock($now);
        $dispatcher = new RunnerDispatcher();
        $runner = $this->runner($clock, $dispatcher);

        $result = $runner->run();

        self::assertSame(2, $result->evaluated);
        self::assertSame(2, $result->accepted);
        self::assertSame(1, $result->failed);
        self::assertSame(['runner.a', 'runner.a', 'runner.b'], $dispatcher->schedules);
        self::assertSame(
            [
                '019f0000-0000-7000-8000-000000000001',
                '019f0000-0000-7000-8000-000000000010',
                '019f0000-0000-7000-8000-000000000011',
            ],
            $dispatcher->operationIds,
        );
        self::assertSame('019f0000-0000-7000-8000-000000000001', $this->connection->fetchOne(
            'SELECT operation_id::text FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE schedule_name = \'runner.a\' AND scheduled_at = \'2026-01-01 00:04:00+00\'',
        ));
        self::assertSame('completed', $this->connection->fetchOne(
            'SELECT state FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE schedule_name = \'runner.a\' AND scheduled_at = \'2026-01-01 00:04:00+00\'',
        ));
        self::assertSame('failed', $this->connection->fetchOne(
            'SELECT state FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE schedule_name = \'runner.b\' AND scheduled_at = \'2026-01-01 00:05:00+00\'',
        ));
        self::assertSame('scheduled_invocation_failed', $this->connection->fetchOne(
            'SELECT category FROM "'
            . self::SCHEMA
            . '"."schedule_occurrences" WHERE schedule_name = \'runner.b\' AND scheduled_at = \'2026-01-01 00:05:00+00\'',
        ));
    }

    public function testAggregatesMisfireSkips(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:05:00Z');
        $this->seedState('runner.a', 'runner.a', '2026-01-01 00:01:00+00');
        $this->seedState('runner.b', 'runner.b', '2026-01-01 00:05:00+00');
        $runner = $this->runner(new RunnerClock($now), new RunnerDispatcher(null));

        $result = $runner->run();

        self::assertSame(2, $result->evaluated);
        self::assertSame(1, $result->accepted);
        self::assertSame(3, $result->skippedMisfire);
        self::assertSame(0, $result->skippedOverlap);
        self::assertSame(0, $result->failed);
        self::assertSame(
            ['skipped_misfire', 'skipped_misfire', 'skipped_misfire', null],
            array_values(array_map(static fn(array $row): ?string => $row['category'] === null
                ? null
                : (string) $row['category'], $this->connection->fetchAllAssociative('SELECT category FROM "' . self::SCHEMA . '"."schedule_occurrences" WHERE schedule_name = \'runner.a\' ORDER BY scheduled_at'))),
        );
    }

    public function testAggregatesOverlapSkip(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:05:00Z');
        $this->seedState('runner.a', 'runner.a', '2026-01-01 00:04:00+00');
        $this->seedState('runner.b', 'runner.b', '2026-01-01 00:05:00+00');
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_occurrences" (schedule_name, scheduled_at, evaluated_at, state, operation_id, created_at, updated_at) VALUES (\'runner.a\', \'2026-01-01 00:04:00+00\', \'2026-01-01 00:04:00+00\', \'accepted\', :id, \'2026-01-01 00:04:00+00\', \'2026-01-01 00:04:00+00\')',
            ['id' => '019f0000-0000-7000-8000-000000000004'],
        );
        $runner = $this->runner(new RunnerClock($now), new RunnerDispatcher(null));

        $result = $runner->run();

        self::assertSame(2, $result->evaluated);
        self::assertSame(0, $result->accepted);
        self::assertSame(0, $result->skippedMisfire);
        self::assertSame(1, $result->skippedOverlap);
        self::assertSame(0, $result->failed);
    }

    private function runner(RunnerClock $clock, RunnerDispatcher $dispatcher): ScheduledOperationRunner
    {
        $metadata = [
            new OperationMetadata(
                'runner.b',
                RunnerOperationB::class,
                RunnerValue::class,
                RunnerOperationB::class,
                RunnerOutcome::class,
                \BlackOps\Core\Execution\Inline::class,
                schedule: new OperationScheduleMetadata('runner.b', '* * * * *', 'UTC'),
            ),
            new OperationMetadata(
                'runner.a',
                RunnerOperationA::class,
                RunnerValue::class,
                RunnerOperationA::class,
                RunnerOutcome::class,
                \BlackOps\Core\Execution\Inline::class,
                schedule: new OperationScheduleMetadata('runner.a', '* * * * *', 'UTC'),
            ),
        ];
        $operations = new OperationRegistry($metadata);
        $contexts = new ExecutionContextFactory(new IdentifierFactory(new RunnerGenerator(), $clock), $clock);
        $lifecycle = new PostgreSqlScheduledOccurrenceLifecycle($this->connection, self::SCHEMA);
        $runtime = new ScheduledOperationRuntime(
            new ScheduledOperationEnvelopeFactory($contexts),
            $dispatcher,
            new class implements ScheduledDeferredAcceptor {
                public function accept(
                    \BlackOps\Core\Execution\DeferredOperationMessage $message,
                    \BlackOps\Core\OperationEnvelope $envelope,
                    OperationMetadata $metadata,
                ): \BlackOps\Core\Execution\DeferredAcknowledgement|OperationResult {
                    return OperationResult::completed(new RunnerOutcome());
                }
            },
            $this->createStub(OperationCodec::class),
            $clock,
            $lifecycle,
        );
        $container = new RunnerContainer();
        $container->services[RunnerOperationA::class] = new RunnerOperationA();
        $container->services[RunnerOperationB::class] = new RunnerOperationB();

        return new ScheduledOperationRunner(
            $operations,
            new PostgreSqlScheduleStore($this->connection, self::SCHEMA),
            new ScheduleEvaluator(
                $this->connection,
                $clock,
                new IdentifierFactory(new RunnerGenerator(), $clock),
                self::SCHEMA,
            ),
            $runtime,
            new ScheduledOperationDefinitionResolver($container),
            $lifecycle,
            $clock,
        );
    }

    private function seedState(string $name, string $type, string $cursor): void
    {
        $this->connection->executeStatement(
            'INSERT INTO "'
            . self::SCHEMA
            . '"."schedule_states" (schedule_name, operation_type, cursor_at, created_at, updated_at) VALUES (:name, :type, :cursor, :cursor, :cursor)',
            ['name' => $name, 'type' => $type, 'cursor' => $cursor],
        );
    }
}

final class RunnerDispatcher implements ScheduledInlineDispatcher
{
    public function __construct(
        private readonly ?string $failureSchedule = 'runner.b',
    ) {}

    /** @var list<string> */
    public array $schedules = [];

    /** @var list<string> */
    public array $operationIds = [];

    public function dispatchScheduled(
        \BlackOps\Core\OperationEnvelope $receivedEnvelope,
        OperationMetadata $metadata,
    ): OperationResult {
        $this->schedules[] = $metadata->schedule?->name ?? '';
        $this->operationIds[] = $receivedEnvelope->id()->toString();
        if ($metadata->schedule?->name === $this->failureSchedule) {
            throw new \RuntimeException('runner fixture failure');
        }

        return OperationResult::completed(new RunnerOutcome());
    }
}

final class RunnerContainer implements ContainerInterface
{
    /** @var array<string, object> */
    public array $services = [];

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

final readonly class RunnerClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class RunnerGenerator implements Uuidv7Generator
{
    private int $counter = 10;

    public function generate(DateTimeImmutable $time): string
    {
        return sprintf('019f0000-0000-7000-8000-%012d', $this->counter++);
    }
}

final readonly class RunnerOperationA implements Operation
{
    public function handle(RunnerValue $value): RunnerOutcome
    {
        return new RunnerOutcome();
    }
}

final readonly class RunnerOperationB implements Operation
{
    public function handle(RunnerValue $value): RunnerOutcome
    {
        return new RunnerOutcome();
    }
}

final readonly class RunnerValue implements OperationValue {}

final readonly class RunnerOutcome implements Outcome {}
