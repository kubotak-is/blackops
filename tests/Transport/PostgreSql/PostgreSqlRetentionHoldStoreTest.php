<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use BlackOps\Core\Retention\RetentionActorRef;
use BlackOps\Core\Retention\RetentionHoldCategory;
use BlackOps\Core\Retention\RetentionHoldPort;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldIdGenerator;
use BlackOps\Transport\PostgreSql\PostgreSqlRetentionHoldStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PostgreSqlRetentionHoldStoreTest extends TestCase
{
    private const SCHEMA = 'blackops_p5_004';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688901';
    private const HOLD_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688902';
    private const SECOND_HOLD_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688903';
    private const INLINE_OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9688904';

    private Connection $connection;
    private PostgreSqlDeferredOperationSender $sender;
    private PostgreSqlRetentionHoldStore $store;

    protected function setUp(): void
    {
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.000000Z'),
        );
        $this->store = new PostgreSqlRetentionHoldStore(
            $this->connection,
            self::SCHEMA,
            new FixedRetentionHoldClock('2026-07-10T00:00:02.000000Z'),
            new FixedRetentionHoldIdGenerator([self::HOLD_ID, self::SECOND_HOLD_ID]),
        );
        $this->sender->migrate();
        $this->sender->enqueue($this->message());
    }

    public function testStoreImplementsRetentionHoldPort(): void
    {
        self::assertInstanceOf(RetentionHoldPort::class, $this->store);
    }

    public function testPlacePersistsAndReturnsRetentionHold(): void
    {
        $hold = $this->store->place(
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Legal,
            'legal request',
            RetentionActorRef::fromString('legal-team'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );
        $row = $this->holdRow(self::HOLD_ID);

        self::assertSame(self::HOLD_ID, $hold->id()->toString());
        self::assertSame(self::OPERATION_ID, $hold->operationId()->toString());
        self::assertSame(RetentionHoldCategory::Legal, $hold->category());
        self::assertSame('legal request', $hold->reason());
        self::assertSame('legal-team', $hold->placedBy()->toString());
        self::assertTrue($hold->isActive());
        self::assertSame('legal', $row['category']);
        self::assertSame('legal request', $row['reason']);
        self::assertSame('legal-team', $row['placed_by']);
        self::assertNull($row['released_at']);
        self::assertNull($row['released_by']);
    }

    public function testReleaseUpdatesSameRetentionHoldRecord(): void
    {
        $this->store->place(
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Security,
            'security investigation',
            RetentionActorRef::fromString('security-team'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );

        $released = $this->store->release(
            RetentionHoldId::fromString(self::HOLD_ID),
            RetentionActorRef::fromString('security-lead'),
            new DateTimeImmutable('2026-07-11T00:00:00+09:00'),
        );
        $row = $this->holdRow(self::HOLD_ID);

        self::assertFalse($released->isActive());
        self::assertSame('2026-07-10T15:00:00+00:00', $released->releasedAt()?->format(DATE_ATOM));
        self::assertSame('security-lead', $released->releasedBy()?->toString());
        self::assertSame('2026-07-10T15:00:00.000000Z', $row['released_at']);
        self::assertSame('security-lead', $row['released_by']);
    }

    public function testReleaseRejectsAlreadyReleasedHold(): void
    {
        $this->store->place(
            OperationId::fromString(self::OPERATION_ID),
            RetentionHoldCategory::Audit,
            'audit review',
            RetentionActorRef::fromString('audit-team'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );
        $this->store->release(
            RetentionHoldId::fromString(self::HOLD_ID),
            RetentionActorRef::fromString('audit-lead'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );

        $this->expectException(DeferredTransportException::class);

        $this->store->release(
            RetentionHoldId::fromString(self::HOLD_ID),
            RetentionActorRef::fromString('audit-lead'),
            new DateTimeImmutable('2026-07-12T00:00:00Z'),
        );
    }

    public function testPlaceAndReleaseInlineOperationWithoutOperationsRow(): void
    {
        $operationId = OperationId::fromString(self::INLINE_OPERATION_ID);
        $hold = $this->store->place(
            $operationId,
            RetentionHoldCategory::Audit,
            'inline journal review',
            RetentionActorRef::fromString('audit-team'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );

        self::assertCount(1, $this->store->activeFor($operationId));
        $released = $this->store->release(
            $hold->id(),
            RetentionActorRef::fromString('audit-lead'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );

        self::assertFalse($released->isActive());
        self::assertSame([], $this->store->activeFor($operationId));
    }

    public function testActiveForReturnsOnlyUnreleasedHolds(): void
    {
        $operationId = OperationId::fromString(self::OPERATION_ID);
        $this->store->place(
            $operationId,
            RetentionHoldCategory::Support,
            'support dispute',
            RetentionActorRef::fromString('support-team'),
            new DateTimeImmutable('2026-07-10T00:00:00Z'),
        );
        $this->store->place(
            $operationId,
            RetentionHoldCategory::Other,
            'manual review',
            RetentionActorRef::fromString('ops-team'),
            new DateTimeImmutable('2026-07-10T00:01:00Z'),
        );
        $this->store->release(
            RetentionHoldId::fromString(self::HOLD_ID),
            RetentionActorRef::fromString('support-lead'),
            new DateTimeImmutable('2026-07-11T00:00:00Z'),
        );

        $active = $this->store->activeFor($operationId);

        self::assertCount(1, $active);
        self::assertSame(self::SECOND_HOLD_ID, $active[0]->id()->toString());
        self::assertSame(RetentionHoldCategory::Other, $active[0]->category());
    }

    /**
     * @return array<string, mixed>
     */
    private function holdRow(string $holdId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                hold_id::text AS hold_id,
                operation_id::text AS operation_id,
                category,
                reason,
                placed_by,
                to_char(released_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') AS released_at,
                released_by
            FROM ' . self::SCHEMA . '.retention_holds
            WHERE hold_id = :hold_id',
            ['hold_id' => $holdId],
        );

        self::assertIsArray($row);

        return $row;
    }

    private function message(): DeferredOperationMessage
    {
        return new DeferredOperationMessage(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            1,
            '{"reportId":"r1"}',
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

final readonly class FixedRetentionHoldClock implements ClockInterface
{
    public function __construct(
        private string $time,
    ) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->time);
    }
}

final class FixedRetentionHoldIdGenerator implements PostgreSqlRetentionHoldIdGenerator
{
    private int $index = 0;

    /**
     * @param list<string> $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function generate(DateTimeImmutable $time): RetentionHoldId
    {
        return RetentionHoldId::fromString($this->values[$this->index++]);
    }
}
