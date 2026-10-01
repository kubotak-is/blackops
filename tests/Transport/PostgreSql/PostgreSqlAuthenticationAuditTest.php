<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Auth\Session\BearerSessionAuthenticator;
use BlackOps\Auth\Session\RawSessionToken;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\TenantRef;
use BlackOps\Database\DatabaseManager;
use BlackOps\Http\Authentication\AuthenticationMiddleware;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Identifier\Uuidv7Generator;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\Authentication\AuditedHttpAuthenticator;
use BlackOps\Internal\Audit\Authentication\DurableAuthenticationAuditEventWriter;
use BlackOps\Internal\Audit\Catalog\FrameworkAuditEventCatalog;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryCoordinator;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Orchestration\AuditDeliverySelector;
use BlackOps\Internal\Audit\Outbox\AuditOutboxIdempotencyKeyHasher;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxRecordHydrator;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxSchema;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Protection\AuditAssociatedData;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionKeySource;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Auth\Session\DefaultSessionManager;
use BlackOps\Internal\Auth\Session\PostgreSqlSessionStore;
use BlackOps\Internal\Auth\Session\SessionClock;
use BlackOps\Internal\Auth\Session\SessionIdentifierGenerator;
use BlackOps\Internal\Auth\Session\SessionTokenGenerator;
use BlackOps\Internal\Identifier\Uuidv7Generator as StoreUuidv7Generator;
use BlackOps\Internal\Migration\PostgreSqlMigrationSchema;
use BlackOps\Internal\StorageProtection\BopdBinaryEnvelopeCodec;
use BlackOps\Internal\StorageProtection\NonceSource;
use BlackOps\StorageProtection\StorageKey;
use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SensitiveParameter;
use Throwable;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:no-empty-catch-clause
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlAuthenticationAuditTest extends TestCase
{
    private const string SCHEMA_PREFIX = 'blackops_p23_d3_auth_';

    private const string OPAQUE_KEY = '0123456789abcdef0123456789abcdef';

    private const string BUILD_ID = 'd3-postgresql-build';

    private const string NOW = '2026-09-15T00:00:10.000000Z';

    private const string STORE_NOW = '2026-09-15T00:00:11.000000Z';

    private const string SESSION_ISSUED_AT = '2026-09-15T00:00:00.000000Z';

    private const string SESSION_TOUCHED_AT = '2026-09-15T00:06:00.000000Z';

    private const string OUTBOX_ID_1 = '019f9d20-0000-7000-8000-000000000101';

    private const string OUTBOX_ID_2 = '019f9d20-0000-7000-8000-000000000102';

    private const string OUTBOX_ID_3 = '019f9d20-0000-7000-8000-000000000103';

    private const string OUTBOX_ID_4 = '019f9d20-0000-7000-8000-000000000104';

    private const string OUTBOX_ID_5 = '019f9d20-0000-7000-8000-000000000105';

    private const string OUTBOX_ID_6 = '019f9d20-0000-7000-8000-000000000106';

    private const string SEGMENT_ID = '019f9d20-0000-7000-8000-000000000201';

    private const string EVALUATION_ID_1 = '019f9d20-0000-7000-8000-000000000301';

    private const string EVALUATION_ID_2 = '019f9d20-0000-7000-8000-000000000302';

    private const string EVALUATION_ID_3 = '019f9d20-0000-7000-8000-000000000303';

    private const string EVALUATION_ID_4 = '019f9d20-0000-7000-8000-000000000304';

    private const string EVALUATION_ID_5 = '019f9d20-0000-7000-8000-000000000305';

    private const string EVALUATION_ID_6 = '019f9d20-0000-7000-8000-000000000306';

    private string $schema;

    private PostgreSqlMigrationSchema $migration;

    private PostgreSqlAuditOutboxSchema $outboxSchema;

    private PostgreSqlAuditSchema $recordSchema;

    private Connection $admin;

    private Connection $connection;

    private Connection $probe;

    protected function setUp(): void
    {
        $this->schema = self::SCHEMA_PREFIX . bin2hex(random_bytes(6));
        $this->migration = new PostgreSqlMigrationSchema($this->schema);
        $this->outboxSchema = new PostgreSqlAuditOutboxSchema($this->migration);
        $this->recordSchema = new PostgreSqlAuditSchema($this->schema);
        $this->admin = $this->realConnection();
        $this->admin->executeStatement('DROP SCHEMA IF EXISTS ' . $this->migration->quoted() . ' CASCADE');
        $this->admin->executeStatement('CREATE SCHEMA ' . $this->migration->quoted());
        foreach ([...$this->outboxSchema->statements(), ...$this->recordSchema->statements()] as $statement) {
            $this->admin->executeStatement($statement);
        }
        $this->createSessionTable($this->admin);
        $this->connection = $this->realConnection();
        $this->probe = $this->realConnection();
        self::assertNotSame(
            $this->connection->fetchOne('SELECT pg_backend_pid()'),
            $this->probe->fetchOne('SELECT pg_backend_pid()'),
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->connection, $this->probe] as $connection) {
            try {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
            }
            try {
                $connection->close();
            } catch (Throwable) {
            }
        }
        try {
            $this->admin->executeStatement('DROP SCHEMA IF EXISTS ' . $this->migration->quoted() . ' CASCADE');
        } finally {
            $this->admin->close();
        }
    }

    public function testAuthenticatedTenantAuditCommitsBeforeMiddlewareHandlerAndUsesEncryptedIngress(): void
    {
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_1]);
        $actor = new ActorRef('actor-canary', 'user');
        $tenant = new TenantRef('account', 'tenant-canary');
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(AuthenticationResult::authenticated($actor, $tenant), 'provider-canary'),
            [self::EVALUATION_ID_1],
        );
        $handler = new D3AuthenticationProbeHandler(function (ServerRequestInterface $request) use (
            $bundle,
            $actor,
            $tenant,
        ): ResponseInterface {
            self::assertSame($actor, $request->getAttribute(ActorRef::class));
            self::assertSame($tenant, $request->getAttribute(TenantRef::class));
            $rows = $this->outboxRows();
            self::assertCount(1, $rows);
            self::assertSame(0, $this->canonicalCount());
            $logical = $this->logicalFromRow($rows[0], $bundle['protection']);
            $this->assertLogical(
                $logical,
                $rows[0],
                self::EVALUATION_ID_1,
                $actor,
                $tenant,
                'security.authentication.succeeded',
            );

            return new Psr17Factory()->createResponse(204);
        });
        $request = $this->request('request-canary', 'token-canary', 'body-canary');

        $response = new AuthenticationMiddleware($authentication['adapter'])->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
        self::assertNotNull($handler->request);
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(1, $bundle['keys']->activeCalls);
        self::assertSame(1, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
        self::assertSame(0, $this->canonicalCount());
        $this->assertConnectionIdle($this->connection);
    }

    public function testInvalidAuditUsesGlobalPartitionAndCommitsBeforeExistingMiddleware401(): void
    {
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_2]);
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(
                AuthenticationResult::invalid('authentication.invalid_session'),
                'provider-canary',
            ),
            [self::EVALUATION_ID_2],
        );
        $observedBefore401 = false;
        $factory = new D3AuthenticationResponseFactory(function (int $status) use (&$observedBefore401, $bundle): void {
            self::assertSame(401, $status);
            $rows = $this->outboxRows();
            self::assertCount(1, $rows);
            self::assertSame(0, $this->canonicalCount());
            $logical = $this->logicalFromRow($rows[0], $bundle['protection']);
            $this->assertLogical(
                $logical,
                $rows[0],
                self::EVALUATION_ID_2,
                null,
                null,
                'security.authentication.invalid',
            );
            $this->assertNoUntrustedCanaries($logical);
            $observedBefore401 = true;
        });
        $handler = new D3AuthenticationProbeHandler();

        $response = new AuthenticationMiddleware($authentication['adapter'], $factory, $factory)->process(
            $this->request('invalid-request-canary', 'invalid-token-canary', 'invalid-body-canary'),
            $handler,
        );

        self::assertTrue($observedBefore401);
        $this->assertInvalidResponse($response);
        self::assertSame(0, $handler->calls);
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(1, $bundle['keys']->activeCalls);
        self::assertSame(1, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
        $this->assertConnectionIdle($this->connection);
    }

    public function testInvalidAuditAppendFailureRollsBackAndPreservesExistingMiddleware401(): void
    {
        $bundle = $this->bundle($this->connection, ['not-a-uuid']);
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(
                AuthenticationResult::invalid('authentication.invalid_session'),
                'provider-canary',
            ),
            [self::EVALUATION_ID_4],
        );
        $handler = new D3AuthenticationProbeHandler();

        $response = new AuthenticationMiddleware($authentication['adapter'])->process(
            $this->request(
                'invalid-failure-request-canary',
                'invalid-failure-token-canary',
                'invalid-failure-body-canary',
            ),
            $handler,
        );

        $this->assertInvalidResponse($response);
        self::assertSame(0, $handler->calls);
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(0, $bundle['keys']->activeCalls);
        self::assertSame(0, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->canonicalCount());
        $this->assertConnectionIdle($this->connection);
    }

    public function testAuthenticatedWithoutTenantUsesGlobalPartitionAndNullBinding(): void
    {
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_3]);
        $actor = new ActorRef('global-actor', 'user');
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(AuthenticationResult::authenticated($actor), 'provider-canary'),
            [self::EVALUATION_ID_6],
        );
        $handler = new D3AuthenticationProbeHandler(function (ServerRequestInterface $request) use (
            $bundle,
            $actor,
        ): ResponseInterface {
            self::assertSame($actor, $request->getAttribute(ActorRef::class));
            self::assertNull($request->getAttribute(TenantRef::class));
            $rows = $this->outboxRows();
            self::assertCount(1, $rows);
            self::assertSame('system:global', $rows[0]['partition_id']);
            self::assertNull($rows[0]['tenant_type']);
            self::assertNull($rows[0]['tenant_id']);
            self::assertSame(0, $this->canonicalCount());
            $logical = $this->logicalFromRow($rows[0], $bundle['protection']);
            $this->assertLogical(
                $logical,
                $rows[0],
                self::EVALUATION_ID_6,
                $actor,
                null,
                'security.authentication.succeeded',
            );

            return new Psr17Factory()->createResponse(204);
        });

        $response = new AuthenticationMiddleware($authentication['adapter'])->process(
            $this->request('global-request-canary', 'global-token-canary', 'global-body-canary'),
            $handler,
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(1, $bundle['keys']->activeCalls);
        self::assertSame(1, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
        $this->assertConnectionIdle($this->connection);
    }

    public function testAnonymousPassesThroughWithoutAnyAuthenticationAuditWork(): void
    {
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_3]);
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(AuthenticationResult::anonymous(), 'provider-canary'),
            [self::EVALUATION_ID_3],
        );
        $handler = new D3AuthenticationProbeHandler();

        $response = new AuthenticationMiddleware($authentication['adapter'])->process(
            $this->request('anonymous-request-canary', 'anonymous-token-canary', 'anonymous-body-canary'),
            $handler,
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
        self::assertNull($handler->request?->getAttribute(ActorRef::class));
        self::assertNull($handler->request?->getAttribute(TenantRef::class));
        self::assertSame(0, $authentication['evaluations']->calls);
        self::assertSame(0, $authentication['clock']->calls);
        self::assertSame(0, $bundle['keys']->activeCalls);
        self::assertSame(0, $bundle['keys']->keyCalls);
        self::assertSame(0, $bundle['nonces']->calls);
        self::assertSame(0, $bundle['outboxUuids']->calls);
        self::assertSame(0, $bundle['recordUuids']->calls);
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->canonicalCount());
        $this->assertConnectionIdle($this->connection);
    }

    public function testRepeatedAndClonedEvaluationsUseDistinctDurableIdentities(): void
    {
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_4, self::OUTBOX_ID_5, self::OUTBOX_ID_6]);
        $actor = new ActorRef('repeat-actor', 'user');
        $tenant = new TenantRef('account', 'repeat-tenant');
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(AuthenticationResult::authenticated($actor, $tenant), 'provider-canary'),
            [self::EVALUATION_ID_4, self::EVALUATION_ID_5, self::EVALUATION_ID_6],
        );
        $request = $this->request('repeat-request-canary', 'repeat-token-canary', 'repeat-body-canary');

        $first = $authentication['adapter']->authenticate($request);
        $second = $authentication['adapter']->authenticate($request);
        $third = (clone $authentication['adapter'])->authenticate($request);

        self::assertSame($actor, $first->actor());
        self::assertSame($actor, $second->actor());
        self::assertSame($actor, $third->actor());
        $rows = $this->outboxRows();
        self::assertCount(3, $rows);
        $executionIds = [];
        $recordIds = [];
        $actionIds = [];
        $idempotencyHashes = [];
        foreach (array_values($rows) as $offset => $row) {
            $evaluationId = [self::EVALUATION_ID_4, self::EVALUATION_ID_5, self::EVALUATION_ID_6][$offset];
            $logical = $this->logicalFromRow($row, $bundle['protection']);
            $this->assertLogical($logical, $row, $evaluationId, $actor, $tenant, 'security.authentication.succeeded');
            $executionIds[] = $logical['actor']['execution']['id'];
            $recordIds[] = $logical['recordId'];
            $actionIds[] = $logical['action']['id'];
            $idempotencyHashes[] = $row['idempotency_key_hash'];
        }
        self::assertCount(3, array_unique($executionIds));
        self::assertCount(3, array_unique($recordIds));
        self::assertCount(3, array_unique($actionIds));
        self::assertCount(3, array_unique($idempotencyHashes));
        self::assertSame(3, $authentication['evaluations']->calls);
        self::assertSame(3, $authentication['clock']->calls);
        self::assertSame(3, $bundle['keys']->activeCalls);
        self::assertSame(3, $bundle['nonces']->calls);
        self::assertSame(3, $bundle['outboxUuids']->calls);
        self::assertSame(0, $this->canonicalCount());
        $this->assertConnectionIdle($this->connection);
    }

    public function testFailedAppendRollsBackWithoutDurableIngress(): void
    {
        $bundle = $this->bundle($this->connection, ['not-a-uuid']);
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(
                AuthenticationResult::authenticated(new ActorRef('rollback-actor', 'user')),
                'provider-canary',
            ),
            [self::EVALUATION_ID_1],
        );
        $handler = new D3AuthenticationProbeHandler();

        try {
            new AuthenticationMiddleware($authentication['adapter'])->process(
                $this->request('rollback-request-canary', 'rollback-token-canary', 'rollback-body-canary'),
                $handler,
            );
            self::fail('Expected the invalid store UUID to fail delivery.');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
            self::assertSame('Audit delivery failed.', $failure->getMessage());
            self::assertSame(0, $failure->getCode());
            self::assertNull($failure->getPrevious());
        }

        self::assertSame(0, $handler->calls);
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(0, $bundle['keys']->activeCalls);
        self::assertSame(0, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
        self::assertSame(0, $this->outboxCount());
        self::assertSame(0, $this->canonicalCount());
        $this->assertConnectionIdle($this->connection);
    }

    public function testCommitThenThrowLeavesOneIngressButWithholdsAuthenticatedHandler(): void
    {
        $this->replaceConnectionWithCommitThenThrowingConnection();
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_1]);
        $actor = new ActorRef('commit-throw-actor', 'user');
        $tenant = new TenantRef('account', 'commit-throw-tenant');
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(AuthenticationResult::authenticated($actor, $tenant), 'provider-canary'),
            [self::EVALUATION_ID_2],
        );
        $handler = new D3AuthenticationProbeHandler();

        try {
            new AuthenticationMiddleware($authentication['adapter'])->process(
                $this->request('commit-throw-request-canary', 'commit-throw-token-canary', 'commit-throw-body-canary'),
                $handler,
            );
            self::fail('Expected commit-then-throw to withhold the result.');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertSame(0, $failure->getCode());
            self::assertNull($failure->getPrevious());
        }

        self::assertSame(0, $handler->calls);
        $rows = $this->outboxRows();
        self::assertCount(1, $rows);
        $logical = $this->logicalFromRow($rows[0], $bundle['protection']);
        $this->assertLogical(
            $logical,
            $rows[0],
            self::EVALUATION_ID_2,
            $actor,
            $tenant,
            'security.authentication.succeeded',
        );
        self::assertSame(0, $this->canonicalCount());
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(1, $bundle['keys']->activeCalls);
        self::assertSame(1, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
    }

    public function testCommitThenThrowLeavesOneInvalidIngressBeforeExistingMiddleware401(): void
    {
        $this->replaceConnectionWithCommitThenThrowingConnection();
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_6]);
        $authentication = $this->adapter(
            $bundle,
            new D3FixedHttpAuthenticator(
                AuthenticationResult::invalid('authentication.invalid_session'),
                'provider-canary',
            ),
            [self::EVALUATION_ID_5],
        );
        $observedBefore401 = false;
        $factory = new D3AuthenticationResponseFactory(function (int $status) use (&$observedBefore401, $bundle): void {
            self::assertSame(401, $status);
            $rows = $this->outboxRows();
            self::assertCount(1, $rows);
            self::assertSame(0, $this->canonicalCount());
            $logical = $this->logicalFromRow($rows[0], $bundle['protection']);
            $this->assertLogical(
                $logical,
                $rows[0],
                self::EVALUATION_ID_5,
                null,
                null,
                'security.authentication.invalid',
            );
            $this->assertNoUntrustedCanaries($logical);
            $observedBefore401 = true;
        });
        $handler = new D3AuthenticationProbeHandler();

        $response = new AuthenticationMiddleware($authentication['adapter'], $factory, $factory)->process(
            $this->request(
                'invalid-commit-throw-request-canary',
                'invalid-commit-throw-token-canary',
                'invalid-commit-throw-body-canary',
            ),
            $handler,
        );

        self::assertTrue($observedBefore401);
        $this->assertInvalidResponse($response);
        self::assertSame(0, $handler->calls);
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(1, $bundle['keys']->activeCalls);
        self::assertSame(1, $bundle['nonces']->calls);
        self::assertSame(1, $bundle['outboxUuids']->calls);
        self::assertSame(0, $this->canonicalCount());
    }

    public function testActualSessionTouchRemainsCommittedWhenLaterAuditAppendFails(): void
    {
        $sessionClock = new D3SessionClock(new DateTimeImmutable(self::SESSION_ISSUED_AT));
        $sessionManager = new DefaultSessionManager(
            new SessionConfiguration(),
            new D3SessionIdentityProvider(),
            new PostgreSqlSessionStore(
                new D3SingleConnectionDatabaseManager($this->connection),
                $this->migration->doctrineTable('blackops_sessions'),
            ),
            new D3SessionTokenGenerator(),
            new D3SessionIdentifierGenerator(),
            $sessionClock,
        );
        $rawToken = $sessionManager->issue('session-identity-canary')->token()->reveal();
        $sessionClock->set(new DateTimeImmutable(self::SESSION_TOUCHED_AT));
        $bundle = $this->bundle($this->connection, [self::OUTBOX_ID_2]);
        $this->admin->executeStatement('DROP TABLE ' . $this->outboxSchema->recordsTable() . ' CASCADE');
        $this->admin->executeStatement('DROP TABLE ' . $this->outboxSchema->partitionStateTable() . ' CASCADE');
        $authentication = $this->adapter(
            $bundle,
            new BearerSessionAuthenticator($sessionManager),
            [self::EVALUATION_ID_3],
        );
        $handler = new D3AuthenticationProbeHandler();

        try {
            new AuthenticationMiddleware($authentication['adapter'])->process(
                $this->requestWithBearer($rawToken),
                $handler,
            );
            self::fail('Expected the missing audit append tables to fail.');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }

        self::assertSame(
            self::SESSION_TOUCHED_AT,
            (string) $this->probe->fetchOne(
                'SELECT to_char(last_used_at AT TIME ZONE \'UTC\', \'YYYY-MM-DD"T"HH24:MI:SS.US"Z"\') ' . 'FROM '
                    . $this->migration->table('blackops_sessions'),
            ),
        );
        self::assertSame(1, $authentication['evaluations']->calls);
        self::assertSame(1, $authentication['clock']->calls);
        self::assertSame(0, $bundle['keys']->activeCalls);
        self::assertSame(0, $bundle['nonces']->calls);
        self::assertSame(0, $bundle['outboxUuids']->calls);
        self::assertSame(0, $handler->calls);
        self::assertSame(0, $this->canonicalCount());
        $this->assertConnectionIdle($this->connection);
    }

    /**
     * @param list<string> $storeIds
     * @return array{
     *     delivery:AuditDeliveryCoordinator,
     *     protection:AuditProtectedBodyCodec,
     *     keys:D3AuditKeySource,
     *     nonces:D3AuditNonceSource,
     *     outboxUuids:D3StoreUuidGenerator,
     *     recordUuids:D3StoreUuidGenerator
     * }
     */
    private function bundle(Connection $connection, array $storeIds): array
    {
        $jcs = new AuditJcsCodec();
        $partitions = new AuditPartitionIdDeriver($jcs);
        $catalog = FrameworkAuditEventCatalog::catalog();
        $lookup = new \BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup([$catalog]);
        $validator = new AuditRecordValidator($lookup);
        $recordsCodec = new AuditRecordCodec($validator, $jcs);
        $finalizer = new AuditRecordFinalizer($validator, $recordsCodec);
        $envelopes = new AuditIngressEnvelopeCodec($recordsCodec, $partitions, $jcs);
        $keys = new D3AuditKeySource();
        $nonces = new D3AuditNonceSource();
        $protection = new AuditProtectedBodyCodec(
            new BopdBinaryEnvelopeCodec(),
            $keys,
            $nonces,
            new AuditAssociatedData(),
            $recordsCodec,
            $envelopes,
        );
        $storeClock = new D3StoreClock(new DateTimeImmutable(self::STORE_NOW));
        $outboxUuids = new D3StoreUuidGenerator($storeIds);
        $recordUuids = new D3StoreUuidGenerator([self::SEGMENT_ID]);
        $recordStore = new PostgreSqlAuditRecordStore(
            $connection,
            $this->recordSchema,
            $storeClock,
            $recordUuids,
            $validator,
            $finalizer,
            $partitions,
            $recordsCodec,
            $protection,
            new PostgreSqlAuditStoredRecordHydrator($partitions),
        );
        $outboxStore = new PostgreSqlAuditOutboxStore(
            $connection,
            $this->outboxSchema,
            $storeClock,
            $outboxUuids,
            $validator,
            $finalizer,
            $partitions,
            $envelopes,
            $protection,
            new AuditOutboxIdempotencyKeyHasher(),
            new PostgreSqlAuditOutboxRecordHydrator(
                new \BlackOps\Internal\Audit\Outbox\PostgreSqlAuditProtectedIngressHydrator(),
            ),
            new AuditOutboxConfiguration(),
        );

        return [
            'delivery' => new AuditDeliveryCoordinator(
                $connection,
                new AuditDeliverySelector($lookup, $validator),
                $recordStore,
                $outboxStore,
            ),
            'protection' => $protection,
            'keys' => $keys,
            'nonces' => $nonces,
            'outboxUuids' => $outboxUuids,
            'recordUuids' => $recordUuids,
        ];
    }

    /**
     * @param array{
     *     delivery:AuditDeliveryCoordinator,
     *     protection:AuditProtectedBodyCodec,
     *     keys:D3AuditKeySource,
     *     nonces:D3AuditNonceSource,
     *     outboxUuids:D3StoreUuidGenerator,
     *     recordUuids:D3StoreUuidGenerator
     * } $bundle
     * @param list<string> $evaluationIds
     * @return array{adapter:AuditedHttpAuthenticator,evaluations:D3EvaluationUuidGenerator,clock:D3EvaluationClock}
     */
    private function adapter(array $bundle, HttpAuthenticator $delegate, array $evaluationIds): array
    {
        $evaluations = new D3EvaluationUuidGenerator($evaluationIds);
        $clock = new D3EvaluationClock(new DateTimeImmutable(self::NOW));
        $writer = new DurableAuthenticationAuditEventWriter(
            $bundle['delivery'],
            new HmacAuditOpaqueIdDeriver(self::OPAQUE_KEY),
            $evaluations,
            $clock,
            self::BUILD_ID,
        );

        return [
            'adapter' => new AuditedHttpAuthenticator($delegate, $writer, $this->connection),
            'evaluations' => $evaluations,
            'clock' => $clock,
        ];
    }

    private function request(string $requestCanary, string $tokenCanary, string $bodyCanary): ServerRequestInterface
    {
        $factory = new Psr17Factory();

        return $factory
            ->createServerRequest('POST', '/auth/' . $requestCanary)
            ->withHeader('Authorization', 'Bearer ' . $tokenCanary)
            ->withHeader('X-Request-Canary', $requestCanary)
            ->withBody($factory->createStream($bodyCanary));
    }

    private function requestWithBearer(#[SensitiveParameter] string $rawToken): ServerRequestInterface
    {
        return new Psr17Factory()
            ->createServerRequest('GET', '/session')
            ->withHeader('Authorization', 'Bearer ' . $rawToken);
    }

    /** @return list<array<string,mixed>> */
    private function outboxRows(): array
    {
        $rows = $this->probe->fetchAllAssociative(
            'SELECT record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'ingress_ordinal::text AS ingress_ordinal, idempotency_key_hash, content_hash, state, protected_body '
            . 'FROM '
            . $this->outboxSchema->recordsTable()
            . ' ORDER BY ingress_ordinal ASC',
        );
        foreach ($rows as &$row) {
            $row['protected_body'] = \BlackOps\Transport\PostgreSql\PostgreSqlBytea::string($row['protected_body']);
        }
        unset($row);

        return $rows;
    }

    private function outboxCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->outboxSchema->recordsTable());
    }

    private function canonicalCount(): int
    {
        return (int) $this->probe->fetchOne('SELECT count(*) FROM ' . $this->recordSchema->recordsTable());
    }

    /** @param array<string,mixed> $row */
    private function logicalFromRow(array $row, AuditProtectedBodyCodec $protection): array
    {
        $tenant = $row['tenant_type'] === null ? null : ['type' => $row['tenant_type'], 'id' => $row['tenant_id']];
        $ingress = \BlackOps\Internal\Audit\Protection\AuditProtectedIngress::fromArray([
            'recordId' => $row['record_id'],
            'partitionId' => $row['partition_id'],
            'tenantBinding' => $tenant,
            'ingressOrdinal' => (int) $row['ingress_ordinal'],
            'idempotencyKeyHash' => $row['idempotency_key_hash'],
            'contentHash' => $row['content_hash'],
            'protectedBody' => $row['protected_body'],
        ]);
        $envelope = $protection->unprotectOutbox($ingress);
        $value = $envelope->toArray();
        self::assertSame($row['content_hash'], $value['contentHash']);
        self::assertSame('pending', $row['state']);
        self::assertStringStartsWith('BOPD', $row['protected_body']);

        return $value['logicalRecord'];
    }

    /** @param array<string,mixed> $logical @param array<string,mixed> $row */
    private function assertLogical(
        array $logical,
        array $row,
        string $evaluationId,
        ?ActorRef $actor,
        ?TenantRef $tenant,
        string $eventType,
    ): void {
        $success = $eventType === 'security.authentication.succeeded';
        $partition = $this->partition($tenant);
        $actionId = (string) new HmacAuditOpaqueIdDeriver(self::OPAQUE_KEY)->derive(
            AuditOpaqueIdPurpose::Action,
            'http_authentication:' . $evaluationId,
        );
        $resourceId = (string) new HmacAuditOpaqueIdDeriver(self::OPAQUE_KEY)->derive(
            AuditOpaqueIdPurpose::Resource,
            'http_authentication:' . $evaluationId,
        );
        $expectedTenant = $tenant === null ? null : ['type' => $tenant->type(), 'id' => $tenant->id()];
        $expectedPrincipal = $actor === null ? null : ['type' => $actor->type(), 'id' => $actor->id()];
        self::assertSame(
            [
                'schemaVersion',
                'kind',
                'recordId',
                'event',
                'action',
                'actor',
                'resource',
                'outcome',
                'policy',
                'tenant',
                'time',
                'source',
                'correlation',
                'evidence',
            ],
            array_keys($logical),
        );
        self::assertSame((string) $row['record_id'], $logical['recordId']);
        self::assertNotSame($evaluationId, $logical['recordId']);
        self::assertSame($partition, $row['partition_id']);
        self::assertSame($tenant?->type(), $row['tenant_type']);
        self::assertSame($tenant?->id(), $row['tenant_id']);
        self::assertSame(
            [
                'type' => $eventType,
                'version' => 1,
                'category' => 'security',
                'catalogHash' => FrameworkAuditEventCatalog::catalog()->catalogHash,
            ],
            $logical['event'],
        );
        self::assertSame(
            ['id' => $actionId, 'type' => $eventType, 'phase' => $success ? 'succeeded' : 'denied'],
            $logical['action'],
        );
        self::assertSame(
            [
                'principal' => $expectedPrincipal,
                'execution' => ['type' => 'http_authentication', 'id' => $evaluationId],
            ],
            $logical['actor'],
        );
        self::assertSame(['type' => 'authentication', 'id' => $resourceId], $logical['resource']);
        self::assertSame(
            [
                'status' => $success ? 'succeeded' : 'denied',
                'code' => $success ? 'authenticated' : 'authentication_invalid',
            ],
            $logical['outcome'],
        );
        self::assertSame(
            [
                'id' => 'http-authentication',
                'version' => 1,
                'decision' => $success ? 'allow' : 'deny',
                'reasonCode' => $success ? 'authenticated' : 'authentication_invalid',
                'auditDelivery' => 'durable',
            ],
            $logical['policy'],
        );
        self::assertSame($expectedTenant, $logical['tenant']);
        self::assertSame(['occurredAt' => self::NOW, 'recordedAt' => self::STORE_NOW], $logical['time']);
        self::assertSame(
            [
                'owner' => 'framework',
                'component' => 'http-authentication',
                'runtime' => 'http',
                'buildId' => self::BUILD_ID,
            ],
            $logical['source'],
        );
        self::assertSame([], $logical['correlation']);
        self::assertSame(['version' => 1], $logical['evidence']);
        self::assertSame(
            new AuditOutboxIdempotencyKeyHasher()->hash($partition, 'http-authentication:' . $actionId),
            $row['idempotency_key_hash'],
        );
        self::assertStringNotContainsString(self::OPAQUE_KEY, $row['protected_body']);
    }

    /** @param array<string,mixed> $logical */
    private function assertNoUntrustedCanaries(array $logical): void
    {
        $encoded = json_encode($logical, JSON_THROW_ON_ERROR);
        foreach ([
            'invalid-request-canary',
            'invalid-token-canary',
            'invalid-body-canary',
            'provider-canary',
            'authentication.invalid_session',
        ] as $canary) {
            self::assertStringNotContainsString($canary, $encoded);
        }
    }

    private function partition(?TenantRef $tenant): string
    {
        $reference = $tenant === null ? null : new AuditTypedReference($tenant->type(), $tenant->id());

        return new AuditPartitionIdDeriver(new AuditJcsCodec())->derive($reference);
    }

    private function assertConnectionIdle(Connection $connection): void
    {
        self::assertTrue($connection->isAutoCommit());
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(0, $connection->getTransactionNestingLevel());
    }

    private function assertInvalidResponse(ResponseInterface $response): void
    {
        self::assertSame(401, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertSame(
            [
                'status' => 'error',
                'category' => 'unauthorized',
                'code' => 'authentication.invalid_session',
            ],
            json_decode($body, true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('operation_id', $body);
    }

    private function replaceConnectionWithCommitThenThrowingConnection(): void
    {
        $params = $this->connection->getParams();
        $driver = $this->connection->getDriver();
        $this->connection->close();
        $this->connection = new D3CommitThenThrowingConnection($params, $driver);
        $this->connection->arm();
    }

    private function createSessionTable(Connection $connection): void
    {
        $table = $this->migration->table('blackops_sessions');
        $connection->executeStatement(
            'CREATE TABLE '
            . $table
            . ' (
                id UUID PRIMARY KEY,
                identity_id VARCHAR(255) NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE CHECK (token_hash ~ \'^[0-9a-f]{64}$\'),
                issued_at TIMESTAMPTZ NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL CHECK (expires_at > issued_at),
                last_used_at TIMESTAMPTZ NOT NULL CHECK (last_used_at >= issued_at AND last_used_at <= expires_at),
                revoked_at TIMESTAMPTZ NULL,
                rotated_to_id UUID NULL UNIQUE,
                CONSTRAINT blackops_sessions_rotated_to_id_fkey
                    FOREIGN KEY (rotated_to_id) REFERENCES '
            . $table
            . ' (id) ON DELETE SET NULL
            )',
        );
        $connection->executeStatement(
            'CREATE INDEX blackops_sessions_identity_idx ON ' . $table . ' (identity_id, expires_at)',
        );
        $connection->executeStatement(
            'CREATE INDEX blackops_sessions_expiry_cleanup_idx ON ' . $table . ' (expires_at, id)',
        );
        $connection->executeStatement(
            'CREATE INDEX blackops_sessions_revocation_cleanup_idx ON '
            . $table
            . ' (revoked_at, id) WHERE revoked_at IS NOT NULL',
        );
    }

    private function realConnection(): Connection
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
}

/** @mago-expect lint:single-class-per-file */
final class D3FixedHttpAuthenticator implements HttpAuthenticator
{
    public function __construct(
        private readonly AuthenticationResult $result,
        public readonly string $providerCanary,
    ) {}

    public function authenticate(#[SensitiveParameter] ServerRequestInterface $request): AuthenticationResult
    {
        return $this->result;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3AuthenticationProbeHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly ?Closure $callback = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;
        $this->request = $request;
        if ($this->callback !== null) {
            return ($this->callback)($request);
        }

        return new Psr17Factory()->createResponse(204);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3AuthenticationResponseFactory implements ResponseFactoryInterface, StreamFactoryInterface
{
    public function __construct(
        private readonly Closure $onResponse,
    ) {}

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        ($this->onResponse)($code);

        return new Psr17Factory()->createResponse($code, $reasonPhrase);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new Psr17Factory()->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new Psr17Factory()->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return new Psr17Factory()->createStreamFromResource($resource);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3EvaluationUuidGenerator implements Uuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(): string
    {
        ++$this->calls;
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('evaluation UUID sequence exhausted.');
        }

        return $id;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3EvaluationClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly DateTimeImmutable $value,
    ) {}

    public function now(): DateTimeImmutable
    {
        ++$this->calls;

        return $this->value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3StoreClock implements ClockInterface
{
    public function __construct(
        private readonly DateTimeImmutable $value,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->value;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3StoreUuidGenerator implements StoreUuidv7Generator
{
    public int $calls = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids,
    ) {}

    public function generate(DateTimeImmutable $time): string
    {
        ++$this->calls;
        $id = array_shift($this->ids);
        if (!is_string($id)) {
            throw new \RuntimeException('store UUID sequence exhausted.');
        }

        return $id;
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3AuditNonceSource implements NonceSource
{
    public int $calls = 0;

    public function generate(): string
    {
        ++$this->calls;

        return substr(hash('sha256', 'd3-audit-nonce-' . $this->calls, true), 0, 24);
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3AuditKeySource implements AuditProtectionKeySource
{
    public int $activeCalls = 0;

    public int $keyCalls = 0;

    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey
    {
        ++$this->activeCalls;

        return $this->keyFor($purpose);
    }

    public function key(
        #[SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey {
        ++$this->keyCalls;
        $key = $this->keyFor($purpose);
        if ($key->id() !== $keyId) {
            throw new \RuntimeException('Unexpected audit key identifier.');
        }

        return $key;
    }

    private function keyFor(AuditProtectionPurpose $purpose): StorageKey
    {
        return new StorageKey(
            $purpose === AuditProtectionPurpose::AuditRecord ? 'd3-audit-record' : 'd3-audit-outbox',
            $purpose === AuditProtectionPurpose::AuditRecord ? str_repeat('r', 32) : str_repeat('o', 32),
        );
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3CommitThenThrowingConnection extends Connection
{
    private bool $throwAfterCommit = false;

    public function __construct(array $params, Driver $driver)
    {
        parent::__construct($params, $driver);
    }

    public function arm(): void
    {
        $this->throwAfterCommit = true;
    }

    public function commit(): void
    {
        parent::commit();
        if ($this->throwAfterCommit) {
            $this->throwAfterCommit = false;
            throw new \RuntimeException('commit response lost after durable commit.');
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final class D3SessionClock implements SessionClock
{
    public function __construct(
        private DateTimeImmutable $value,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->value;
    }

    public function set(DateTimeImmutable $value): void
    {
        $this->value = $value;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D3SessionIdentityProvider implements SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef
    {
        return new ActorRef($identityId, 'user');
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D3SingleConnectionDatabaseManager implements DatabaseManager
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function connection(?string $name = null): Connection
    {
        return $this->connection;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D3SessionIdentifierGenerator implements SessionIdentifierGenerator
{
    public function generate(DateTimeImmutable $time): string
    {
        return '019f9d20-0000-7000-8000-000000000401';
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class D3SessionTokenGenerator implements SessionTokenGenerator
{
    public function generate(): RawSessionToken
    {
        return RawSessionToken::fromRandomBytes(str_repeat('s', 32));
    }
}
