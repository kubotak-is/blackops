<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Core\Retention\RetentionPlan;
use BlackOps\Core\Retention\RetentionPlanner;
use BlackOps\Core\Retention\RetentionPolicy;
use BlackOps\Core\Retention\RetentionTarget;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionPlanner;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlRetentionPlannerTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_007';
    private const PAYLOAD_ELIGIBLE = '019f32ab-2be0-7b38-a0a7-1ab2f9688b01';
    private const PAYLOAD_FRESH = '019f32ab-2be0-7b38-a0a7-1ab2f9688b02';
    private const PAYLOAD_HELD = '019f32ab-2be0-7b38-a0a7-1ab2f9688b03';
    private const PAYLOAD_ALREADY_PURGED = '019f32ab-2be0-7b38-a0a7-1ab2f9688b04';
    private const DEAD_LETTER_ELIGIBLE = '019f32ab-2be0-7b38-a0a7-1ab2f9688b05';
    private const DEAD_LETTER_HELD = '019f32ab-2be0-7b38-a0a7-1ab2f9688b06';
    private const OUTCOME_ELIGIBLE = '019f32ab-2be0-7b38-a0a7-1ab2f9688b07';
    private const OUTCOME_HELD = '019f32ab-2be0-7b38-a0a7-1ab2f9688b08';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlRetentionPlanner $planner;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->planner = new PostgreSqlRetentionPlanner($this->connection, self::SCHEMA);
        $this->sender->migrate();
    }

    public function testPlannerImplementsRetentionPlannerPort(): void
    {
        self::assertInstanceOf(RetentionPlanner::class, $this->planner);
    }

    public function testPlannerReturnsEligiblePayloadAndDeadLetterCandidates(): void
    {
        $this->seedRetentionRows();

        $plan = $this->planner->plan($this->policy(), new DateTimeImmutable('2026-07-10T00:00:00Z'));

        self::assertInstanceOf(RetentionPlan::class, $plan);
        self::assertSame(4, $plan->count());
        self::assertSame(
            [self::DEAD_LETTER_ELIGIBLE, self::PAYLOAD_ELIGIBLE],
            array_map(
                static fn($item): string => $item->operationId()->toString(),
                $plan->forTarget(RetentionTarget::TransportPayload),
            ),
        );
        self::assertSame(
            [self::DEAD_LETTER_ELIGIBLE],
            array_map(
                static fn($item): string => $item->operationId()->toString(),
                $plan->forTarget(RetentionTarget::DeadLetter),
            ),
        );
        self::assertSame(
            [self::OUTCOME_ELIGIBLE],
            array_map(
                static fn($item): string => $item->operationId()->toString(),
                $plan->forTarget(RetentionTarget::Outcome),
            ),
        );

        $payload = $plan->forTarget(RetentionTarget::TransportPayload)[1];
        $deadLetter = $plan->forTarget(RetentionTarget::DeadLetter)[0];
        $outcome = $plan->forTarget(RetentionTarget::Outcome)[0];

        self::assertSame('2026-07-08T00:00:00+00:00', $payload->basisAt()->format(DATE_ATOM));
        self::assertSame('2026-07-09T00:00:00+00:00', $payload->eligibleAt()->format(DATE_ATOM));
        self::assertSame('2026-07-07T00:00:00+00:00', $deadLetter->basisAt()->format(DATE_ATOM));
        self::assertSame('2026-07-09T00:00:00+00:00', $deadLetter->eligibleAt()->format(DATE_ATOM));
        self::assertSame('2026-06-20T00:00:00+00:00', $outcome->basisAt()->format(DATE_ATOM));
        self::assertSame('2026-07-04T00:00:00+00:00', $outcome->eligibleAt()->format(DATE_ATOM));
    }

    public function testPlannerHasNoSideEffects(): void
    {
        $this->seedRetentionRows();

        $this->planner->plan($this->policy(), new DateTimeImmutable('2026-07-10T00:00:00Z'));

        $row = $this->connection->fetchAssociative(
            'SELECT
                state,
                convert_from(encoded_payload, \'UTF8\') AS encoded_payload,
                payload_purged_at
            FROM ' . self::SCHEMA . '.operations
            WHERE operation_id = :operation_id',
            ['operation_id' => self::PAYLOAD_ELIGIBLE],
        );

        self::assertIsArray($row);
        self::assertSame('completed', $row['state']);
        self::assertSame('{"operationId":"' . self::PAYLOAD_ELIGIBLE . '"}', $row['encoded_payload']);
        self::assertNull($row['payload_purged_at']);
    }

    private function seedRetentionRows(): void
    {
        $this->terminalOperation(self::PAYLOAD_ELIGIBLE, 'completed', '2026-07-08 00:00:00+00:00');
        $this->terminalOperation(self::PAYLOAD_FRESH, 'completed', '2026-07-09 12:00:00+00:00');
        $this->terminalOperation(self::PAYLOAD_HELD, 'completed', '2026-07-08 00:00:00+00:00');
        $this->terminalOperation(self::PAYLOAD_ALREADY_PURGED, 'completed', '2026-07-08 00:00:00+00:00');
        $this->terminalOperation(self::DEAD_LETTER_ELIGIBLE, 'dead_lettered', '2026-07-07 00:00:00+00:00');
        $this->terminalOperation(self::DEAD_LETTER_HELD, 'dead_lettered', '2026-07-07 00:00:00+00:00');
        $this->terminalOperation(self::OUTCOME_ELIGIBLE, 'completed', '2026-07-09 12:00:00+00:00');
        $this->terminalOperation(self::OUTCOME_HELD, 'completed', '2026-07-09 12:00:00+00:00');

        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET encoded_payload = NULL,
                encoded_context = NULL,
                payload_purged_at = :payload_purged_at
            WHERE operation_id = :operation_id',
            [
                'operation_id' => self::PAYLOAD_ALREADY_PURGED,
                'payload_purged_at' => '2026-07-09 00:00:00+00:00',
            ],
        );

        $this->hold(self::PAYLOAD_HELD, '019f32ab-2be0-7b38-a0a7-1ab2f9688b11');
        $this->hold(self::DEAD_LETTER_HELD, '019f32ab-2be0-7b38-a0a7-1ab2f9688b12');
        $this->hold(self::OUTCOME_HELD, '019f32ab-2be0-7b38-a0a7-1ab2f9688b13');
        $this->deadLetter(self::DEAD_LETTER_ELIGIBLE, '2026-07-07 00:00:00+00:00');
        $this->deadLetter(self::DEAD_LETTER_HELD, '2026-07-07 00:00:00+00:00');
        $this->outcome(self::OUTCOME_ELIGIBLE, '2026-06-20 00:00:00+00:00');
        $this->outcome(self::OUTCOME_HELD, '2026-06-20 00:00:00+00:00');
    }

    private function terminalOperation(string $operationId, string $state, string $updatedAt): void
    {
        $this->sender->enqueue($this->message($operationId));
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
            SET state = :state,
                updated_at = :updated_at
            WHERE operation_id = :operation_id',
            [
                'operation_id' => $operationId,
                'state' => $state,
                'updated_at' => $updatedAt,
            ],
        );
    }

    private function hold(string $operationId, string $holdId): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.retention_holds (
                hold_id,
                operation_id,
                category,
                reason,
                placed_at,
                placed_by
            ) VALUES (
                :hold_id,
                :operation_id,
                :category,
                :reason,
                :placed_at,
                :placed_by
            )', [
            'hold_id' => $holdId,
            'operation_id' => $operationId,
            'category' => 'legal',
            'reason' => 'legal request',
            'placed_at' => '2026-07-08 00:00:00+00:00',
            'placed_by' => 'legal-team',
        ]);
    }

    private function deadLetter(string $operationId, string $movedAt): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.dead_letters (
                operation_id,
                final_attempt_id,
                final_attempt_number,
                reason_type,
                reason_message,
                moved_at
            ) VALUES (
                :operation_id,
                NULL,
                NULL,
                :reason_type,
                :reason_message,
                :moved_at
            )', [
            'operation_id' => $operationId,
            'reason_type' => \RuntimeException::class,
            'reason_message' => 'boom',
            'moved_at' => $movedAt,
        ]);
    }

    private function outcome(string $operationId, string $completedAt): void
    {
        $this->connection->executeStatement('INSERT INTO ' . self::SCHEMA . '.outcomes (
            operation_id, outcome_type, schema_version, encoded_payload, completed_at
        ) VALUES (
            :operation_id, :outcome_type, 1, convert_to(:payload, \'UTF8\'), :completed_at
        )', [
            'operation_id' => $operationId,
            'outcome_type' => 'retention.test',
            'payload' => '{}',
            'completed_at' => $completedAt,
        ]);
    }

    private function policy(): RetentionPolicy
    {
        return new RetentionPolicy(
            RetentionPeriod::days(1),
            RetentionPeriod::days(30),
            RetentionPeriod::days(14),
            RetentionPeriod::days(2),
        );
    }

    private function message(string $operationId): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($operationId),
            'report.generate',
            1,
            '{"operationId":"' . $operationId . '"}',
            '{"correlationId":"c1"}',
            new DateTimeImmutable('2026-07-10T00:00:00.000000Z'),
        );
    }

    private function connection(): Connection
    {
        $host = (string) (getenv('POSTGRES_HOST') ?: 'postgres');
        $port = (int) (getenv('POSTGRES_PORT') ?: '5432');
        $db = (string) (getenv('POSTGRES_DB') ?: 'blackops');
        $user = (string) (getenv('POSTGRES_USER') ?: 'blackops');
        $password = (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops');

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => $port,
            'dbname' => $db,
            'user' => $user,
            'password' => $password,
        ]);
    }
}
