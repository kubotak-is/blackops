<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Replay\ObserverReplayRequest;
use BlackOps\Internal\Replay\ObserverReplayRuntime;
use BlackOps\Internal\Replay\ObserverReplayTargetRegistry;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalSchema;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBeginRequest;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayBinding;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplaySelector;
use BlackOps\Transport\PostgreSql\PostgreSqlObserverReplayStore;
use DateTimeImmutable;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgreSqlObserverReplayTransactionOutcomeTest extends TestCase
{
    private const SCHEMA = 'blackops_replay_outcome';
    private const OPERATION = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const RECORD_ONE = '019f32ab-2be0-7b38-a0a7-1ab2f968769a';
    private const RECORD_TWO = '019f32ab-2be0-7b38-a0a7-1ab2f968769b';
    private const RECORD_THREE = '019f32ab-2be0-7b38-a0a7-1ab2f968769c';

    private Connection $connection;

    /** @var list<Connection> */
    private array $connections = [];

    protected function setUp(): void
    {
        $this->connection = $this->realConnection();
        $this->connections = [$this->connection];
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        foreach (new PostgreSqlJournalSchema(self::SCHEMA)->statements() as $statement) {
            $this->connection->executeStatement($statement);
        }
        $this->connection->executeStatement(
            'CREATE TABLE "' . self::SCHEMA . '"."caller_sentinel" (id integer PRIMARY KEY)',
        );
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->connections) as $connection) {
            if ($connection instanceof ReplayOutcomeFaultConnection) {
                $connection->disarm();
            }
            if ($connection->isConnected()) {
                $connection->close();
            }
        }

        $cleanup = $this->realConnection();
        try {
            $cleanup->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        } finally {
            $cleanup->close();
        }
    }

    public function testAllMutationRootsRejectForeignRootsBeforeSql(): void
    {
        $methods = [
            'begin' => function (PostgreSqlObserverReplayStore $store): void {
                $store->begin($this->request('admission-begin'));
            },
            'advance' => function (PostgreSqlObserverReplayStore $store): void {
                $store->advance('admission-advance', '1|' . self::RECORD_ONE, 1, 'audit-id');
            },
            'finish' => function (PostgreSqlObserverReplayStore $store): void {
                $store->finishInvocation('admission-finish', 'complete', auditId: 'audit-id');
            },
            'fail' => function (PostgreSqlObserverReplayStore $store): void {
                $store->fail('admission-fail', new RuntimeException('failure'), auditId: 'audit-id');
            },
        ];

        foreach ($methods as $name => $method) {
            $connection = $this->faultConnection();
            $store = $this->store($connection);

            $connection->beginTransaction();
            $this->insertCallerSentinel($connection, 1);
            $connection->resetCounters();
            try {
                $this->assertMutationRejected($method, $store, $name . ' ambient');
                self::assertTrue($connection->isTransactionActive());
                self::assertSame(1, $connection->getTransactionNestingLevel());
                self::assertSame(0, $connection->sqlCalls);
                self::assertSame(1, $this->callerSentinelCount($connection));
                self::assertSame(0, $connection->beginCalls);
                self::assertSame(0, $connection->commitCalls);
                self::assertSame(0, $connection->rollbackCalls);
                self::assertSame(0, $connection->lockAcquireCalls);
                self::assertSame(0, $connection->lockReleaseCalls);
            } finally {
                $connection->rollBack();
            }

            $connection->beginTransaction();
            $this->insertCallerSentinel($connection, 2);
            $connection->beginTransaction();
            $connection->resetCounters();
            try {
                $this->assertMutationRejected($method, $store, $name . ' nested');
                self::assertTrue($connection->isTransactionActive());
                self::assertSame(2, $connection->getTransactionNestingLevel());
                self::assertSame(0, $connection->sqlCalls);
                self::assertSame(1, $this->callerSentinelCount($connection));
                self::assertSame(0, $connection->beginCalls);
                self::assertSame(0, $connection->commitCalls);
                self::assertSame(0, $connection->rollbackCalls);
                self::assertSame(0, $connection->lockAcquireCalls);
                self::assertSame(0, $connection->lockReleaseCalls);
            } finally {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            }

            $connection->setAutoCommit(false);
            $connection->resetCounters();
            $this->assertMutationRejected($method, $store, $name . ' non-autocommit');
            self::assertFalse($connection->isAutoCommit());
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, $connection->sqlCalls);
            self::assertSame(0, $connection->beginCalls);
            self::assertSame(0, $connection->commitCalls);
            self::assertSame(0, $connection->rollbackCalls);
            self::assertSame(0, $connection->lockAcquireCalls);
            self::assertSame(0, $connection->lockReleaseCalls);
            $connection->setAutoCommit(true);
        }
    }

    public function testBeginTransactionFaultsReleaseTheAcquiredAdvisoryLock(): void
    {
        foreach (['before', 'after'] as $mode) {
            $connection = $this->faultConnection();
            $connection->armBeginFault($mode);
            $store = $this->store($connection);

            $failure = null;
            try {
                $store->begin($this->request('begin-transaction-' . $mode));
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(RuntimeException::class, $failure);
            self::assertSame('replay begin transaction ' . $mode, $failure->getMessage());

            self::assertFalse($connection->isTransactionActive());
            self::assertSame(1, $connection->beginCalls);
            self::assertSame(0, $connection->commitCalls);
            self::assertSame($mode === 'after' ? 1 : 0, $connection->rollbackCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            self::assertSame(0, $this->checkpointCount('begin-transaction-' . $mode));
            self::assertSame(0, $this->auditCount('begin-transaction-' . $mode));
            $this->assertLockAvailable('begin-transaction-' . $mode);
        }
    }

    public function testBeginPrecommitAndCommitOutcomesPreserveDurabilityMeaning(): void
    {
        $cases = [
            'precommit' => static function (ReplayOutcomeFaultConnection $connection, string $schema): void {
                $connection->armStatementFault('INSERT INTO "' . $schema . '"."observer_replay_checkpoints"', 'before');
            },
            'commit-before' => static function (ReplayOutcomeFaultConnection $connection, string $schema): void {
                $connection->armCommitFault('before');
            },
            'commit-after' => static function (ReplayOutcomeFaultConnection $connection, string $schema): void {
                $connection->armCommitFault('after');
            },
        ];

        foreach ($cases as $name => $arm) {
            $connection = $this->faultConnection();
            $checkpoint = 'begin-' . str_replace('-', '.', $name);
            $arm($connection, self::SCHEMA);
            $failure = null;
            try {
                $this->store($connection)->begin($this->request($checkpoint));
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(RuntimeException::class, $failure);

            $row = $this->checkpointRow($checkpoint);
            $audit = $this->auditRows($checkpoint);
            if ($name === 'commit-after') {
                self::assertSame('running', $row['state']);
                self::assertCount(1, $audit);
                self::assertSame('started', $audit[0]['state']);
            } else {
                self::assertFalse($row);
                self::assertSame([], $audit);
            }
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(1, $connection->beginCalls);
            self::assertSame($name === 'precommit' ? 0 : 1, $connection->commitCalls);
            self::assertSame($name === 'commit-after' ? 0 : 1, $connection->rollbackCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            $this->assertLockAvailable($checkpoint);
        }
    }

    public function testBeginRollbackFailureStillAttemptsUnlock(): void
    {
        $connection = $this->faultConnection();
        $checkpoint = 'begin-rollback-failure';
        $connection->armStatementFault('INSERT INTO "' . self::SCHEMA . '"."observer_replay_checkpoints"', 'before');
        $connection->armRollbackFault();

        $failure = null;
        try {
            $this->store($connection)->begin($this->request($checkpoint));
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(RuntimeException::class, $failure);
        self::assertSame('Replay transaction rollback failed.', $failure->getMessage());

        self::assertTrue($connection->isTransactionActive());
        self::assertSame(1, $connection->beginCalls);
        self::assertSame(0, $connection->commitCalls);
        self::assertSame(1, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        $connection->disarmRollbackFault();
        $connection->rollBack();
        $this->assertLockAvailable($checkpoint);
    }

    public function testRuntimeBeginFaultDoesNotSelectDeliverOrFinalize(): void
    {
        $this->insertRecords(3);
        foreach (['before', 'after'] as $mode) {
            $connection = $this->faultConnection();
            $observer = new ReplayOutcomeRecordingObserver();
            $runtime = $this->runtime($connection, $observer);
            $checkpoint = 'runtime-begin-' . $mode;
            $connection->armBeginFault($mode);

            $failure = null;
            try {
                $runtime->replay($this->replayRequest($checkpoint, 3));
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(RuntimeException::class, $failure);
            self::assertSame('replay begin transaction ' . $mode, $failure->getMessage());
            self::assertSame([], $observer->recordIds);
            self::assertFalse($this->checkpointRow($checkpoint));
            self::assertSame([], $this->auditRows($checkpoint));
            self::assertSame(1, $connection->beginCalls);
            self::assertSame(0, $connection->commitCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            self::assertSame(0, $connection->selectCalls);
            self::assertSame(0, $connection->advanceCalls);
            self::assertSame(0, $connection->terminalCalls);
            self::assertSame(0, $connection->failureCalls);
            self::assertSame($mode === 'after' ? 1 : 0, $connection->rollbackCalls);
            $this->assertLockAvailable($checkpoint);
        }
    }

    public function testRuntimeBeginCommitOutcomesDoNotSelectDeliverOrFinalize(): void
    {
        $this->insertRecords(3);
        foreach (['before', 'after'] as $mode) {
            $connection = $this->faultConnection();
            $observer = new ReplayOutcomeRecordingObserver();
            $runtime = $this->runtime($connection, $observer);
            $checkpoint = 'runtime-begin-commit-' . $mode;
            $connection->armCommitFaultAt(1, $mode);

            $failure = null;
            try {
                $runtime->replay($this->replayRequest($checkpoint, 3));
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(RuntimeException::class, $failure);
            self::assertSame('replay commit ' . $mode, $failure->getMessage());
            self::assertSame([], $observer->recordIds);

            $row = $this->checkpointRow($checkpoint);
            $audit = $this->auditRows($checkpoint);
            if ($mode === 'after') {
                self::assertSame('running', $row['state']);
                self::assertSame(0, (int) $row['selected_count']);
                self::assertSame(0, (int) $row['delivered_count']);
                self::assertNull($row['cursor_record_id']);
                self::assertNull($row['first_record_id']);
                self::assertNull($row['last_record_id']);
                self::assertCount(1, $audit);
                self::assertSame('started', $audit[0]['state']);
                self::assertSame(0, (int) $audit[0]['selected_count']);
                self::assertSame(0, (int) $audit[0]['delivered_count']);
                self::assertNull($audit[0]['first_record_id']);
                self::assertNull($audit[0]['last_record_id']);
            } else {
                self::assertFalse($row);
                self::assertSame([], $audit);
            }
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(1, $connection->beginCalls);
            self::assertSame(1, $connection->commitCalls);
            self::assertSame($mode === 'before' ? 1 : 0, $connection->rollbackCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            self::assertSame(0, $connection->selectCalls);
            self::assertSame(0, $connection->advanceCalls);
            self::assertSame(0, $connection->advanceAuditCalls);
            self::assertSame(0, $connection->terminalCalls);
            self::assertSame(0, $connection->terminalAuditCalls);
            self::assertSame(0, $connection->failureCalls);
            self::assertSame(0, $connection->failureAuditCalls);
            $this->assertLockAvailable($checkpoint);
        }
    }

    public function testRuntimeTerminalCommitOutcomesUseOneFinalizationAndRelease(): void
    {
        $this->insertRecords(3);
        foreach (['before', 'after'] as $mode) {
            $connection = $this->faultConnection();
            $observer = new ReplayOutcomeRecordingObserver();
            $runtime = $this->runtime($connection, $observer);
            $checkpoint = 'runtime-terminal-' . $mode;
            $connection->armCommitFaultAt(5, $mode);

            $failure = null;
            try {
                $runtime->replay($this->replayRequest($checkpoint, 3));
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(RuntimeException::class, $failure);
            self::assertSame('replay commit ' . $mode, $failure->getMessage());
            self::assertSame([self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE], $observer->recordIds);
            $row = $this->checkpointRow($checkpoint);
            $audit = $this->auditRows($checkpoint);
            self::assertCount(1, $audit);
            self::assertSame($mode === 'after' ? 'complete' : 'running', $row['state']);
            self::assertSame($mode === 'after' ? 'complete' : 'started', $audit[0]['state']);
            self::assertSame(3, (int) $row['delivered_count']);
            self::assertSame(3, (int) $audit[0]['delivered_count']);
            self::assertSame(self::RECORD_ONE, $row['first_record_id']);
            self::assertSame(self::RECORD_THREE, $row['last_record_id']);
            self::assertSame(self::RECORD_ONE, $audit[0]['first_record_id']);
            self::assertSame(self::RECORD_THREE, $audit[0]['last_record_id']);
            self::assertSame(5, $connection->beginCalls);
            self::assertSame(5, $connection->commitCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->selectCalls);
            self::assertSame(3, $connection->advanceCalls);
            self::assertSame(3, $connection->advanceAuditCalls);
            self::assertSame(1, $connection->terminalCalls);
            self::assertSame(1, $connection->terminalAuditCalls);
            self::assertSame(0, $connection->failureCalls);
            self::assertSame(0, $connection->failureAuditCalls);
            self::assertSame($mode === 'before' ? 1 : 0, $connection->rollbackCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            $this->assertLockAvailable($checkpoint);
        }
    }

    public function testRuntimePausedTerminalCommitOutcomesUseOneFinalizationAndRelease(): void
    {
        $this->insertRecords(3);
        foreach (['before', 'after'] as $mode) {
            $connection = $this->faultConnection();
            $observer = new ReplayOutcomeRecordingObserver();
            $runtime = $this->runtime($connection, $observer);
            $checkpoint = 'runtime-paused-terminal-' . $mode;
            $connection->armCommitFaultAt(4, $mode);

            $failure = null;
            try {
                $runtime->replay($this->replayRequest($checkpoint, 2));
            } catch (RuntimeException $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(RuntimeException::class, $failure);
            self::assertSame('replay commit ' . $mode, $failure->getMessage());
            self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $observer->recordIds);

            $row = $this->checkpointRow($checkpoint);
            $audit = $this->auditRows($checkpoint);
            self::assertCount(1, $audit);
            self::assertSame($mode === 'after' ? 'paused' : 'running', $row['state']);
            self::assertSame($mode === 'after' ? 'complete' : 'started', $audit[0]['state']);
            self::assertSame('2|' . self::RECORD_TWO, $row['cursor_record_id']);
            self::assertSame(2, (int) $row['selected_count']);
            self::assertSame(2, (int) $row['delivered_count']);
            self::assertSame(self::RECORD_ONE, $row['first_record_id']);
            self::assertSame(self::RECORD_TWO, $row['last_record_id']);
            self::assertSame(2, (int) $audit[0]['selected_count']);
            self::assertSame(2, (int) $audit[0]['delivered_count']);
            self::assertSame(self::RECORD_ONE, $audit[0]['first_record_id']);
            self::assertSame(self::RECORD_TWO, $audit[0]['last_record_id']);
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(4, $connection->beginCalls);
            self::assertSame(4, $connection->commitCalls);
            self::assertSame(2, $connection->advanceCalls);
            self::assertSame(2, $connection->advanceAuditCalls);
            self::assertSame(1, $connection->terminalCalls);
            self::assertSame(1, $connection->terminalAuditCalls);
            self::assertSame(0, $connection->failureCalls);
            self::assertSame(0, $connection->failureAuditCalls);
            self::assertSame($mode === 'before' ? 1 : 0, $connection->rollbackCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            self::assertSame(1, $connection->selectCalls);
            $this->assertLockAvailable($checkpoint);
        }
    }

    public function testRuntimeObserverFailureCommitOutcomesDoNotFinalize(): void
    {
        $this->insertRecords(3);
        foreach (['before', 'after'] as $mode) {
            $connection = $this->faultConnection();
            $observer = new ReplayOutcomeFailingObserver(1);
            $runtime = $this->runtime($connection, $observer);
            $checkpoint = 'runtime-failure-' . $mode;
            $connection->armCommitFaultAt(2, $mode);

            $failure = null;
            try {
                $runtime->replay($this->replayRequest($checkpoint, 3));
            } catch (JournalObservationFailed $exception) {
                $failure = $exception;
            }
            self::assertInstanceOf(JournalObservationFailed::class, $failure);
            self::assertSame('Observer replay delivery failed.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
            $row = $this->checkpointRow($checkpoint);
            $audit = $this->auditRows($checkpoint);
            self::assertSame($mode === 'after' ? 'failed' : 'running', $row['state']);
            self::assertSame($mode === 'after' ? 'failed' : 'started', $audit[0]['state']);
            self::assertNull($row['cursor_record_id']);
            self::assertSame(0, (int) $row['selected_count']);
            self::assertSame(0, (int) $row['delivered_count']);
            self::assertSame(0, (int) $audit[0]['selected_count']);
            self::assertSame(0, (int) $audit[0]['delivered_count']);
            self::assertNull($row['first_record_id']);
            self::assertNull($row['last_record_id']);
            self::assertNull($audit[0]['first_record_id']);
            self::assertNull($audit[0]['last_record_id']);
            self::assertSame([self::RECORD_ONE], $observer->recordIds);
            self::assertSame(1, $connection->failureCalls);
            self::assertSame(1, $connection->failureAuditCalls);
            self::assertSame(2, $connection->beginCalls);
            self::assertSame(2, $connection->commitCalls);
            self::assertSame(1, $connection->lockAcquireCalls);
            self::assertSame(1, $connection->selectCalls);
            self::assertSame(1, $connection->lockReleaseCalls);
            self::assertSame(0, $connection->advanceAuditCalls);
            self::assertSame(0, $connection->terminalAuditCalls);
            self::assertSame(0, $connection->terminalCalls);
            self::assertSame(0, $connection->advanceCalls);
            self::assertSame($mode === 'before' ? 1 : 0, $connection->rollbackCalls);
            $this->assertLockAvailable($checkpoint);
        }
    }

    public function testDirectMutationCommitOutcomesCoverAdvancePauseCompleteAndFailure(): void
    {
        $operations = [
            'advance' => function (
                PostgreSqlObserverReplayStore $store,
                PostgreSqlObserverReplayBinding $binding,
                string $checkpoint,
            ): void {
                $store->advance($checkpoint, '1|' . self::RECORD_ONE, 1, $binding->auditId);
            },
            'paused' => function (
                PostgreSqlObserverReplayStore $store,
                PostgreSqlObserverReplayBinding $binding,
                string $checkpoint,
            ): void {
                $store->finishInvocation($checkpoint, 'paused', auditId: $binding->auditId);
            },
            'complete' => function (
                PostgreSqlObserverReplayStore $store,
                PostgreSqlObserverReplayBinding $binding,
                string $checkpoint,
            ): void {
                $store->finishInvocation($checkpoint, 'complete', auditId: $binding->auditId);
            },
            'fail' => function (
                PostgreSqlObserverReplayStore $store,
                PostgreSqlObserverReplayBinding $binding,
                string $checkpoint,
            ): void {
                $store->fail($checkpoint, new RuntimeException('observer failure'), auditId: $binding->auditId);
            },
        ];

        foreach ($operations as $name => $operation) {
            foreach (['before', 'after'] as $mode) {
                $connection = $this->faultConnection();
                $checkpoint = 'mutation-' . $name . '-' . $mode;
                $store = $this->store($connection);
                $binding = $store->begin($this->request($checkpoint));
                $connection->armCommitFault($mode);

                $failure = null;
                try {
                    $operation($store, $binding, $checkpoint);
                } catch (RuntimeException $exception) {
                    $failure = $exception;
                }
                self::assertInstanceOf(RuntimeException::class, $failure);

                $row = $this->checkpointRow($checkpoint);
                $audit = $this->auditRows($checkpoint);
                if ($mode === 'after') {
                    $expected = $name === 'advance' ? 'running' : ($name === 'fail' ? 'failed' : $name);
                    self::assertSame($expected, $row['state']);
                    self::assertSame($name === 'advance' ? 1 : 0, (int) $row['delivered_count']);
                    self::assertSame(
                        $name === 'advance' ? 'started' : ($name === 'fail' ? 'failed' : 'complete'),
                        $audit[0]['state'],
                    );
                } else {
                    self::assertSame('running', $row['state']);
                    self::assertSame(0, (int) $row['delivered_count']);
                    self::assertSame('started', $audit[0]['state']);
                }
                self::assertFalse($connection->isTransactionActive());
                $store->unlock($checkpoint);
                $this->assertLockAvailable($checkpoint);
            }
        }
    }

    public function testObserverFailurePersistsFailureWithoutChangingCanonicalBytes(): void
    {
        $this->insertRecords(2);
        $before = $this->journalSnapshot();
        $observer = new ReplayOutcomeFailingObserver(1);
        $runtime = $this->runtime($this->connection, $observer);

        $failure = null;
        try {
            $runtime->replay($this->replayRequest('observer-failure'));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow('observer-failure');
        self::assertSame('failed', $row['state']);
        self::assertNull($row['cursor_record_id']);
        self::assertSame('failed', $this->auditRows('observer-failure')[0]['state']);
        self::assertSame($before, $this->journalSnapshot());
        self::assertSame([self::RECORD_ONE], $observer->recordIds);
    }

    public function testRuntimeSelectionDecryptFailurePersistsOneFailedOutcome(): void
    {
        $this->insertRecords(2);
        $this->connection->executeStatement(
            'UPDATE "' . self::SCHEMA . '"."journal" SET encoded_record=:encoded WHERE record_id=:record',
            [
                'encoded' => PostgreSqlTestStorageProtection::journalEnvelope(
                    '{}',
                    self::RECORD_ONE,
                    self::OPERATION,
                    'order.create',
                ),
                'record' => self::RECORD_ONE,
            ],
            ['encoded' => \Doctrine\DBAL\ParameterType::BINARY],
        );
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);

        $failure = null;
        try {
            $runtime->replay($this->replayRequest('selection-decrypt-failure'));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        $row = $this->checkpointRow('selection-decrypt-failure');
        $audit = $this->auditRows('selection-decrypt-failure')[0];
        self::assertSame('failed', $row['state']);
        self::assertSame('failed', $audit['state']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        self::assertSame(0, (int) $audit['selected_count']);
        self::assertSame(0, (int) $audit['delivered_count']);
        self::assertSame([], $observer->recordIds);
        self::assertSame(1, $connection->selectCalls);
        self::assertSame(1, $connection->failureCalls);
        self::assertSame(1, $connection->failureAuditCalls);
        self::assertSame(0, $connection->advanceCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame($before, $this->journalSnapshot());
        $this->assertLockAvailable('selection-decrypt-failure');
    }

    public function testRuntimeProjectionFailurePersistsFailureWithoutAdvance(): void
    {
        $record = $this->records()[0];
        $record = new JournalRecord(
            $record->recordId,
            $record->schemaVersion,
            $record->event,
            $record->occurredAt,
            $record->sequence,
            $record->operation,
            $record->attempt,
            new OperationReceivedData(new ReplayOutcomeHashValue('projection-secret')),
        );
        $this->insertJournalRecords([$record]);
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);

        $failure = null;
        try {
            $runtime->replay($this->replayRequest('projection-failure'));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        $row = $this->checkpointRow('projection-failure');
        $audit = $this->auditRows('projection-failure')[0];
        self::assertSame('failed', $row['state']);
        self::assertSame('failed', $audit['state']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        self::assertSame(0, (int) $audit['selected_count']);
        self::assertSame(0, (int) $audit['delivered_count']);
        self::assertSame([], $observer->recordIds);
        self::assertSame(1, $connection->failureCalls);
        self::assertSame(1, $connection->failureAuditCalls);
        self::assertSame(0, $connection->advanceCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        $this->assertLockAvailable('projection-failure');
    }

    public function testRuntimeFlushFailurePersistsFailureWithoutLaterDelivery(): void
    {
        $this->insertRecords(3);
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeFlushFailingObserver();
        $runtime = $this->runtime($connection, $observer);

        $failure = null;
        try {
            $runtime->replay($this->replayRequest('flush-failure', 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        $row = $this->checkpointRow('flush-failure');
        $audit = $this->auditRows('flush-failure')[0];
        self::assertSame('failed', $row['state']);
        self::assertNull($row['cursor_record_id']);
        self::assertSame('failed', $audit['state']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        self::assertSame(0, (int) $audit['selected_count']);
        self::assertSame(0, (int) $audit['delivered_count']);
        self::assertSame([self::RECORD_ONE], $observer->recordIds);
        self::assertSame(1, $connection->failureCalls);
        self::assertSame(1, $connection->failureAuditCalls);
        self::assertSame(0, $connection->advanceCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        $this->assertLockAvailable('flush-failure');
    }

    public function testObserverWithCursorMessageStillUsesDeliveryFailurePath(): void
    {
        $this->insertRecords(2);
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeCursorMessageObserver();
        $runtime = $this->runtime($connection, $observer);

        $failure = null;
        try {
            $runtime->replay($this->replayRequest('same-cursor-message', 2));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay delivery failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        $row = $this->checkpointRow('same-cursor-message');
        $audit = $this->auditRows('same-cursor-message')[0];
        self::assertSame('failed', $row['state']);
        self::assertSame('failed', $audit['state']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        self::assertSame(0, (int) $audit['selected_count']);
        self::assertSame(0, (int) $audit['delivered_count']);
        self::assertSame(1, $connection->failureCalls);
        self::assertSame(1, $connection->failureAuditCalls);
        self::assertSame(0, $connection->advanceCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        $this->assertLockAvailable('same-cursor-message');
    }

    public function testAdvanceSqlFailureLeavesRunningCheckpointAndExplicitResumeRedelivers(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'advance-sql-failure';
        $connection->armStatementFault('UPDATE "' . self::SCHEMA . '"."observer_replay_checkpoints"', 'before');

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        self::assertSame('running', $row['state']);
        self::assertNull($row['cursor_record_id']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        self::assertNull($row['first_record_id']);
        self::assertNull($row['last_record_id']);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(0, (int) $oldAudit['selected_count']);
        self::assertSame(0, (int) $oldAudit['delivered_count']);
        self::assertNull($oldAudit['first_record_id']);
        self::assertNull($oldAudit['last_record_id']);
        self::assertSame([self::RECORD_ONE], $observer->recordIds);
        self::assertSame(2, $connection->beginCalls);
        self::assertSame(1, $connection->commitCalls);
        self::assertSame(1, $connection->advanceCalls);
        self::assertSame(0, $connection->advanceAuditCalls);
        self::assertSame(1, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame(1, $connection->selectCalls);
        self::assertSame(0, $connection->failureCalls);
        self::assertSame(0, $connection->terminalCalls);
        $this->assertLockAvailable($checkpoint);

        $connection->disarm();
        $result = $runtime->resume($checkpoint, 'operator', 'resume', 3);
        self::assertSame(3, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame(
            [self::RECORD_ONE, self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE],
            $observer->recordIds,
        );
        $audits = $this->auditRows($checkpoint);
        self::assertCount(2, $audits);
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        $newAudit = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] !== $oldAudit['audit_id'],
        ))[0];
        self::assertNotSame($oldAudit['audit_id'], $newAudit['audit_id']);
        self::assertSame('complete', $newAudit['state']);
        self::assertSame(3, (int) $newAudit['selected_count']);
        self::assertSame(3, (int) $newAudit['delivered_count']);
        self::assertSame(self::RECORD_ONE, $newAudit['first_record_id']);
        self::assertSame(self::RECORD_THREE, $newAudit['last_record_id']);
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame('complete', $finalRow['state']);
        self::assertSame('3|' . self::RECORD_THREE, $finalRow['cursor_record_id']);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
        self::assertSame($before, $this->journalSnapshot());
    }

    public function testAdvanceCommitBeforeEffectLeavesPreviousRootAndResumeRedelivers(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'advance-commit-before';
        $connection->armCommitFaultAt(2, 'before');

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint, 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        self::assertSame('running', $row['state']);
        self::assertNull($row['cursor_record_id']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(0, (int) $oldAudit['selected_count']);
        self::assertSame(0, (int) $oldAudit['delivered_count']);
        self::assertNull($row['first_record_id']);
        self::assertNull($row['last_record_id']);
        self::assertNull($oldAudit['first_record_id']);
        self::assertNull($oldAudit['last_record_id']);
        self::assertSame([self::RECORD_ONE], $observer->recordIds);
        self::assertSame(2, $connection->beginCalls);
        self::assertSame(2, $connection->commitCalls);
        self::assertSame(1, $connection->advanceCalls);
        self::assertSame(1, $connection->advanceAuditCalls);
        self::assertSame(1, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        $connection->disarm();

        $result = $runtime->resume($checkpoint, 'operator', 'resume', 3);
        self::assertSame(3, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame(
            [self::RECORD_ONE, self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE],
            $observer->recordIds,
        );
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        self::assertCount(2, $this->auditRows($checkpoint));
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
        self::assertSame($before, $this->journalSnapshot());
    }

    public function testFirstAdvanceCommitAfterEffectStopsBeforeLaterRecords(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'first-advance-commit-after';
        $connection->armCommitFaultAt(2, 'after');

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint, 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('running', $row['state']);
        self::assertSame('1|' . self::RECORD_ONE, $row['cursor_record_id']);
        self::assertSame(1, (int) $row['selected_count']);
        self::assertSame(1, (int) $row['delivered_count']);
        self::assertSame(self::RECORD_ONE, $row['first_record_id']);
        self::assertSame(self::RECORD_ONE, $row['last_record_id']);
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(1, (int) $oldAudit['selected_count']);
        self::assertSame(1, (int) $oldAudit['delivered_count']);
        self::assertSame(self::RECORD_ONE, $oldAudit['first_record_id']);
        self::assertSame(self::RECORD_ONE, $oldAudit['last_record_id']);
        self::assertSame([self::RECORD_ONE], $observer->recordIds);
        self::assertSame(2, $connection->beginCalls);
        self::assertSame(2, $connection->commitCalls);
        self::assertSame(1, $connection->advanceCalls);
        self::assertSame(1, $connection->advanceAuditCalls);
        self::assertSame(0, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame(0, $connection->failureCalls);
        self::assertSame(0, $connection->terminalCalls);
        self::assertSame($before, $this->journalSnapshot());

        $connection->disarm();
        $result = $runtime->resume($checkpoint, 'operator', 'resume', 3);
        self::assertSame(2, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE], $observer->recordIds);
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        $audits = $this->auditRows($checkpoint);
        self::assertCount(2, $audits);
        $newAudit = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] !== $oldAudit['audit_id'],
        ))[0];
        self::assertSame('complete', $newAudit['state']);
        self::assertSame(2, (int) $newAudit['selected_count']);
        self::assertSame(2, (int) $newAudit['delivered_count']);
        self::assertSame(self::RECORD_TWO, $newAudit['first_record_id']);
        self::assertSame(self::RECORD_THREE, $newAudit['last_record_id']);
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame('complete', $finalRow['state']);
        self::assertSame('3|' . self::RECORD_THREE, $finalRow['cursor_record_id']);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
        self::assertSame($before, $this->journalSnapshot());
    }

    public function testAdvanceCommitAfterEffectPreservesProgressWithoutSyntheticFailure(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'advance-commit-after';
        $connection->armCommitFaultAt(3, 'after');

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint, 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        self::assertSame('running', $row['state']);
        self::assertSame('2|' . self::RECORD_TWO, $row['cursor_record_id']);
        self::assertSame(2, (int) $row['delivered_count']);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(2, (int) $oldAudit['selected_count']);
        self::assertSame(2, (int) $oldAudit['delivered_count']);
        self::assertSame(self::RECORD_ONE, $row['first_record_id']);
        self::assertSame(self::RECORD_TWO, $row['last_record_id']);
        self::assertSame(self::RECORD_ONE, $oldAudit['first_record_id']);
        self::assertSame(self::RECORD_TWO, $oldAudit['last_record_id']);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $observer->recordIds);
        self::assertSame(3, $connection->beginCalls);
        self::assertSame(3, $connection->commitCalls);
        self::assertSame(2, $connection->advanceCalls);
        self::assertSame(2, $connection->advanceAuditCalls);
        self::assertSame(0, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame($before, $this->journalSnapshot());
        $this->assertLockAvailable($checkpoint);

        $connection->disarm();
        $result = $runtime->resume($checkpoint, 'operator', 'resume');
        self::assertSame(1, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE], $observer->recordIds);
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        self::assertCount(2, $this->auditRows($checkpoint));
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame('complete', $finalRow['state']);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
    }

    public function testAdvanceAuditSqlFailureRollsBackPartialCheckpointAndResumesAll(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'advance-audit-sql-failure';
        $connection->armStatementFault('UPDATE "' . self::SCHEMA . '"."observer_replay_audits"', 'before');

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint, 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('running', $row['state']);
        self::assertNull($row['cursor_record_id']);
        self::assertSame(0, (int) $row['selected_count']);
        self::assertSame(0, (int) $oldAudit['selected_count']);
        self::assertSame(0, (int) $row['delivered_count']);
        self::assertSame(0, (int) $oldAudit['delivered_count']);
        self::assertNull($row['first_record_id']);
        self::assertNull($row['last_record_id']);
        self::assertNull($oldAudit['first_record_id']);
        self::assertNull($oldAudit['last_record_id']);
        self::assertSame(1, $connection->advanceCalls);
        self::assertSame(1, $connection->advanceAuditCalls);
        self::assertSame(1, $connection->rollbackCalls);
        self::assertSame(2, $connection->beginCalls);
        self::assertSame(1, $connection->commitCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame(0, $connection->failureCalls);
        self::assertSame(0, $connection->terminalCalls);
        self::assertSame([self::RECORD_ONE], $observer->recordIds);
        self::assertSame($before, $this->journalSnapshot());

        $connection->disarm();
        $result = $runtime->resume($checkpoint, 'operator', 'resume', 3);
        self::assertSame(3, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame(
            [self::RECORD_ONE, self::RECORD_ONE, self::RECORD_TWO, self::RECORD_THREE],
            $observer->recordIds,
        );
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        self::assertCount(2, $this->auditRows($checkpoint));
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
        self::assertSame($before, $this->journalSnapshot());
    }

    public function testLaterAdvanceSqlFailurePreservesEarlierCursorAndLaterSentinel(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'later-advance-sql-failure';
        $connection->armStatementFaultAt('UPDATE "' . self::SCHEMA . '"."observer_replay_checkpoints"', 'before', 2);

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint, 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('running', $row['state']);
        self::assertSame('1|' . self::RECORD_ONE, $row['cursor_record_id']);
        self::assertSame(1, (int) $row['delivered_count']);
        self::assertSame(1, (int) $oldAudit['delivered_count']);
        self::assertSame(self::RECORD_ONE, $row['first_record_id']);
        self::assertSame(self::RECORD_ONE, $row['last_record_id']);
        self::assertSame(self::RECORD_ONE, $oldAudit['first_record_id']);
        self::assertSame(self::RECORD_ONE, $oldAudit['last_record_id']);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $observer->recordIds);
        self::assertSame(2, $connection->advanceCalls);
        self::assertSame(1, $connection->advanceAuditCalls);
        self::assertSame(3, $connection->beginCalls);
        self::assertSame(2, $connection->commitCalls);
        self::assertSame(1, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame(0, $connection->failureCalls);
        self::assertSame(0, $connection->terminalCalls);

        $connection->disarm();
        $result = $runtime->resume($checkpoint, 'operator', 'resume', 3);
        self::assertSame(2, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame(
            [self::RECORD_ONE, self::RECORD_TWO, self::RECORD_TWO, self::RECORD_THREE],
            $observer->recordIds,
        );
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        $audits = $this->auditRows($checkpoint);
        self::assertCount(2, $audits);
        $newAudit = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] !== $oldAudit['audit_id'],
        ))[0];
        self::assertNotSame($oldAudit['audit_id'], $newAudit['audit_id']);
        self::assertSame(2, (int) $newAudit['selected_count']);
        self::assertSame(2, (int) $newAudit['delivered_count']);
        self::assertSame(self::RECORD_TWO, $newAudit['first_record_id']);
        self::assertSame(self::RECORD_THREE, $newAudit['last_record_id']);
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
        self::assertSame($before, $this->journalSnapshot());
    }

    public function testLaterAdvanceCommitBeforePreservesEarlierCursorAndLaterSentinel(): void
    {
        $this->insertRecords(3);
        $before = $this->journalSnapshot();
        $connection = $this->faultConnection();
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = $this->runtime($connection, $observer);
        $checkpoint = 'later-advance-commit-before';
        $connection->armCommitFaultAt(3, 'before');

        $failure = null;
        try {
            $runtime->replay($this->replayRequest($checkpoint, 3));
        } catch (JournalObservationFailed $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(JournalObservationFailed::class, $failure);
        self::assertSame('Observer replay cursor persistence failed.', $failure->getMessage());
        self::assertNull($failure->getPrevious());

        $row = $this->checkpointRow($checkpoint);
        $oldAudit = $this->auditRows($checkpoint)[0];
        self::assertSame('running', $row['state']);
        self::assertSame('1|' . self::RECORD_ONE, $row['cursor_record_id']);
        self::assertSame(1, (int) $row['selected_count']);
        self::assertSame(1, (int) $row['delivered_count']);
        self::assertSame(self::RECORD_ONE, $row['first_record_id']);
        self::assertSame(self::RECORD_ONE, $row['last_record_id']);
        self::assertSame('started', $oldAudit['state']);
        self::assertSame(1, (int) $oldAudit['selected_count']);
        self::assertSame(1, (int) $oldAudit['delivered_count']);
        self::assertSame(self::RECORD_ONE, $oldAudit['first_record_id']);
        self::assertSame(self::RECORD_ONE, $oldAudit['last_record_id']);
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $observer->recordIds);
        self::assertSame(3, $connection->beginCalls);
        self::assertSame(3, $connection->commitCalls);
        self::assertSame(2, $connection->advanceCalls);
        self::assertSame(2, $connection->advanceAuditCalls);
        self::assertSame(1, $connection->rollbackCalls);
        self::assertSame(1, $connection->lockAcquireCalls);
        self::assertSame(1, $connection->lockReleaseCalls);
        self::assertSame(0, $connection->failureCalls);
        self::assertSame(0, $connection->terminalCalls);
        self::assertSame($before, $this->journalSnapshot());

        $connection->disarm();
        $result = $runtime->resume($checkpoint, 'operator', 'resume', 3);
        self::assertSame(2, $result->delivered);
        self::assertTrue($result->complete);
        self::assertSame(
            [self::RECORD_ONE, self::RECORD_TWO, self::RECORD_TWO, self::RECORD_THREE],
            $observer->recordIds,
        );
        self::assertSame($oldAudit, $this->auditRow($oldAudit['audit_id']));
        $audits = $this->auditRows($checkpoint);
        self::assertCount(2, $audits);
        $newAudit = array_values(array_filter(
            $audits,
            static fn(array $audit): bool => $audit['audit_id'] !== $oldAudit['audit_id'],
        ))[0];
        self::assertSame('complete', $newAudit['state']);
        self::assertSame(2, (int) $newAudit['selected_count']);
        self::assertSame(2, (int) $newAudit['delivered_count']);
        self::assertSame(self::RECORD_TWO, $newAudit['first_record_id']);
        self::assertSame(self::RECORD_THREE, $newAudit['last_record_id']);
        $finalRow = $this->checkpointRow($checkpoint);
        self::assertSame('complete', $finalRow['state']);
        self::assertSame('3|' . self::RECORD_THREE, $finalRow['cursor_record_id']);
        self::assertSame(3, (int) $finalRow['selected_count']);
        self::assertSame(3, (int) $finalRow['delivered_count']);
        self::assertSame(self::RECORD_ONE, $finalRow['first_record_id']);
        self::assertSame(self::RECORD_THREE, $finalRow['last_record_id']);
        self::assertSame($before, $this->journalSnapshot());
    }

    public function testPausedAndCompleteFinalizationPersistThroughResume(): void
    {
        $this->insertRecords(2);
        $observer = new ReplayOutcomeRecordingObserver();
        $runtime = new ObserverReplayRuntime(
            $this->store($this->connection),
            new ObserverReplayTargetRegistry([new JournalObserverBinding('recording', $observer)]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            1,
        );

        $paused = $runtime->replay($this->replayRequest('paused-checkpoint', batchSize: 1));
        self::assertTrue($paused->hasMore);
        self::assertFalse($paused->complete);
        self::assertSame('paused', $this->checkpointRow('paused-checkpoint')['state']);
        self::assertSame('complete', $this->auditRows('paused-checkpoint')[0]['state']);

        $complete = $runtime->resume('paused-checkpoint', 'operator', 'resume', 1);
        self::assertFalse($complete->hasMore);
        self::assertTrue($complete->complete);
        self::assertSame('complete', $this->checkpointRow('paused-checkpoint')['state']);
        self::assertCount(2, $this->auditRows('paused-checkpoint'));
        self::assertSame([self::RECORD_ONE, self::RECORD_TWO], $observer->recordIds);
    }

    public function testDryRunLeavesCheckpointAuditAndCanonicalBytesUntouched(): void
    {
        $this->insertRecords(2);
        $before = $this->journalSnapshot();
        $runtime = $this->runtime($this->connection, new ReplayOutcomeRecordingObserver());

        $result = $runtime->dryRun($this->selector(), ['recording'], 1);

        self::assertSame(1, $result->selected);
        self::assertTrue($result->hasMore);
        self::assertSame([], $this->auditRows('dry-run'));
        self::assertFalse($this->checkpointRow('dry-run'));
        self::assertSame($before, $this->journalSnapshot());
    }

    private function assertMutationRejected(callable $method, PostgreSqlObserverReplayStore $store, string $label): void
    {
        $failure = null;
        try {
            $method($store);
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        self::assertInstanceOf(RuntimeException::class, $failure, $label);
        self::assertSame('Replay mutation requires an idle auto-commit connection.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
    }

    private function realConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST') ?: 'postgres',
            'port' => getenv('POSTGRES_PORT') ?: 5432,
            'dbname' => getenv('POSTGRES_DB') ?: 'blackops',
            'user' => getenv('POSTGRES_USER') ?: 'blackops',
            'password' => getenv('POSTGRES_PASSWORD') ?: 'blackops',
        ]);
    }

    private function faultConnection(): ReplayOutcomeFaultConnection
    {
        $connection = new ReplayOutcomeFaultConnection(
            $this->connection->getParams(),
            $this->connection->getDriver(),
            self::SCHEMA,
        );
        $this->connections[] = $connection;

        return $connection;
    }

    private function store(Connection $connection): PostgreSqlObserverReplayStore
    {
        return new PostgreSqlObserverReplayStore($connection, PostgreSqlTestStorageProtection::codec(), self::SCHEMA);
    }

    private function selector(): PostgreSqlObserverReplaySelector
    {
        return PostgreSqlObserverReplaySelector::operation(OperationId::fromString(self::OPERATION));
    }

    private function request(string $checkpoint): PostgreSqlObserverReplayBeginRequest
    {
        return new PostgreSqlObserverReplayBeginRequest(
            $checkpoint,
            $this->selector(),
            ['recording'],
            'operator',
            'transaction-outcome-test',
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
        );
    }

    private function replayRequest(string $checkpoint, ?int $batchSize = null): ObserverReplayRequest
    {
        return new ObserverReplayRequest(
            $this->selector(),
            ['recording'],
            $checkpoint,
            'operator',
            'transaction-outcome-test',
            new DateTimeImmutable('2026-09-29T12:00:00Z'),
            $batchSize,
        );
    }

    private function runtime(Connection $connection, JournalObserver $observer): ObserverReplayRuntime
    {
        return new ObserverReplayRuntime(
            $this->store($connection),
            new ObserverReplayTargetRegistry([new JournalObserverBinding('recording', $observer)]),
            new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
            2,
        );
    }

    private function insertRecords(int $count): void
    {
        $this->insertJournalRecords(array_slice($this->records(), 0, $count));
    }

    /** @param list<JournalRecord> $records */
    private function insertJournalRecords(array $records): void
    {
        foreach ($records as $record) {
            $this->connection->executeStatement(
                'INSERT INTO "'
                . self::SCHEMA
                . '"."journal" (record_id, operation_id, operation_type, sequence, event, schema_version, operation_schema_version, occurred_at, encoded_record) VALUES (:record,:operation,\'order.create\',:sequence,:event,1,1,:at,:encoded)',
                [
                    'record' => $record->recordId->toString(),
                    'operation' => $record->operation->id->toString(),
                    'sequence' => $record->sequence,
                    'event' => 'operation.received',
                    'at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
                    'encoded' => PostgreSqlTestStorageProtection::journalRecordEnvelope($record),
                ],
                ['encoded' => \Doctrine\DBAL\ParameterType::BINARY],
            );
        }
    }

    /** @return list<JournalRecord> */
    private function records(): array
    {
        $operation = OperationId::fromString(self::OPERATION);
        return [
            new JournalRecord(
                JournalRecordId::fromString(self::RECORD_ONE),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-09-29T12:00:01Z'),
                1,
                new JournalOperation(
                    $operation,
                    'order.create',
                    1,
                    'inline',
                    CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                ),
                null,
                new EmptyJournalData(),
            ),
            new JournalRecord(
                JournalRecordId::fromString(self::RECORD_TWO),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-09-29T12:00:02Z'),
                2,
                new JournalOperation(
                    $operation,
                    'order.create',
                    1,
                    'inline',
                    CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                ),
                null,
                new EmptyJournalData(),
            ),
            new JournalRecord(
                JournalRecordId::fromString(self::RECORD_THREE),
                1,
                JournalEvent::OperationReceived,
                new DateTimeImmutable('2026-09-29T12:00:03Z'),
                3,
                new JournalOperation(
                    $operation,
                    'order.create',
                    1,
                    'inline',
                    CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687699'),
                ),
                null,
                new EmptyJournalData(),
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function journalSnapshot(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT record_id, encode(encoded_record, \'hex\') AS encoded FROM "'
            . self::SCHEMA
            . '"."journal" ORDER BY record_id',
        );
    }

    /** @return array<string, mixed>|false */
    private function checkpointRow(string $checkpoint): array|false
    {
        return $this->connection->fetchAssociative('SELECT state, cursor_record_id, selected_count, delivered_count, first_record_id, last_record_id FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => $checkpoint]);
    }

    /** @return list<array<string, mixed>> */
    private function auditRows(string $checkpoint): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT audit_id, checkpoint_id, state, selector_hash, target_hash, actor, reason, target_names, started_at, finished_at, selected_count, delivered_count, first_record_id, last_record_id FROM "'
            . self::SCHEMA
            . '"."observer_replay_audits" WHERE checkpoint_id=:id ORDER BY started_at, audit_id',
            ['id' => $checkpoint],
        );
    }

    /** @return array<string, mixed>|false */
    private function auditRow(string $auditId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT audit_id, checkpoint_id, state, selector_hash, target_hash, actor, reason, target_names, started_at, finished_at, selected_count, delivered_count, first_record_id, last_record_id FROM "'
            . self::SCHEMA
            . '"."observer_replay_audits" WHERE audit_id=:id',
            ['id' => $auditId],
        );
    }

    private function checkpointCount(string $checkpoint): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM "'
        . self::SCHEMA
        . '"."observer_replay_checkpoints" WHERE checkpoint_id=:id', ['id' => $checkpoint]);
    }

    private function insertCallerSentinel(Connection $connection, int $id): void
    {
        $connection->executeStatement('INSERT INTO "' . self::SCHEMA . '"."caller_sentinel" (id) VALUES (:id)', [
            'id' => $id,
        ]);
    }

    private function callerSentinelCount(Connection $connection): int
    {
        return (int) $connection->fetchOne('SELECT count(*) FROM "' . self::SCHEMA . '"."caller_sentinel"');
    }

    private function auditCount(string $checkpoint): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM "'
        . self::SCHEMA
        . '"."observer_replay_audits" WHERE checkpoint_id=:id', ['id' => $checkpoint]);
    }

    private function assertLockAvailable(string $checkpoint): void
    {
        $locked = $this->connection->fetchOne('SELECT pg_try_advisory_lock(hashtext(:checkpoint))', [
            'checkpoint' => $checkpoint,
        ]);
        self::assertTrue($locked === true || $locked === 't' || $locked === 1 || $locked === '1');
        $this->connection->fetchOne('SELECT pg_advisory_unlock(hashtext(:checkpoint))', ['checkpoint' => $checkpoint]);
    }
}

/** @mago-expect lint:single-class-per-file */
final class ReplayOutcomeRecordingObserver implements JournalObserver
{
    /** @var list<string> */
    public array $recordIds = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->recordIds[] = $record->recordId->toString();
    }
}

/** @mago-expect lint:single-class-per-file */
final class ReplayOutcomeFailingObserver implements JournalObserver
{
    /** @var list<string> */
    public array $recordIds = [];

    public function __construct(
        private readonly int $failureSequence,
    ) {}

    public function observe(ObservedJournalRecord $record): void
    {
        $this->recordIds[] = $record->recordId->toString();
        if ($record->sequence === $this->failureSequence) {
            throw new JournalObservationFailed('transaction outcome observer failure');
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final class ReplayOutcomeFlushFailingObserver implements FlushableJournalObserver
{
    /** @var list<string> */
    public array $recordIds = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->recordIds[] = $record->recordId->toString();
    }

    public function flush(): void
    {
        throw new JournalObservationFailed('flush failure');
    }
}

/** @mago-expect lint:single-class-per-file */
final class ReplayOutcomeCursorMessageObserver implements JournalObserver
{
    public function observe(ObservedJournalRecord $record): void
    {
        throw new JournalObservationFailed(
            'Observer replay cursor persistence failed.',
            previous: new RuntimeException('observer message canary'),
        );
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class ReplayOutcomeHashValue implements OperationValue
{
    public function __construct(
        #[Sensitive(SensitiveMode::Hash)]
        public string $value,
    ) {}
}

/** @mago-expect lint:single-class-per-file */
final class ReplayOutcomeFaultConnection extends Connection
{
    public int $sqlCalls = 0;
    public int $beginCalls = 0;
    public int $commitCalls = 0;
    public int $rollbackCalls = 0;
    public int $lockAcquireCalls = 0;
    public int $lockReleaseCalls = 0;
    public int $selectCalls = 0;
    public int $advanceCalls = 0;
    public int $advanceAuditCalls = 0;
    public int $terminalCalls = 0;
    public int $terminalAuditCalls = 0;
    public int $failureCalls = 0;
    public int $failureAuditCalls = 0;

    private string $schema;
    private ?string $beginFault = null;
    private ?string $commitFault = null;
    private ?int $commitFaultAt = null;
    private ?string $statementNeedle = null;
    private ?string $statementFault = null;
    private ?int $statementFaultAt = null;
    private int $statementMatches = 0;
    private bool $rollbackFault = false;

    public function __construct(array $params, Driver $driver, string $schema)
    {
        parent::__construct($params, $driver);
        $this->schema = $schema;
    }

    public function resetCounters(): void
    {
        $this->sqlCalls = 0;
        $this->beginCalls = 0;
        $this->commitCalls = 0;
        $this->rollbackCalls = 0;
        $this->lockAcquireCalls = 0;
        $this->lockReleaseCalls = 0;
        $this->selectCalls = 0;
        $this->advanceCalls = 0;
        $this->advanceAuditCalls = 0;
        $this->terminalCalls = 0;
        $this->terminalAuditCalls = 0;
        $this->failureCalls = 0;
        $this->failureAuditCalls = 0;
    }

    public function armBeginFault(string $mode): void
    {
        $this->beginFault = $mode;
    }

    public function armCommitFault(string $mode): void
    {
        $this->armCommitFaultAt($this->commitCalls + 1, $mode);
    }

    public function armCommitFaultAt(int $commitNumber, string $mode): void
    {
        $this->commitFaultAt = $commitNumber;
        $this->commitFault = $mode;
    }

    public function armStatementFault(string $needle, string $mode): void
    {
        $this->armStatementFaultAt($needle, $mode, 1);
    }

    public function armStatementFaultAt(string $needle, string $mode, int $match): void
    {
        $this->statementNeedle = $needle;
        $this->statementFault = $mode;
        $this->statementFaultAt = $match;
        $this->statementMatches = 0;
    }

    public function armRollbackFault(): void
    {
        $this->rollbackFault = true;
    }

    public function disarmRollbackFault(): void
    {
        $this->rollbackFault = false;
    }

    public function disarm(): void
    {
        $this->beginFault = null;
        $this->commitFault = null;
        $this->commitFaultAt = null;
        $this->statementNeedle = null;
        $this->statementFault = null;
        $this->statementFaultAt = null;
        $this->statementMatches = 0;
        $this->rollbackFault = false;
    }

    public function beginTransaction(): void
    {
        ++$this->beginCalls;
        $fault = $this->beginFault;
        $this->beginFault = null;
        if ($fault === 'before') {
            throw new RuntimeException('replay begin transaction before');
        }

        parent::beginTransaction();
        if ($fault === 'after') {
            throw new RuntimeException('replay begin transaction after');
        }
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null,
    ): Result {
        ++$this->sqlCalls;
        if (str_contains($sql, 'pg_try_advisory_lock')) {
            ++$this->lockAcquireCalls;
        }
        if (str_contains($sql, 'pg_advisory_unlock')) {
            ++$this->lockReleaseCalls;
        }
        if (str_contains($sql, 'FROM "' . $this->schema . '"."journal"')) {
            ++$this->selectCalls;
        }

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        ++$this->sqlCalls;
        $this->recordStatementPhase($sql);
        $needle = $this->statementNeedle;
        $fault = $this->statementFault;
        $faultAt = $this->statementFaultAt;
        if ($needle !== null && $fault !== null && str_contains($sql, $needle)) {
            $match = ++$this->statementMatches;
            if ($faultAt !== $match) {
                return parent::executeStatement($sql, $params, $types);
            }
            $this->statementNeedle = null;
            $this->statementFault = null;
            $this->statementFaultAt = null;
            $this->statementMatches = 0;
            if ($fault === 'before') {
                throw new RuntimeException('replay statement before');
            }
            parent::executeStatement($sql, $params, $types);
            throw new RuntimeException('replay statement after');
        }

        return parent::executeStatement($sql, $params, $types);
    }

    public function commit(): void
    {
        $number = ++$this->commitCalls;
        $fault = $this->commitFaultAt === $number ? $this->commitFault : null;
        if ($fault !== null) {
            $this->commitFaultAt = null;
            $this->commitFault = null;
        }
        if ($fault === 'before') {
            throw new RuntimeException('replay commit before');
        }

        parent::commit();
        if ($fault === 'after') {
            throw new RuntimeException('replay commit after');
        }
    }

    public function rollBack(): void
    {
        ++$this->rollbackCalls;
        if ($this->rollbackFault) {
            $this->rollbackFault = false;
            throw new RuntimeException('replay rollback failure');
        }
        parent::rollBack();
    }

    private function recordStatementPhase(string $sql): void
    {
        $checkpoint = '"' . $this->schema . '"."observer_replay_checkpoints"';
        $audit = '"' . $this->schema . '"."observer_replay_audits"';
        if (str_contains($sql, 'UPDATE ' . $checkpoint)) {
            if (str_contains($sql, 'cursor_record_id')) {
                ++$this->advanceCalls;
            } elseif (str_contains($sql, 'SET state=:state')) {
                ++$this->terminalCalls;
            } elseif (str_contains($sql, "state='failed'")) {
                ++$this->failureCalls;
            }
        }
        if (str_contains($sql, 'UPDATE ' . $audit)) {
            if (str_contains($sql, 'selected_count')) {
                ++$this->advanceAuditCalls;
            } elseif (str_contains($sql, "state='complete'")) {
                ++$this->terminalAuditCalls;
            } elseif (str_contains($sql, "state='failed'")) {
                ++$this->failureAuditCalls;
            }
        }
    }
}
