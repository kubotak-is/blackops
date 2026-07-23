<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Idempotency;

use BlackOps\Core\ActorRef;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Idempotency\IdempotencyKeyHash;
use BlackOps\Internal\Idempotency\IdempotencyClaimStatus;
use BlackOps\Internal\Idempotency\IdempotencyRecordState;
use BlackOps\Internal\Idempotency\IdempotencyResponseSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyResultSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\OperationFingerprint;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\PostgreSqlIdempotencyStore;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Throwable;

final class PostgreSqlIdempotencyStoreTest extends TestCase
{
    private const string SCHEMA = 'blackops_p19_003_idempotency';

    private Connection $connection;
    private PostgreSqlIdempotencyStore $store;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => 'postgres',
            'port' => 5432,
            'dbname' => 'blackops',
            'user' => 'blackops',
            'password' => (string) getenv('POSTGRES_PASSWORD'),
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->store = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA);
        $this->store->migrate();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->connection->close();
    }

    public function testAtomicClaimAndCrossProcessRejectedReplayRoundTrip(): void
    {
        $key = new IdempotencyKey('cross-process');
        $scope = new IdempotencyScopeHasher()->hash('fixture.operation', new ActorRef('u-1', 'user'), $key);
        $value = new class implements \BlackOps\Core\OperationValue {
            public string $value = 'safe';
        };
        $fingerprint = new OperationValueFingerprinter()->fingerprint('fixture.operation', $value);
        $operation = OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e477e');
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $claim = $this->store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
        );

        self::assertSame(IdempotencyClaimStatus::Claimed, $claim->status());
        self::assertSame(
            IdempotencyClaimStatus::ExistingSameFingerprint,
            $this->store
                ->claim(
                    $scope,
                    $key->hash(),
                    $fingerprint,
                    $operation,
                    new Inline(),
                    $created,
                    $created->modify('+1 day'),
                )
                ->status(),
        );
        self::assertInstanceOf(ProcessingRecord::class, $claim->record());
        $record = $claim->record();
        $terminal = new TerminalRecord(
            $record->scope(),
            $record->key(),
            $record->fingerprint(),
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
            null,
            new IdempotencyResultSnapshot(OperationResult::rejected(
                RejectionReason::conflict('fixture.conflict'),
                $operation,
            )),
        );
        self::assertTrue($this->store->terminalize($operation, $terminal));

        $reloaded = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA)->find($scope);
        self::assertInstanceOf(TerminalRecord::class, $reloaded);
        self::assertSame('fixture.conflict', $reloaded->result()?->result()->rejectionReason()->code());
        self::assertSame($operation->toString(), $reloaded->result()?->result()->operationId()?->toString());
    }

    public function testCompletedEmptyOutcomeReplayRoundTripsAcrossStoreInstances(): void
    {
        $key = new IdempotencyKey('completed-cross-process');
        $scope = new IdempotencyScopeHasher()->hash('fixture.operation', new ActorRef('u-1', 'user'), $key);
        $value = new class implements \BlackOps\Core\OperationValue {
            public string $value = 'safe';
        };
        $fingerprint = new OperationValueFingerprinter()->fingerprint('fixture.operation', $value);
        $operation = OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e477f');
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $claim = $this->store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
        );
        $record = $claim->record();
        self::assertInstanceOf(ProcessingRecord::class, $record);
        self::assertTrue($this->store->terminalize(
            $operation,
            new TerminalRecord(
                $record->scope(),
                $record->key(),
                $record->fingerprint(),
                $operation,
                new Inline(),
                $created,
                $created->modify('+1 day'),
                null,
                new IdempotencyResultSnapshot(OperationResult::completed(new EmptyOutcome(), $operation)),
            ),
        ));

        $reloaded = new PostgreSqlIdempotencyStore($this->connection, self::SCHEMA)->find($scope);
        self::assertInstanceOf(TerminalRecord::class, $reloaded);
        self::assertInstanceOf(EmptyOutcome::class, $reloaded->result()?->result()->outcome());
    }

    public function testConcurrentClaimsAcrossTwoConnectionsHaveOneOriginalOperation(): void
    {
        self::assertTrue(function_exists('pcntl_fork'));
        $key = new IdempotencyKey('two-connections');
        $scope = new IdempotencyScopeHasher()->hash('fixture.operation', new ActorRef('u-1', 'user'), $key);
        $value = new class implements \BlackOps\Core\OperationValue {
            public string $value = 'safe';
        };
        $fingerprint = new OperationValueFingerprinter()->fingerprint('fixture.operation', $value);
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $operations = [
            OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4780'),
            OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4781'),
        ];
        $results = [tempnam(sys_get_temp_dir(), 'blackops-idem-'), tempnam(sys_get_temp_dir(), 'blackops-idem-')];
        $start = tempnam(sys_get_temp_dir(), 'blackops-idem-start-');
        self::assertIsString($results[0]);
        self::assertIsString($results[1]);
        self::assertIsString($start);
        unlink($start);
        $this->connection->close();
        $pids = [];
        foreach ($operations as $index => $operation) {
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                $connection = $this->connection();
                while (!is_file($start)) {
                    usleep(1000);
                }
                try {
                    $claim = new PostgreSqlIdempotencyStore($connection, self::SCHEMA)->claim(
                        $scope,
                        $key->hash(),
                        $fingerprint,
                        $operation,
                        new Inline(),
                        $created,
                        $created->modify('+1 day'),
                    );
                    file_put_contents(
                        $results[$index],
                        $claim->status()->value . '|' . $claim->record()->operationId()->toString(),
                    );
                    $connection->close();
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($results[$index], 'error|' . $exception::class);
                    exit(1);
                }
            }
            $pids[] = $pid;
        }
        file_put_contents($start, 'go');

        try {
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }
            $this->connection = $this->connection();
            $outcomes = [trim((string) file_get_contents($results[0])), trim((string) file_get_contents($results[1]))];
            $parsed = array_map(static fn(string $outcome): array => explode('|', $outcome, 2), $outcomes);
            $statuses = array_map(static fn(array $outcome): string => $outcome[0], $parsed);
            sort($statuses);
            self::assertSame(['claimed', 'existing_same_fingerprint'], $statuses);
            $operationIds = array_map(static fn(array $outcome): string => $outcome[1], $parsed);
            self::assertCount(1, array_unique($operationIds));
            $winner = $operationIds[0];
            self::assertContains($winner, array_map(
                static fn(OperationId $operation): string => $operation->toString(),
                $operations,
            ));
            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.idempotency_records'),
            );
            self::assertSame(
                $winner,
                $this->connection->fetchOne('SELECT operation_id::text FROM ' . self::SCHEMA . '.idempotency_records'),
            );
        } finally {
            foreach ([...$results, $start] as $path) {
                if (is_string($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testTerminalizeRequiresMatchingOperationFingerprintAndProcessingState(): void
    {
        [$scope, $key, $fingerprint, $operation, $created] = $this->claimFixture('terminal-guards');
        $record = $this->store->find($scope);
        self::assertInstanceOf(ProcessingRecord::class, $record);
        $terminal = new TerminalRecord(
            $scope,
            $key,
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
            null,
            new IdempotencyResultSnapshot(OperationResult::completed(new EmptyOutcome(), $operation)),
        );
        $wrongOperation = OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4782');
        self::assertFalse($this->store->terminalize($wrongOperation, $terminal));
        self::assertFalse($this->store->terminalize(
            $operation,
            new TerminalRecord(
                $scope,
                $key,
                new OperationFingerprint(1, str_repeat('b', 64)),
                $operation,
                new Inline(),
                $created,
                $created->modify('+1 day'),
            ),
        ));
        self::assertFalse($this->store->terminalize($operation, $terminal, IdempotencyRecordState::Terminal));
        self::assertTrue($this->store->terminalize($operation, $terminal));
        self::assertFalse($this->store->terminalize($operation, $terminal));
    }

    public function testTypedResultsAndSafeResponseSnapshotRoundTripAcrossStoreInstances(): void
    {
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $fixtures = [
            [
                'completed',
                OperationResult::completed(
                    new EmptyOutcome(),
                    OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4783'),
                ),
            ],
            [
                'rejected',
                OperationResult::rejected(
                    RejectionReason::conflict('fixture.conflict'),
                    OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4784'),
                ),
            ],
            ['internal', null],
        ];
        foreach ($fixtures as [$name, $result]) {
            $key = new IdempotencyKey('typed-' . $name);
            $scope = new IdempotencyScopeHasher()->hash('fixture.operation', new ActorRef('u-1', 'user'), $key);
            $fingerprint = new OperationValueFingerprinter()->fingerprint('fixture.operation', new class implements
                \BlackOps\Core\OperationValue {
                public string $value = 'safe';
            });
            $operation = $result?->operationId() ?? OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4785');
            $claim = $this->store->claim(
                $scope,
                $key->hash(),
                $fingerprint,
                $operation,
                new Inline(),
                $created,
                $created->modify('+1 day'),
            );
            $record = $claim->record();
            self::assertInstanceOf(ProcessingRecord::class, $record);
            $snapshot = $result === null
                ? IdempotencyResultSnapshot::internalFailure($operation)
                : new IdempotencyResultSnapshot($result);
            $response = new IdempotencyResponseSnapshot(
                1,
                202,
                [
                    'Content-Type' => 'application/json',
                    'Location' => '/operations/' . $operation->toString(),
                    'Retry-After' => '5',
                ],
                '{"safe":true}',
            );
            self::assertTrue($this->store->terminalize(
                $operation,
                new TerminalRecord(
                    $record->scope(),
                    $record->key(),
                    $record->fingerprint(),
                    $operation,
                    new Inline(),
                    $created,
                    $created->modify('+1 day'),
                    $response,
                    $snapshot,
                ),
            ));
            $otherConnection = $this->connection();
            try {
                $reloaded = new PostgreSqlIdempotencyStore($otherConnection, self::SCHEMA)->find($scope);
            } finally {
                $otherConnection->close();
            }
            self::assertInstanceOf(TerminalRecord::class, $reloaded);
            self::assertSame($operation->toString(), $reloaded->operationId()->toString());
            self::assertEqualsCanonicalizing($response->headers(), $reloaded->response()?->headers());
            self::assertSame($response->body(), $reloaded->response()?->body());
            if ($result === null) {
                self::assertTrue($reloaded->result()?->isInternalFailure());
            } elseif ($result->isCompleted()) {
                self::assertTrue($reloaded->result()?->result()->isCompleted());
            } else {
                self::assertTrue($reloaded->result()?->result()->isRejected());
                self::assertSame('fixture.conflict', $reloaded->result()?->result()->rejectionReason()->code());
            }
        }
    }

    public function testInvalidRowsFailClosedWithSafeErrorsAndProjectionConstraints(): void
    {
        [$scope, $key, $fingerprint, $operation, $created] = $this->claimFixture('invalid-row');
        $record = $this->store->find($scope);
        self::assertInstanceOf(ProcessingRecord::class, $record);
        self::assertTrue($this->store->terminalize(
            $operation,
            new TerminalRecord(
                $scope,
                $key,
                $fingerprint,
                $operation,
                new Inline(),
                $created,
                $created->modify('+1 day'),
                new IdempotencyResponseSnapshot(1, 200, ['Content-Type' => 'application/json'], '{}'),
                new IdempotencyResultSnapshot(OperationResult::completed(new EmptyOutcome(), $operation)),
            ),
        ));
        $table = self::SCHEMA . '.idempotency_records';
        foreach ([
            ['response_version',      99],
            ['result_schema_version', 99],
            ['strategy',              'unknown.strategy'],
        ] as [$column, $value]) {
            $this->connection->executeStatement("UPDATE {$table} SET {$column} = :value", ['value' => $value]);
            try {
                $this->store->find($scope);
                self::fail('Expected invalid idempotency row to fail closed.');
            } catch (DeferredTransportException $exception) {
                self::assertStringNotContainsString(self::SCHEMA, $exception->getMessage());
                self::assertStringNotContainsString('SELECT', $exception->getMessage());
                self::assertStringNotContainsString('fixture', $exception->getMessage());
            }
            $this->connection->executeStatement("UPDATE {$table} SET {$column} = :value", [
                'value' => $column === 'strategy' ? Inline::class : 1,
            ]);
        }
        $this->expectException(Throwable::class);
        $this->connection->executeStatement("UPDATE {$table} SET response_status = NULL");
    }

    public function testSchemaHelperAndMigrationHaveUniqueProjectionChecksWithoutOperationIndex(): void
    {
        $statements = new \BlackOps\Transport\PostgreSql\PostgreSqlIdempotencySchema(self::SCHEMA)->statements();
        $joined = implode("\n", $statements);
        foreach ([
            'CONSTRAINT idempotency_record_operation_id_unique UNIQUE (operation_id)',
            'idempotency_record_response_projection_check',
            'idempotency_record_result_projection_check',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $joined);
        }
        self::assertStringNotContainsString('CREATE INDEX idempotency_records_operation_id', $joined);
        $migration = file_get_contents(__DIR__ . '/../../../migrations/postgresql/Version20260724000000.php');
        self::assertIsString($migration);
        foreach ([
            'CONSTRAINT idempotency_record_operation_id_unique UNIQUE (operation_id)',
            'idempotency_record_response_projection_check',
            'idempotency_record_result_projection_check',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $migration);
        }
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT count(*) FROM pg_indexes WHERE schemaname = :schema AND tablename = :table AND indexdef ILIKE :pattern', [
                'schema' => self::SCHEMA,
                'table' => 'idempotency_records',
                'pattern' => '%operation_id%',
            ]),
        );
    }

    /** @return array{IdempotencyScopeHash, IdempotencyKeyHash, OperationFingerprint, OperationId, DateTimeImmutable} */
    private function claimFixture(string $suffix): array
    {
        $key = new IdempotencyKey($suffix);
        $scope = new IdempotencyScopeHasher()->hash('fixture.operation', new ActorRef('u-1', 'user'), $key);
        $fingerprint = new OperationFingerprint(1, str_repeat('a', 64));
        $operation = OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e4786');
        $created = new DateTimeImmutable('2026-07-24T00:00:00Z');
        $this->store->claim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operation,
            new Inline(),
            $created,
            $created->modify('+1 day'),
        );

        return [$scope, $key->hash(), $fingerprint, $operation, $created];
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => 'postgres',
            'port' => 5432,
            'dbname' => 'blackops',
            'user' => 'blackops',
            'password' => (string) getenv('POSTGRES_PASSWORD'),
        ]);
    }
}
