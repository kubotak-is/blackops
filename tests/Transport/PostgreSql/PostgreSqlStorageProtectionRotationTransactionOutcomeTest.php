<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\TenantRef;
use BlackOps\Internal\Console\StorageProtectionRotateCommand;
use BlackOps\Internal\Migration\DatabaseMigrationRunner;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\StorageProtection\StorageProtectionContext;
use BlackOps\Internal\StorageProtection\StorageProtectionRotationScope;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Transport\PostgreSql\PostgreSqlBytea;
use BlackOps\Transport\PostgreSql\PostgreSqlStorageProtectionRotation;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;
use Traversable;

/**
 * @mago-expect lint:no-empty-catch-clause
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlStorageProtectionRotationTransactionOutcomeTest extends TestCase
{
    private const string SCHEMA = 'blackops_rotation_outcome';
    private const string TENANT_TYPE = 'org';
    private const string TENANT_ID = 'tenant-a';

    private ?Connection $admin = null;
    private ?Connection $probe = null;

    /** @var list<Connection> */
    private array $connections = [];

    protected function setUp(): void
    {
        $this->admin = $this->connection();
        $this->connections[] = $this->admin;
        $this->admin->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        new DatabaseMigrationRunner($this->admin, self::SCHEMA)->migrate();
        $this->probe = $this->connection();
        $this->connections[] = $this->probe;
    }

    protected function tearDown(): void
    {
        $admin = $this->admin;
        foreach (array_reverse($this->connections) as $connection) {
            if ($connection === $admin) {
                continue;
            }
            if ($connection instanceof PostgreSqlStorageProtectionOutcomeFaultConnection) {
                $connection->resetFaults();
            }
            $this->restore($connection);
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
        if ($admin === null) {
            return;
        }
        $this->restore($admin);
        $dropFailure = null;
        try {
            $admin->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        } catch (Throwable $exception) {
            $dropFailure = $exception;
        }
        try {
            $admin->close();
        } catch (Throwable) {
        }
        if ($dropFailure !== null) {
            throw $dropFailure;
        }
    }

    public function testPlanRemainsReadOnly(): void
    {
        $rows = $this->seedRows(1);
        $scope = $this->scope('plan-only', 2, false);
        $before = $this->envelope($rows[0]['record']);

        $result = $this->rotation($this->admin())->plan($scope);

        self::assertSame(1, $result->selected);
        self::assertSame(0, $result->rotated);
        self::assertSame($before, $this->envelope($rows[0]['record']));
        self::assertSame(
            0,
            (int) $this->probe()->fetchOne(
                'SELECT count(*) FROM ' . self::SCHEMA . '.storage_protection_rotation_checkpoints',
            ),
        );
        self::assertSame(
            0,
            (int) $this->probe()->fetchOne(
                'SELECT count(*) FROM ' . self::SCHEMA . '.storage_protection_rotation_audits',
            ),
        );
    }

    public function testBeginCommitThenThrowStopsBeforeRowsAndFinalization(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(throwAfterCommitAt: 1);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope('begin-after', 2)));

        self::assertSame('d5r1-commit-after-1', $failure->getMessage());
        self::assertSame($rows[0]['old'], $this->envelope($rows[0]['record']));
        self::assertSame(
            ['state' => 'running', 'cursor_value' => null, 'failure_fingerprint' => null],
            $this->checkpoint('begin-after'),
        );
        self::assertSame(1, count($this->audits('begin-after')));
        self::assertSame('started', $this->audits('begin-after')[0]['state']);
        self::assertSame(0, $connection->rowMutationCalls());
        self::assertSame(0, $connection->remainingScanCalls());
        self::assertSame(0, $connection->finishAttempts());
    }

    public function testFirstRowCommitThenThrowPreservesCommittedProgressAndStopsTheBatch(): void
    {
        $rows = $this->seedRows(2);
        $connection = $this->faultConnection(throwAfterCommitAt: 2);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'first-row-after',
            2,
        )));

        self::assertSame('d5r1-commit-after-2', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        self::assertSame($rows[1]['old'], $this->envelope($rows[1]['record']));
        self::assertSame(
            ['state' => 'running', 'cursor_value' => $rows[0]['record'], 'failure_fingerprint' => null],
            $this->checkpoint('first-row-after'),
        );
        $audits = $this->audits('first-row-after');
        self::assertCount(1, $audits);
        self::assertSame('started', $audits[0]['state']);
        self::assertSame('1', (string) $audits[0]['rotated_count']);
        self::assertSame(1, $connection->rowMutationCalls());
        self::assertSame(0, $connection->remainingScanCalls());
        self::assertSame(0, $connection->finishAttempts());
    }

    public function testLaterRowCommitThenThrowStopsLaterRowsAndFinalization(): void
    {
        $rows = $this->seedRows(3);
        $connection = $this->faultConnection(throwAfterCommitAt: 3);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'later-row-after',
            3,
        )));

        self::assertSame('d5r1-commit-after-3', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        self::assertSame($this->newEnvelope($rows[1]['record']), $this->envelope($rows[1]['record']));
        self::assertSame($rows[2]['old'], $this->envelope($rows[2]['record']));
        self::assertSame($rows[1]['record'], $this->checkpoint('later-row-after')['cursor_value']);
        self::assertSame(2, $connection->rowMutationCalls());
        self::assertSame(0, $connection->remainingScanCalls());
        self::assertSame(0, $connection->finishAttempts());
    }

    public function testCommitBeforeEffectRollsBackTheRowAndDoesNotFinalize(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(throwBeforeCommitAt: 2);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope('row-before', 2)));

        self::assertSame('d5r1-commit-before-2', $failure->getMessage());
        self::assertSame($rows[0]['old'], $this->envelope($rows[0]['record']));
        self::assertSame(
            ['state' => 'running', 'cursor_value' => null, 'failure_fingerprint' => null],
            $this->checkpoint('row-before'),
        );
        self::assertSame('started', $this->audits('row-before')[0]['state']);
        self::assertSame(0, $connection->finishAttempts());
    }

    public function testAdvancementFailureRollsBackProtectionAndFinishesOneFailedResult(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(executeFailureNeedle: 'SET cursor_value = ?');

        $result = $this->rotation($connection)->rotate($this->scope('advance-before', 2));

        self::assertSame('failed', $result->state);
        self::assertSame(1, $result->failed);
        self::assertSame($rows[0]['old'], $this->envelope($rows[0]['record']));
        self::assertSame('failed', $this->checkpoint('advance-before')['state']);
        self::assertSame('failed', $this->audits('advance-before')[0]['state']);
        self::assertSame('1', (string) $this->audits('advance-before')[0]['failed_count']);
        self::assertSame(1, $connection->rowMutationCalls());
        self::assertSame(1, $connection->remainingScanCalls());
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testRollbackFailureRemainsAnAbortPath(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(throwBeforeCommitAt: 2, throwOnRollback: true);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'rollback-failure',
            2,
        )));

        self::assertSame('d5r1-rollback-failure', $failure->getMessage());
        self::assertSame($rows[0]['old'], $this->envelope($rows[0]['record']));
        self::assertSame(0, $connection->finishAttempts());
    }

    public function testCompleteFinishCommitThenThrowRetainsTerminalStateWithoutRetry(): void
    {
        $rows = $this->seedRows(1);
        $scope = $this->scope('complete-after', 2);
        self::assertSame('complete', $this->rotation($this->admin())->rotate($scope)->state);
        $connection = $this->faultConnection(throwAfterCommitAt: 2);

        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($scope));

        self::assertSame('d5r1-commit-after-2', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        self::assertSame('complete', $this->checkpoint('complete-after')['state']);
        self::assertSame(['complete', 'complete'], array_column($this->audits('complete-after'), 'state'));
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testFailedFinishCommitThenThrowRetainsFailureStateWithoutRetry(): void
    {
        $this->seedRows(1, true);
        $connection = $this->faultConnection(throwAfterCommitAt: 2);

        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope('failed-after', 2)));

        self::assertSame('d5r1-commit-after-2', $failure->getMessage());
        $checkpoint = $this->checkpoint('failed-after');
        self::assertSame('failed', $checkpoint['state']);
        self::assertMatchesRegularExpression('/^v1:[0-9a-f]{64}$/', (string) $checkpoint['failure_fingerprint']);
        $audits = $this->audits('failed-after');
        self::assertCount(1, $audits);
        self::assertSame('failed', $audits[0]['state']);
        self::assertSame('1', (string) $audits[0]['failed_count']);
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testCompleteFinishCommitBeforeEffectRollsBackOnce(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(throwBeforeCommitAt: 3);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'complete-before',
            2,
        )));

        self::assertSame('d5r1-commit-before-3', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        self::assertSame('running', $this->checkpoint('complete-before')['state']);
        self::assertSame('started', $this->audits('complete-before')[0]['state']);
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testFailedFinishCommitBeforeEffectRollsBackOnce(): void
    {
        $this->seedRows(1, true);
        $connection = $this->faultConnection(throwBeforeCommitAt: 2);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'failed-before',
            2,
        )));

        self::assertSame('d5r1-commit-before-2', $failure->getMessage());
        self::assertSame('running', $this->checkpoint('failed-before')['state']);
        self::assertSame('started', $this->audits('failed-before')[0]['state']);
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testFinalizationSqlFailureDoesNotRetryFailureFinish(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(executeFailureNeedle: 'SET state = ?, selected_count');
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope('finish-sql', 2)));

        self::assertSame('d5r1-sql-failure', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        self::assertSame('running', $this->checkpoint('finish-sql')['state']);
        self::assertSame('started', $this->audits('finish-sql')[0]['state']);
        self::assertSame(1, $connection->remainingScanCalls());
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testRemainingScanFailureUsesOneSuccessfulFailureFinish(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(throwOnRemaining: true);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'remaining-success',
            2,
        )));

        self::assertSame('d5r1-remaining-failure', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        self::assertSame('failed', $this->checkpoint('remaining-success')['state']);
        self::assertSame('failed', $this->audits('remaining-success')[0]['state']);
        self::assertSame(1, $connection->remainingScanCalls());
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testRemainingScanFailureWithCommitThenThrowStillPropagatesOriginal(): void
    {
        $this->seedRows(1);
        $connection = $this->faultConnection(throwAfterCommitAt: 3, throwOnRemaining: true);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'remaining-after',
            2,
        )));

        self::assertSame('d5r1-remaining-failure', $failure->getMessage());
        self::assertSame('failed', $this->checkpoint('remaining-after')['state']);
        self::assertSame('failed', $this->audits('remaining-after')[0]['state']);
        self::assertSame(1, $connection->remainingScanCalls());
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testRemainingScanFailureWithFailedRecoveryFinishRethrowsOriginalWithoutRetry(): void
    {
        $rows = $this->seedRows(1);
        $connection = $this->faultConnection(
            executeFailureNeedle: 'SET state = ?, selected_count',
            throwOnRemaining: true,
        );

        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'remaining-finish-sql',
            2,
        )));

        self::assertSame('d5r1-remaining-failure', $failure->getMessage());
        self::assertSame($this->newEnvelope($rows[0]['record']), $this->envelope($rows[0]['record']));
        $checkpoint = $this->checkpoint('remaining-finish-sql');
        self::assertSame('running', $checkpoint['state']);
        self::assertSame($rows[0]['record'], $checkpoint['cursor_value']);
        $audits = $this->audits('remaining-finish-sql');
        self::assertCount(1, $audits);
        self::assertSame('started', $audits[0]['state']);
        self::assertSame('1', (string) $audits[0]['rotated_count']);
        self::assertSame('0', (string) $audits[0]['failed_count']);
        self::assertSame(1, $connection->remainingScanCalls());
        self::assertSame(1, $connection->finishAttempts());
    }

    public function testExplicitResumeKeepsACommittedRowBytesAndRotatesOnlyRemainingRows(): void
    {
        $rows = $this->seedRows(2);
        $scope = $this->scope('resume-after', 2);
        $connection = $this->faultConnection(throwAfterCommitAt: 2);
        $this->captureFailure(fn() => $this->rotation($connection)->rotate($scope));
        $firstAfterFailure = $this->envelope($rows[0]['record']);

        $result = $this->rotation($this->admin())->rotate($scope);

        self::assertSame(1, $result->rotated);
        self::assertSame('complete', $result->state);
        self::assertSame($firstAfterFailure, $this->envelope($rows[0]['record']));
        self::assertSame($this->newEnvelope($rows[1]['record']), $this->envelope($rows[1]['record']));
        $states = array_column($this->audits('resume-after'), 'state');
        self::assertCount(2, $states);
        self::assertSame(1, count(array_filter($states, static fn(mixed $state): bool => $state === 'failed')));
        self::assertSame(1, count(array_filter($states, static fn(mixed $state): bool => $state === 'complete')));
    }

    public function testCliMapsNativeStorageFailureAndRetainsInvalidArgumentBranch(): void
    {
        $this->seedRows(2);
        $storageFailure = $this->faultConnection(throwAfterCommitAt: 2, throwNativeAfterCommit: true);
        $storageJson = new CommandTester(new StorageProtectionRotateCommand($this->rotation($storageFailure)));
        $arguments = $this->cliArguments('cli-storage', true, 1);

        self::assertSame(1, $storageJson->execute([...$arguments, '--json' => true]));
        $nativeFailure = $storageFailure->lastFailure();
        self::assertInstanceOf(DriverException::class, $nativeFailure);
        self::assertStringContainsString('d5r1-storage-canary', $nativeFailure->getMessage());
        self::assertSame(
            ['schemaVersion' => 1, 'status' => 'failed', 'code' => 'storage_error'],
            json_decode($storageJson->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('d5r1-storage-canary', $storageJson->getDisplay());

        $inputFailure = $this->faultConnection(throwBeforeCommitAt: 2, throwInvalidArgument: true);
        $inputHuman = new CommandTester(new StorageProtectionRotateCommand($this->rotation($inputFailure)));
        self::assertSame(2, $inputHuman->execute($this->cliArguments('cli-input', true)));
        self::assertStringContainsString('Input error: d5r1-commit-before-2', $inputHuman->getDisplay());
        self::assertStringNotContainsString('Storage protection rotation failed safely.', $inputHuman->getDisplay());

        $inputJsonFailure = $this->faultConnection(throwBeforeCommitAt: 2, throwInvalidArgument: true);
        $inputJson = new CommandTester(new StorageProtectionRotateCommand($this->rotation($inputJsonFailure)));
        self::assertSame(2, $inputJson->execute([...$this->cliArguments('cli-input-json', true), '--json' => true]));
        self::assertSame(
            ['schemaVersion' => 1, 'status' => 'failed', 'code' => 'input_error'],
            json_decode($inputJson->getDisplay(), true, 512, JSON_THROW_ON_ERROR),
        );

        $storageHumanConnection = $this->faultConnection(throwAfterCommitAt: 2, throwNativeAfterCommit: true);
        $storageHuman = new CommandTester(new StorageProtectionRotateCommand($this->rotation($storageHumanConnection)));
        self::assertSame(1, $storageHuman->execute($this->cliArguments('cli-human', true)));
        self::assertStringContainsString('Storage protection rotation failed safely.', $storageHuman->getDisplay());
        self::assertStringNotContainsString('d5r1-storage-canary', $storageHuman->getDisplay());
    }

    public function testUnlockFailureAbortsAfterPersistedTerminalFinish(): void
    {
        $this->seedRows(1);
        $connection = $this->faultConnection(throwOnUnlock: true);
        $failure = $this->captureFailure(fn() => $this->rotation($connection)->rotate($this->scope(
            'unlock-failure',
            2,
        )));

        self::assertSame('d5r1-unlock-failure', $failure->getMessage());
        self::assertSame('complete', $this->checkpoint('unlock-failure')['state']);
        self::assertSame('complete', $this->audits('unlock-failure')[0]['state']);
        self::assertSame(1, $connection->unlockCalls());
        self::assertSame(1, $connection->finishAttempts());
    }

    /** @return list<array{record:string,old:string,context:StorageProtectionContext}> */
    private function seedRows(int $count, bool $corruptLast = false): array
    {
        $old = new BopdEnvelopeCodec(new PostgreSqlStorageProtectionOutcomeKeyProvider('old:v1'));
        $tenant = new TenantRef(self::TENANT_TYPE, self::TENANT_ID);
        $rows = [];
        for ($index = 1; $index <= $count; ++$index) {
            $record = sprintf('019f32ab-2be0-7%03x-8000-%012x', $index, $index);
            $operation = sprintf('019f32ab-2be0-7%03x-8001-%012x', $index, $index);
            $context = new StorageProtectionContext(
                StoragePurpose::JournalRecord,
                $record,
                $operation,
                'rotation.fixture',
                1,
                $tenant,
            );
            $envelope = $old->encrypt('rotation-payload-' . $index, $context);
            $stored = $corruptLast && $index === $count
                ? substr_replace($envelope, chr(ord($envelope[strlen($envelope) - 1]) ^ 1), -1, 1)
                : $envelope;
            $this->admin()->insert(
                self::SCHEMA . '.journal',
                [
                    'record_id' => $record,
                    'operation_id' => $operation,
                    'operation_type' => 'rotation.fixture',
                    'sequence' => $index,
                    'event' => 'received',
                    'schema_version' => 1,
                    'operation_schema_version' => 1,
                    'occurred_at' => '2026-09-29 00:00:00+00',
                    'encoded_record' => $stored,
                    'tenant_type' => self::TENANT_TYPE,
                    'tenant_id' => self::TENANT_ID,
                ],
                ['encoded_record' => ParameterType::BINARY],
            );
            $rows[] = ['record' => $record, 'old' => $stored, 'context' => $context];
        }
        return $rows;
    }

    private function newEnvelope(string $record): string
    {
        $row = $this->probe()->fetchOne('SELECT encoded_record FROM ' . self::SCHEMA . '.journal WHERE record_id = ?', [
            $record,
        ]);
        $envelope = PostgreSqlBytea::string($row);
        self::assertSame(
            'new:v1',
            new BopdEnvelopeCodec(new PostgreSqlStorageProtectionOutcomeKeyProvider('new:v1'))->keyId($envelope),
        );
        return $envelope;
    }

    private function envelope(string $record): string
    {
        return PostgreSqlBytea::string($this->probe()->fetchOne('SELECT encoded_record FROM '
        . self::SCHEMA
        . '.journal WHERE record_id = ?', [$record]));
    }

    /** @return array{state:string,cursor_value:?string,failure_fingerprint:?string} */
    private function checkpoint(string $checkpoint): array
    {
        $row = $this->probe()->fetchAssociative('SELECT state, cursor_value, failure_fingerprint FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_checkpoints WHERE checkpoint_id = ?', [$checkpoint]);
        self::assertIsArray($row);
        return [
            'state' => (string) $row['state'],
            'cursor_value' => $row['cursor_value'] === null ? null : (string) $row['cursor_value'],
            'failure_fingerprint' => $row['failure_fingerprint'] === null ? null : (string) $row['failure_fingerprint'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function audits(string $checkpoint): array
    {
        return $this->probe()->fetchAllAssociative('SELECT state, selected_count, rotated_count, skipped_count, failed_count FROM '
        . self::SCHEMA
        . '.storage_protection_rotation_audits WHERE checkpoint_id = ? ORDER BY started_at, audit_id', [$checkpoint]);
    }

    private function scope(string $checkpoint, int $batchSize, bool $confirmed = true): StorageProtectionRotationScope
    {
        return new StorageProtectionRotationScope(
            StoragePurpose::JournalRecord,
            new TenantRef(self::TENANT_TYPE, self::TENANT_ID),
            'old:v1',
            'new:v1',
            $batchSize,
            $checkpoint,
            $confirmed ? 'fixture.actor' : null,
            $confirmed ? 'fixture.reason' : null,
            $confirmed,
        );
    }

    /** @return array<string,string|bool> */
    private function cliArguments(string $checkpoint, bool $confirm, int $batchSize = 2): array
    {
        return [
            '--purpose' => StoragePurpose::JournalRecord->value,
            '--tenant-type' => self::TENANT_TYPE,
            '--tenant-id' => self::TENANT_ID,
            '--old-key-id' => 'old:v1',
            '--new-key-id' => 'new:v1',
            '--batch' => (string) $batchSize,
            '--checkpoint' => $checkpoint,
            '--actor' => 'fixture.actor',
            '--reason' => 'fixture.reason',
            '--confirm' => $confirm,
        ];
    }

    private function rotation(Connection $connection): PostgreSqlStorageProtectionRotation
    {
        return new PostgreSqlStorageProtectionRotation(
            $connection,
            new BopdEnvelopeCodec(new PostgreSqlStorageProtectionOutcomeKeyProvider('new:v1')),
            self::SCHEMA,
        );
    }

    private function faultConnection(
        ?int $throwBeforeCommitAt = null,
        ?int $throwAfterCommitAt = null,
        ?string $executeFailureNeedle = null,
        bool $throwOnRemaining = false,
        bool $throwOnUnlock = false,
        bool $throwOnRollback = false,
        bool $throwInvalidArgument = false,
        bool $throwNativeAfterCommit = false,
    ): PostgreSqlStorageProtectionOutcomeFaultConnection {
        $source = $this->connection();
        $connection = new PostgreSqlStorageProtectionOutcomeFaultConnection(
            $source,
            $throwBeforeCommitAt,
            $throwAfterCommitAt,
            $executeFailureNeedle,
            $throwOnRemaining,
            $throwOnUnlock,
            $throwOnRollback,
            $throwInvalidArgument,
            $throwNativeAfterCommit,
        );
        $source->close();
        $this->connections[] = $connection;
        return $connection;
    }

    private function captureFailure(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            return $exception;
        }
        self::fail('The operation was expected to throw.');
    }

    private function admin(): Connection
    {
        self::assertInstanceOf(Connection::class, $this->admin);
        return $this->admin;
    }

    private function probe(): Connection
    {
        self::assertInstanceOf(Connection::class, $this->probe);
        return $this->probe;
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
    }

    private function restore(Connection $connection): void
    {
        try {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (Throwable) {
        }
        try {
            if (!$connection->isAutoCommit()) {
                $connection->setAutoCommit(true);
            }
        } catch (Throwable) {
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final class PostgreSqlStorageProtectionOutcomeFaultConnection extends Connection
{
    private int $commitCount = 0;
    private int $rowMutationCount = 0;
    private int $finishAttemptCount = 0;
    private int $remainingScanCount = 0;
    private bool $executeFaulted = false;
    private bool $remainingFaulted = false;
    private int $unlockCount = 0;

    public function __construct(
        Connection $source,
        private ?int $throwBeforeCommitAt = null,
        private ?int $throwAfterCommitAt = null,
        private ?string $executeFailureNeedle = null,
        private bool $throwOnRemaining = false,
        private bool $throwOnUnlock = false,
        private bool $throwOnRollback = false,
        private bool $throwInvalidArgument = false,
        private bool $throwNativeAfterCommit = false,
    ) {
        parent::__construct($source->getParams(), $source->getDriver(), $source->getConfiguration());
    }

    private ?Throwable $lastFailure = null;

    public function commit(): void
    {
        ++$this->commitCount;
        if ($this->throwBeforeCommitAt === $this->commitCount) {
            throw $this->failure('commit-before-' . $this->commitCount);
        }
        parent::commit();
        if ($this->throwAfterCommitAt === $this->commitCount) {
            if ($this->throwNativeAfterCommit) {
                try {
                    parent::executeStatement('SELECT CAST(? AS integer)', ['d5r1-storage-canary']);
                } catch (Throwable $exception) {
                    $this->lastFailure = $exception;
                    throw $exception;
                }
            }
            throw $this->failure('commit-after-' . $this->commitCount);
        }
    }

    public function rollBack(): void
    {
        if ($this->throwOnRollback) {
            throw $this->failure('rollback-failure');
        }
        parent::rollBack();
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (str_contains($sql, ' SET encoded_record = ')) {
            ++$this->rowMutationCount;
        }
        if (str_contains($sql, 'SET state = ?, selected_count')) {
            ++$this->finishAttemptCount;
        }
        if (str_contains($sql, 'pg_advisory_unlock')) {
            ++$this->unlockCount;
            if ($this->throwOnUnlock) {
                throw $this->failure('unlock-failure');
            }
        }
        if (
            !$this->executeFaulted
            && $this->executeFailureNeedle !== null
            && str_contains($sql, $this->executeFailureNeedle)
        ) {
            $this->executeFaulted = true;
            throw $this->failure('sql-failure');
        }
        return parent::executeStatement($sql, $params, $types);
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        if (str_contains($query, 'SELECT substring(')) {
            ++$this->remainingScanCount;
        }
        if ($this->throwOnRemaining && !$this->remainingFaulted && str_contains($query, 'SELECT substring(')) {
            $this->remainingFaulted = true;
            throw $this->failure('remaining-failure');
        }
        return parent::iterateAssociative($query, $params, $types);
    }

    public function resetFaults(): void
    {
        $this->throwBeforeCommitAt = null;
        $this->throwAfterCommitAt = null;
        $this->executeFailureNeedle = null;
        $this->throwOnRemaining = false;
        $this->throwOnUnlock = false;
        $this->throwOnRollback = false;
        $this->throwInvalidArgument = false;
        $this->throwNativeAfterCommit = false;
    }

    public function rowMutationCalls(): int
    {
        return $this->rowMutationCount;
    }

    public function finishAttempts(): int
    {
        return $this->finishAttemptCount;
    }

    public function remainingScanCalls(): int
    {
        return $this->remainingScanCount;
    }

    public function unlockCalls(): int
    {
        return $this->unlockCount;
    }

    public function lastFailure(): ?Throwable
    {
        return $this->lastFailure;
    }

    private function failure(string $suffix): Throwable
    {
        $message = 'd5r1-' . $suffix;
        return $this->throwInvalidArgument ? new InvalidArgumentException($message) : new \RuntimeException($message);
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class PostgreSqlStorageProtectionOutcomeKeyProvider implements StorageKeyProvider
{
    public function __construct(
        private string $activeKeyId,
    ) {}

    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return $this->key($this->activeKeyId, $tenant, $purpose);
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat(
            $keyId === 'old:v1' ? 'o' : 'n',
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
        ));
    }
}
