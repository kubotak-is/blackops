<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Scheduling;

use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Scheduling\CronExpression;
use BlackOps\Internal\Scheduling\PostgreSqlScheduleStore;
use BlackOps\Internal\Scheduling\ScheduleEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class ScheduleEvaluatorTest extends TestCase
{
    private Connection $connection;
    private MutableScheduleClock $clock;
    private IdentifierFactory $identifiers;

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
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS schedule_eval_test CASCADE');
        new PostgreSqlScheduleStore($this->connection, 'schedule_eval_test')->migrate();
        $this->clock = new MutableScheduleClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->identifiers = new IdentifierFactory(new FixedScheduleGenerator(), $this->clock);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS schedule_eval_test CASCADE');
    }

    public function testFirstAndNoMatchAdvanceCursor(): void
    {
        $first = $this->evaluator()->evaluate($this->metadata('* * * * *'));
        self::assertTrue($first->claimed);
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:01:00Z');
        $none = $this->evaluator()->evaluate($this->metadata('1 1 * * *'));
        self::assertFalse($none->claimed);
        self::assertSame(
            '2026-01-01 00:01:00+00',
            $this->connection->fetchOne(
                'SELECT cursor_at::text FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
            ),
        );
    }

    public function testEvaluationPreservesInstantPrecisionApartFromMinuteSlots(): void
    {
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:00:42.123456Z');
        $result = $this->evaluator()->evaluate($this->metadata('* * * * *'));
        self::assertCount(1, $result->occurrences);
        self::assertSame(
            '2026-01-01 00:00:42.123456+00:00',
            $result->occurrences[0]->evaluatedAt->format('Y-m-d H:i:s.uP'),
        );
        $row = $this->connection->fetchAssociative('SELECT o.scheduled_at::text, s.cursor_at::text, o.evaluated_at::text, o.created_at::text, o.updated_at::text
                FROM "schedule_eval_test"."schedule_occurrences" o
                JOIN "schedule_eval_test"."schedule_states" s USING (schedule_name)
                WHERE o.schedule_name = \'schedule.test\'');
        self::assertSame('2026-01-01 00:00:00+00', $row['scheduled_at']);
        self::assertSame('2026-01-01 00:00:00+00', $row['cursor_at']);
        self::assertSame('2026-01-01 00:00:42.123456+00', $row['evaluated_at']);
        self::assertSame('2026-01-01 00:00:42.123456+00', $row['created_at']);
        self::assertSame('2026-01-01 00:00:42.123456+00', $row['updated_at']);
    }

    public function testMisfireMarksOlderMatchesAndClaimsLatest(): void
    {
        $metadata = $this->metadata('*/5 * * * *');
        $this->evaluator()->evaluate($metadata);
        $this->connection->executeStatement(
            "UPDATE \"schedule_eval_test\".\"schedule_occurrences\" SET state='completed' WHERE state='claimed'",
        );
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:16:00Z');
        $result = $this->evaluator()->evaluate($metadata);
        self::assertSame(
            ['skipped_misfire', 'skipped_misfire', 'claimed'],
            array_map(static fn($occurrence): string => $occurrence->state, $result->occurrences),
        );
        self::assertNull($result->occurrences[0]->operationId);
        self::assertNull($result->occurrences[1]->operationId);
        self::assertNotNull($result->occurrences[2]->operationId);
    }

    public function testActiveOverlapSkipsLatestAndTerminalAllowsClaim(): void
    {
        $metadata = $this->metadata('*/5 * * * *');
        $this->evaluator()->evaluate($metadata);
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:16:00Z');
        $skips = $this->evaluator()->evaluate($metadata);
        self::assertSame(
            ['skipped_misfire', 'skipped_misfire', 'skipped_overlap'],
            array_map(static fn($occurrence): string => $occurrence->state, $skips->occurrences),
        );
        self::assertNull($skips->occurrences[2]->operationId);
        $this->connection->executeStatement(
            "UPDATE \"schedule_eval_test\".\"schedule_occurrences\" SET state='completed' WHERE state='claimed'",
        );
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:20:00Z');
        self::assertTrue($this->evaluator()->evaluate($metadata)->claimed);
    }

    public function testAcceptedOccurrenceBlocksAConcurrentClaim(): void
    {
        $metadata = $this->metadata('* * * * *');
        $this->evaluator()->evaluate($metadata);
        $this->connection->executeStatement(
            "UPDATE \"schedule_eval_test\".\"schedule_occurrences\" SET state='accepted' WHERE state='claimed'",
        );
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:01:00Z');
        $result = $this->evaluator()->evaluate($metadata);
        self::assertFalse($result->claimed);
        self::assertSame(
            ['skipped_overlap'],
            array_map(static fn($occurrence): string => $occurrence->state, $result->occurrences),
        );
        self::assertNull($result->occurrences[0]->operationId);
    }

    public function testClockRollbackDoesNotRegressAndRecoveryIsOrdered(): void
    {
        $metadata = $this->metadata('* * * * *');
        $this->evaluator()->evaluate($metadata);
        $before = $this->connection->fetchOne(
            'SELECT cursor_at FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
        );
        $this->clock->at = new DateTimeImmutable('2025-12-31T23:00:00Z');
        self::assertFalse($this->evaluator()->evaluate($metadata)->claimed);
        self::assertSame(
            $before,
            $this->connection->fetchOne(
                'SELECT cursor_at FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
            ),
        );
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO "schedule_eval_test"."schedule_occurrences"
                (schedule_name, scheduled_at, evaluated_at, state, category, operation_id, accepted_at, created_at, updated_at)
            VALUES
                ('schedule.test', '2026-01-01 00:02:00+00', '2026-01-01 00:02:00+00', 'claimed', NULL, '019f0000-0000-7000-8000-000000000002', NULL, '2026-01-01 00:02:00+00', '2026-01-01 00:02:00+00'),
                ('schedule.test', '2026-01-01 00:01:00+00', '2026-01-01 00:01:00+00', 'claimed', NULL, '019f0000-0000-7000-8000-000000000003', NULL, '2026-01-01 00:01:00+00', '2026-01-01 00:01:00+00')
            SQL);
        $recovery = new PostgreSqlScheduleStore($this->connection, 'schedule_eval_test')->recoverClaimed(
            'schedule.test',
        );
        self::assertSame(
            ['2026-01-01 00:00', '2026-01-01 00:01', '2026-01-01 00:02'],
            array_map(static fn($occurrence): string => $occurrence->scheduledAt->format('Y-m-d H:i'), $recovery),
        );
    }

    public function testScheduleSessionLockIsReleasedAfterFailure(): void
    {
        $store = new PostgreSqlScheduleStore($this->connection, 'schedule_eval_test');
        try {
            $store->withScheduleLock('schedule.test', static function (): void {
                throw new \RuntimeException('fixture failure');
            });
            self::fail('Expected lock callback failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('fixture failure', $exception->getMessage());
        }

        $probe = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        try {
            $acquired = (bool) $probe->fetchOne(
                "SELECT pg_try_advisory_lock(hashtextextended('blackops.scheduled-operation:schedule_eval_test:schedule.test', 0))",
            );
            self::assertTrue($acquired, 'A second session must acquire the lock after callback failure.');
            $probe->executeQuery(
                "SELECT pg_advisory_unlock(hashtextextended('blackops.scheduled-operation:schedule_eval_test:schedule.test', 0))",
            );
        } finally {
            $probe->close();
        }
    }

    public function testScheduleLocksAreNamespacedBySchema(): void
    {
        $store = new PostgreSqlScheduleStore($this->connection, 'schedule_eval_test');
        $probe = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        try {
            $sameNameDifferentSchema = false;
            $store->withScheduleLock('schedule.test', function () use ($probe, &$sameNameDifferentSchema): void {
                $sameNameDifferentSchema = (bool) $probe->fetchOne(
                    "SELECT pg_try_advisory_lock(hashtextextended('blackops.scheduled-operation:other_schema:schedule.test', 0))",
                );
                if ($sameNameDifferentSchema) {
                    $probe->executeQuery(
                        "SELECT pg_advisory_unlock(hashtextextended('blackops.scheduled-operation:other_schema:schedule.test', 0))",
                    );
                }
            });
            self::assertTrue($sameNameDifferentSchema);
        } finally {
            $probe->close();
        }
    }

    #[DataProvider('terminalStates')]
    public function testTerminalAndSkippedStatesDoNotBlock(string $state): void
    {
        $metadata = $this->metadata('* * * * *');
        $this->evaluator()->evaluate($metadata);
        $this->connection->executeStatement(
            $state === 'skipped_misfire' || $state === 'skipped_overlap'
                ? "UPDATE \"schedule_eval_test\".\"schedule_occurrences\" SET state=:state, operation_id=NULL WHERE state='claimed'"
                : "UPDATE \"schedule_eval_test\".\"schedule_occurrences\" SET state=:state WHERE state='claimed'",
            ['state' => $state],
        );
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:01:00Z');
        self::assertTrue($this->evaluator()->evaluate($metadata)->claimed);
    }

    /** @return iterable<string,array{string}> */
    public static function terminalStates(): iterable
    {
        foreach (['completed', 'rejected', 'failed', 'dead_lettered', 'skipped_misfire', 'skipped_overlap'] as $state)
            yield $state => [$state];
    }

    public function testOperationTypeReassignmentDoesNotAdvanceCursor(): void
    {
        $this->evaluator()->evaluate($this->metadata('* * * * *'));
        $before = $this->connection->fetchOne(
            'SELECT cursor_at FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
        );
        $other = new OperationMetadata(
            'other.type',
            'Definition',
            'Value',
            'Handler',
            'Outcome',
            'Inline',
            schedule: new OperationScheduleMetadata('schedule.test', '* * * * *', 'UTC'),
        );
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->evaluator()->evaluate($other);
        } finally {
            self::assertSame(
                $before,
                $this->connection->fetchOne(
                    'SELECT cursor_at FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
                ),
            );
        }
    }

    public function testCursorFailureRollsBackOccurrenceAndCursor(): void
    {
        $metadata = $this->metadata('* * * * *');
        $this->evaluator()->evaluate($metadata);
        $before = $this->connection->fetchOne(
            'SELECT cursor_at FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
        );
        $this->connection->executeStatement(
            'CREATE FUNCTION "schedule_eval_test".reject_cursor() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION \'cursor denied\'; END; $$',
        );
        $this->connection->executeStatement(
            'CREATE TRIGGER reject_cursor BEFORE UPDATE OF cursor_at ON "schedule_eval_test"."schedule_states" FOR EACH ROW EXECUTE FUNCTION "schedule_eval_test".reject_cursor()',
        );
        $this->clock->at = new DateTimeImmutable('2026-01-01T00:01:00Z');
        $this->expectException(\RuntimeException::class);
        try {
            $this->evaluator()->evaluate($metadata);
        } finally {
            self::assertSame(
                $before,
                $this->connection->fetchOne(
                    'SELECT cursor_at FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
                ),
            );
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM "schedule_eval_test"."schedule_occurrences"'),
            );
        }
    }

    public function testTwoConnectionsConvergeOnSingleFirstOccurrence(): void
    {
        $barrier = tempnam(sys_get_temp_dir(), 'schedule-barrier-');
        self::assertNotFalse($barrier);
        unlink($barrier);
        $pids = [];
        $this->connection->close();
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                while (!is_file($barrier))
                    usleep(1000);
                $connection = DriverManager::getConnection([
                    'driver' => 'pdo_pgsql',
                    'host' => getenv('POSTGRES_HOST') ?: 'postgres',
                    'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
                    'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
                    'user' => getenv('POSTGRES_USER') ?: 'blackops',
                    'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
                ]);
                $connection->executeStatement("SET lock_timeout = '2s'");
                $clock = new MutableScheduleClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
                $evaluator = new ScheduleEvaluator(
                    $connection,
                    $clock,
                    new IdentifierFactory(new FixedScheduleGenerator(), $clock),
                    'schedule_eval_test',
                );
                try {
                    $evaluator->evaluate(
                        new OperationMetadata(
                            'schedule.test',
                            'Definition',
                            'Value',
                            'Handler',
                            'Outcome',
                            'Inline',
                            schedule: new OperationScheduleMetadata('schedule.test', '* * * * *', 'UTC'),
                        ),
                    );
                } catch (\Throwable) {
                    exit(1);
                }
                exit(0);
            }
            $pids[] = $pid;
        }
        try {
            touch($barrier);
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }
        } finally {
            if (is_file($barrier))
                unlink($barrier);
        }
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM "schedule_eval_test"."schedule_occurrences"'),
        );
        self::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT count(DISTINCT operation_id) FROM "schedule_eval_test"."schedule_occurrences" WHERE operation_id IS NOT NULL',
            ),
        );
        self::assertSame(
            '2026-01-01 00:00:00+00',
            $this->connection->fetchOne(
                'SELECT cursor_at::text FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.test\'',
            ),
        );
        self::assertCount(
            1,
            new PostgreSqlScheduleStore($this->connection, 'schedule_eval_test')->recoverClaimed('schedule.test'),
        );
    }

    private function evaluator(): ScheduleEvaluator
    {
        return new ScheduleEvaluator($this->connection, $this->clock, $this->identifiers, 'schedule_eval_test');
    }

    private function metadata(string $cron): OperationMetadata
    {
        return new OperationMetadata(
            'schedule.test',
            'Definition',
            'Value',
            'Handler',
            'Outcome',
            'Inline',
            schedule: new OperationScheduleMetadata('schedule.test', $cron, 'UTC'),
        );
    }

    public function testCronDowsundaySevenIsNormalizedForEvaluatorModel(): void
    {
        $cron = CronExpression::parse('0 0 * * 7');
        self::assertSame([0], $cron->dayOfWeek->values);
    }

    #[DataProvider('matchingCronCases')]
    public function testEvaluatorMatchesCronFieldForms(string $cron, DateTimeImmutable $at): void
    {
        $this->clock->at = $at;
        self::assertTrue($this->evaluator()->evaluate($this->metadata($cron))->claimed);
    }

    /** @return iterable<string,array{string,DateTimeImmutable}> */
    public static function matchingCronCases(): iterable
    {
        yield 'wildcard' => ['* * * * *', new DateTimeImmutable('2026-01-01T00:00:00Z')];
        yield 'list' => ['0,2 * * * *', new DateTimeImmutable('2026-01-01T00:02:00Z')];
        yield 'range' => ['0-5 * * * *', new DateTimeImmutable('2026-01-01T00:05:00Z')];
        yield 'step' => ['*/15 * * * *', new DateTimeImmutable('2026-01-01T00:15:00Z')];
        yield 'sunday-seven' => ['0 0 * * 7', new DateTimeImmutable('2026-01-04T00:00:00Z')];
    }

    public function testFallBackLocalMinuteHasTwoUtcInstantsAndEarlierIsCanonical(): void
    {
        $zone = new DateTimeZone('America/New_York');
        $first = new DateTimeImmutable('2026-11-01T05:30:00Z');
        $second = new DateTimeImmutable('2026-11-01T06:30:00Z');
        self::assertSame('2026-11-01 01:30', $first->setTimezone($zone)->format('Y-m-d H:i'));
        self::assertSame('2026-11-01 01:30', $second->setTimezone($zone)->format('Y-m-d H:i'));
        self::assertLessThan($second->getTimestamp(), $first->getTimestamp());
    }

    public function testNewYorkFallBackSplitPollingDoesNotClaimSecondUtcInstant(): void
    {
        $metadata = new OperationMetadata(
            'schedule.ny',
            'Definition',
            'Value',
            'Handler',
            'Outcome',
            'Inline',
            schedule: new OperationScheduleMetadata('schedule.ny', '30 1 * * *', 'America/New_York'),
        );
        $this->clock->at = new DateTimeImmutable('2026-11-01T05:30:00Z');
        self::assertTrue($this->evaluator()->evaluate($metadata)->claimed);
        $this->clock->at = new DateTimeImmutable('2026-11-01T06:30:00Z');
        $second = $this->evaluator()->evaluate($metadata);
        self::assertFalse($second->claimed);
        self::assertSame([], $second->occurrences);
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM "schedule_eval_test"."schedule_occurrences"'),
        );
    }

    public function testSpringForwardGapMinuteIsNotClaimed(): void
    {
        $metadata = new OperationMetadata(
            'schedule.spring',
            'Definition',
            'Value',
            'Handler',
            'Outcome',
            'Inline',
            schedule: new OperationScheduleMetadata('schedule.spring', '30 2 * * *', 'America/New_York'),
        );
        $this->clock->at = new DateTimeImmutable('2026-03-08T06:00:00Z');
        self::assertFalse($this->evaluator()->evaluate($metadata)->claimed);
        $this->clock->at = new DateTimeImmutable('2026-03-08T08:00:00Z');
        $result = $this->evaluator()->evaluate($metadata);
        self::assertFalse($result->claimed);
        self::assertSame([], $result->occurrences);
        self::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT count(*) FROM "schedule_eval_test"."schedule_occurrences"'),
        );
        self::assertSame(
            '2026-03-08 08:00:00+00',
            $this->connection->fetchOne(
                'SELECT cursor_at::text FROM "schedule_eval_test"."schedule_states" WHERE schedule_name = \'schedule.spring\'',
            ),
        );
    }
}

final class MutableScheduleClock implements ClockInterface
{
    public function __construct(
        public DateTimeImmutable $at,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->at;
    }
}

final class FixedScheduleGenerator implements Uuidv7Generator
{
    private int $counter = 1;

    public function generate(DateTimeImmutable $time): string
    {
        return sprintf('019f0000-0000-7000-8000-%012d', $this->counter++);
    }
}
