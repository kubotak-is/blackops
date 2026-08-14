<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\OperationData\DefaultOperationJournalQuery;
use BlackOps\Internal\OperationData\DefaultOperationOutcomeQuery;
use BlackOps\Internal\OperationData\PostgreSqlOperationDataSubjectReader;
use BlackOps\Internal\OperationData\PostgreSqlTenantScopedCanonicalJournalReader;
use BlackOps\Internal\OperationData\PostgreSqlTenantScopedOutcomeReader;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationJournalUnavailable;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeUnavailable;
use BlackOps\Outcome\OutcomeRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutcomeStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class PostgreSqlOperationDataQueryIntegrationTest extends TestCase
{
    private const string SCHEMA = 'blackops_p16_004_operation_data';

    private Connection $connection;
    private OperationId $operationId;
    private TenantRef $tenant;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: '5432'),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $protection = PostgreSqlTestStorageProtection::codec();
        new PostgreSqlDeferredOperationSender($this->connection, $protection, self::SCHEMA)->migrate();
        new PostgreSqlCanonicalJournalStore($this->connection, $protection, self::SCHEMA)->migrate();
        new PostgreSqlOutcomeStore($this->connection, $protection, self::SCHEMA)->migrate();
        $this->operationId = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687901');
        $this->tenant = new TenantRef('customer', 'tenant-a');

        new PostgreSqlDeferredOperationSender($this->connection, $protection, self::SCHEMA)->enqueue(
            new \BlackOps\Core\Execution\DeferredOperationMessage(
                $this->operationId,
                'report.generate',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-08-08T00:00:00Z'),
                tenant: $this->tenant,
                originActor: new ActorRef('user-a', 'customer'),
            ),
        );
        $this->connection->executeStatement('UPDATE '
        . self::SCHEMA
        . '.operations SET state = \'completed\' WHERE operation_id = :id', ['id' => $this->operationId->toString()]);
        new PostgreSqlCanonicalJournalStore($this->connection, $protection, self::SCHEMA)->append($this->record());
        new PostgreSqlOutcomeStore($this->connection, $protection, self::SCHEMA)->save(
            new OutcomeRecord($this->operationId, new EmptyOutcome(), new DateTimeImmutable('2026-08-08T00:00:01Z')),
        );
    }

    public function testFoundAndCrossTenantUnavailableUseTenantScopedSubjectAndBlobQueries(): void
    {
        $subject = new PostgreSqlOperationDataSubjectReader(
            new \BlackOps\Transport\PostgreSql\PostgreSqlStatusReader($this->connection, self::SCHEMA),
        );
        $authorizer = new AllowOperationDataAuthorizer();
        $journal = new DefaultOperationJournalQuery(
            $subject,
            $authorizer,
            new PostgreSqlTenantScopedCanonicalJournalReader(
                $this->connection,
                PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
        );
        $outcome = new DefaultOperationOutcomeQuery(
            $subject,
            $authorizer,
            new PostgreSqlTenantScopedOutcomeReader(
                $this->connection,
                PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            ),
        );
        $purpose = OperationDataPurpose::fromString('application.view');

        self::assertInstanceOf(OperationJournalFound::class, $journal->records(
            $this->operationId,
            null,
            $this->tenant,
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeFound::class, $outcome->find(
            $this->operationId,
            null,
            $this->tenant,
            $purpose,
        ));
        self::assertInstanceOf(OperationJournalUnavailable::class, $journal->records(
            $this->operationId,
            null,
            new TenantRef('customer', 'tenant-b'),
            $purpose,
        ));
        self::assertInstanceOf(OperationOutcomeUnavailable::class, $outcome->find(
            $this->operationId,
            null,
            new TenantRef('customer', 'tenant-b'),
            $purpose,
        ));
        self::assertCount(2, $authorizer->requests);
    }

    public function testTamperedOutcomeEnvelopeIsClassifiedAsDecodeFailure(): void
    {
        $this->connection->executeStatement('UPDATE '
        . self::SCHEMA
        . '.outcomes SET encoded_payload = decode(\'424f5044\', \'hex\') WHERE operation_id = :id', [
            'id' => $this->operationId->toString(),
        ]);

        try {
            new PostgreSqlTenantScopedOutcomeReader(
                $this->connection,
                PostgreSqlTestStorageProtection::codec(),
                self::SCHEMA,
            )->findForTenant($this->operationId, $this->tenant);
            self::fail('Tampered outcome envelope must fail closed.');
        } catch (OperationOutcomeQueryException $exception) {
            self::assertSame(OperationOutcomeQueryException::DECODE_FAILED, $exception->queryCode());
        }
    }

    private function record(): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687902'),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-08-08T00:00:00Z'),
            1,
            new JournalOperation(
                $this->operationId,
                'report.generate',
                1,
                'deferred',
                CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687903'),
                actorContext: new ActorContext(
                    new ActorRef('user-a', 'customer'),
                    null,
                    new ActorRef('worker', 'system'),
                ),
                tenant: $this->tenant,
            ),
            null,
            new EmptyJournalData(),
        );
    }
}

final class AllowOperationDataAuthorizer implements OperationDataReadAuthorizer
{
    /** @var list<OperationDataReadAuthorizationRequest> */
    public array $requests = [];

    public function decide(OperationDataReadAuthorizationRequest $request): OperationDataReadAuthorizationDecision
    {
        $this->requests[] = $request;

        return OperationDataReadAuthorizationDecision::allow();
    }
}
