<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimHeartbeat;
use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\ClaimSettlement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationReceiver;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlDeferredOperationReceiverTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_009';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687721';
    private const SECOND_OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687722';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlDeferredOperationReceiver $receiver;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->receiver = new PostgreSqlDeferredOperationReceiver(
            $this->connection,
            self::SCHEMA,
            'worker-a',
            30,
            new FixedReceiverClock('2026-07-10T00:01:20.000000Z'),
        );
        $this->sender->migrate();
        $this->receiver->migrate();
    }

    public function testClaimMarksEligibleOperationRunningAndReturnsClaim(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));

        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);
        self::assertSame(self::OPERATION_ID, $claim->message()->operationId()->toString());
        self::assertSame('report.generate', $claim->message()->operationType());
        self::assertSame(1, $claim->message()->schemaVersion());
        self::assertSame('{"reportName":"weekly"}', $claim->message()->encodedPayload());
        self::assertSame('{"operationId":"' . self::OPERATION_ID . '"}', $claim->message()->encodedContext());
        self::assertSame(self::OPERATION_ID . ':1', $claim->claimToken());

        $row = $this->operationRow(self::OPERATION_ID);

        self::assertSame('running', $row['state']);
        self::assertSame(2, (int) $row['state_version']);
        self::assertSame('worker-a', $row['lease_owner']);
        self::assertSame('2026-07-10T00:01:30.000000Z', $row['lease_expires_at']);
        self::assertSame(1, (int) $row['fencing_token']);
    }

    public function testClaimReturnsNullWhenNoOperationIsEligible(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:10:00.000000Z'));

        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNull($claim);
        self::assertSame('accepted', $this->operationRow(self::OPERATION_ID)['state']);
    }

    public function testClaimSelectsOldestEligibleOperationFirst(): void
    {
        $this->sender->enqueue($this->message(self::SECOND_OPERATION_ID, '2026-07-10T00:05:00.000000Z'));
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));

        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:10:00.000000Z')));

        self::assertNotNull($claim);
        self::assertSame(self::OPERATION_ID, $claim->message()->operationId()->toString());
        self::assertSame('running', $this->operationRow(self::OPERATION_ID)['state']);
        self::assertSame('accepted', $this->operationRow(self::SECOND_OPERATION_ID)['state']);
    }

    public function testHeartbeatExtendsRunningLease(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));
        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);
        self::assertInstanceOf(ClaimHeartbeat::class, $this->receiver);

        $returned = $this->receiver->heartbeat($claim);
        $row = $this->operationRow(self::OPERATION_ID);

        self::assertSame($claim, $returned);
        self::assertSame('running', $row['state']);
        self::assertSame('worker-a', $row['lease_owner']);
        self::assertSame('2026-07-10T00:01:50.000000Z', $row['lease_expires_at']);
        self::assertSame('2026-07-10T00:01:20.000000Z', $row['updated_at']);
        self::assertSame(1, (int) $row['fencing_token']);
    }

    public function testHeartbeatRejectsStaleFencingToken(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));
        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);

        $this->expectException(\BlackOps\Core\Exception\DeferredTransportException::class);

        $this->receiver->heartbeat(new OperationClaim($claim->message(), self::OPERATION_ID . ':999'));
    }

    public function testHeartbeatRejectsNonRunningOperation(): void
    {
        $message = $this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z');
        $this->sender->enqueue($message);

        $this->expectException(\BlackOps\Core\Exception\DeferredTransportException::class);

        $this->receiver->heartbeat(new OperationClaim($message, self::OPERATION_ID . ':1'));
    }

    public function testAcknowledgeAcceptsTerminalClaim(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));
        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);
        self::assertInstanceOf(ClaimSettlement::class, $this->receiver);

        $this->markTerminal(self::OPERATION_ID, 'completed');
        $this->receiver->acknowledge($claim);

        self::assertSame('completed', $this->operationRow(self::OPERATION_ID)['state']);
    }

    public function testAcknowledgeRejectsStaleFencingToken(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));
        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);

        $this->markTerminal(self::OPERATION_ID, 'completed');
        $this->expectException(DeferredTransportException::class);

        $this->receiver->acknowledge(new OperationClaim($claim->message(), self::OPERATION_ID . ':999'));
    }

    public function testReleaseReturnsClaimToAcceptedBeforeAttemptStarts(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));
        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);

        $this->receiver->release($claim, new DateTimeImmutable('2026-07-10T00:02:05.000000Z'));
        $row = $this->operationRow(self::OPERATION_ID);

        self::assertSame('accepted', $row['state']);
        self::assertSame(3, (int) $row['state_version']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires_at']);
        self::assertSame(1, (int) $row['fencing_token']);
        self::assertSame('2026-07-10T00:02:05.000000Z', $row['available_at']);
        self::assertSame('2026-07-10T00:01:20.000000Z', $row['updated_at']);
    }

    public function testReleaseRejectsStartedAttempt(): void
    {
        $this->sender->enqueue($this->message(self::OPERATION_ID, '2026-07-10T00:00:00.000000Z'));
        $claim = $this->receiver->claim(new ClaimRequest(new DateTimeImmutable('2026-07-10T00:01:00.000000Z')));

        self::assertNotNull($claim);

        $this->markAttemptStarted(self::OPERATION_ID);
        $this->expectException(DeferredTransportException::class);

        $this->receiver->release($claim, new DateTimeImmutable('2026-07-10T00:02:05.000000Z'));
    }

    private function message(string $operationId, string $availableAt): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString($operationId),
            'report.generate',
            1,
            '{"reportName":"weekly"}',
            '{"operationId":"' . $operationId . '"}',
            new DateTimeImmutable($availableAt),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(string $operationId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                operation_id::text AS operation_id,
                state,
                state_version,
                to_char(available_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS available_at,
                lease_owner,
                to_char(lease_expires_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS lease_expires_at,
                fencing_token,
                current_attempt_id::text AS current_attempt_id,
                to_char(updated_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS updated_at
            FROM ' . self::SCHEMA . '.operations
            WHERE operation_id = :operation_id',
            ['operation_id' => $operationId],
        );

        self::assertIsArray($row);

        return $row;
    }

    private function markTerminal(string $operationId, string $state): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
                SET state = :state,
                    state_version = state_version + 1,
                    lease_owner = NULL,
                    lease_expires_at = NULL,
                    current_attempt_id = NULL,
                    current_attempt_started_at = NULL
                WHERE operation_id = :operation_id',
            [
                'operation_id' => $operationId,
                'state' => $state,
            ],
        );
    }

    private function markAttemptStarted(string $operationId): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::SCHEMA . '.operations
                SET attempt_number = 1,
                    current_attempt_id = :attempt_id,
                    current_attempt_started_at = :started_at
                WHERE operation_id = :operation_id',
            [
                'operation_id' => $operationId,
                'attempt_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687799',
                'started_at' => '2026-07-10 00:01:05.000000+00:00',
            ],
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

final readonly class FixedReceiverClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}
