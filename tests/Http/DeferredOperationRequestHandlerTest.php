<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpOperationRoute;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Authorization\AuthorizationPolicyResolver;
use BlackOps\Internal\Codec\ReflectionJsonOperationCodec;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Http\OperationFailureErrorBoundary;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use BlackOps\Transport\PostgreSql\PostgreSqlDeferredOperationSender;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

final class DeferredOperationRequestHandlerTest extends TestCase
{
    private const SCHEMA = 'blackops_p3_008';
    private const OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687711';

    private Psr17Factory $psr17;
    private Connection $connection;
    private PostgreSqlCanonicalJournalStore $journal;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->connection = $this->connection();
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $this->journal = new PostgreSqlCanonicalJournalStore($this->connection, self::SCHEMA);
    }

    public function testDeferredRouteReturnsAcceptedResponseAndPersistsStateAndJournal(): void
    {
        $handler = $this->handler(new ReflectionJsonOperationCodec());
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withBody($this->psr17->createStream('{"reportName":"weekly"}'));

        $response = $handler->handle($request);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('/operations/' . self::OPERATION_ID, $response->getHeaderLine('Location'));
        self::assertSame('1', $response->getHeaderLine('Retry-After'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(
            '{"status":"accepted","operationId":"'
            . self::OPERATION_ID
            . '","acceptedAt":"2026-07-10T00:00:01.123456Z"}',
            (string) $response->getBody(),
        );

        $operationRow = $this->operationRow();
        $records = $this->records();

        self::assertSame(self::OPERATION_ID, $operationRow['operation_id']);
        self::assertSame('report.generate', $operationRow['operation_type']);
        self::assertSame('accepted', $operationRow['state']);
        self::assertSame(3, (int) $operationRow['next_sequence']);
        self::assertSame('{"reportName":"weekly"}', $operationRow['payload']);
        self::assertStringContainsString('"operation_id":"' . self::OPERATION_ID . '"', $operationRow['context']);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationAccepted],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertInstanceOf(OperationReceivedData::class, $records[0]->data);
        self::assertInstanceOf(ReportRequestValue::class, $records[0]->data->value);
        self::assertSame('weekly', $records[0]->data->value->reportName);
        self::assertInstanceOf(EmptyJournalData::class, $records[1]->data);
    }

    public function testDeferredRouteDoesNotCallInlineDispatcher(): void
    {
        $handler = $this->handler(new ReflectionJsonOperationCodec());
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withBody($this->psr17->createStream('{"reportName":"daily"}'));

        $response = $handler->handle($request);

        self::assertSame(202, $response->getStatusCode());
    }

    public function testDeferredBindingFailureRejectsWithoutPersistenceOrAcceptance(): void
    {
        $handler = $this->handler(new ReflectionJsonOperationCodec());
        $request = $this->psr17->createServerRequest('POST', '/reports')->withBody($this->psr17->createStream('{}'));

        $response = $handler->handle($request);
        $records = $this->records();

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('"operationId":"' . self::OPERATION_ID . '"', (string) $response->getBody());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertSame([JournalEvent::OperationRejected], array_column($records, 'event'));
        self::assertSame([1], array_column($records, 'sequence'));
    }

    public function testDeferredValueFailureRejectsBeforePersistenceOrAcceptance(): void
    {
        $handler = $this->handler(new ReflectionJsonOperationCodec());
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withBody($this->psr17->createStream('{"reportName":""}'));

        $response = $handler->handle($request);
        $records = $this->records();

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('"operationId":"' . self::OPERATION_ID . '"', (string) $response->getBody());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationRejected],
            array_column($records, 'event'),
        );
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertInstanceOf(OperationReceivedData::class, $records[0]->data);
        self::assertSame('', $records[0]->data->value->reportName);
    }

    public function testAnonymousAuthorizedDeferredRequestRejectsBeforePolicyAndEnqueue(): void
    {
        $policy = new DeferredHttpAuthorizationPolicy(AuthorizationDecision::allow());
        $handler = $this->handler(new ReflectionJsonOperationCodec(), $policy);

        $response = $handler->handle(
            $this->psr17
                ->createServerRequest('POST', '/reports')
                ->withBody($this->psr17->createStream('{"reportName":"weekly"}')),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('"operationId":"' . self::OPERATION_ID . '"', (string) $response->getBody());
        self::assertStringContainsString(
            '"code":"authorization.authentication_required"',
            (string) $response->getBody(),
        );
        self::assertSame(0, $policy->calls);
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationRejected],
            array_column($this->records(), 'event'),
        );
    }

    public function testAuthenticatedForbiddenDeferredRequestRejectsWithoutEnqueue(): void
    {
        $policy = new DeferredHttpAuthorizationPolicy(AuthorizationDecision::forbid('authorization.report_forbidden'));
        $actor = new ActorRef('user-123', 'user');
        $handler = $this->handler(new ReflectionJsonOperationCodec(), $policy);
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withAttribute(ActorRef::class, $actor)
            ->withBody($this->psr17->createStream('{"reportName":"weekly"}'));

        $response = $handler->handle($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            '{"status":"rejected","operationId":"'
            . self::OPERATION_ID
            . '","category":"forbidden","code":"authorization.report_forbidden"}',
            (string) $response->getBody(),
        );
        self::assertSame($actor, $policy->request?->actor());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        self::assertSame([1, 2], array_column($this->records(), 'sequence'));
    }

    public function testAuthenticatedAllowDeferredRequestPersistsActorIdsAndEnqueues(): void
    {
        $policy = new DeferredHttpAuthorizationPolicy(AuthorizationDecision::allow());
        $actor = new ActorRef('user-123', 'user');
        $handler = $this->handler(new ReflectionJsonOperationCodec(), $policy);
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withAttribute(ActorRef::class, $actor)
            ->withBody($this->psr17->createStream('{"reportName":"weekly"}'));

        $response = $handler->handle($request);
        $operationRow = $this->operationRow();

        self::assertSame(202, $response->getStatusCode());
        self::assertSame($actor, $policy->request?->context()->actorContext()?->origin());
        self::assertSame($actor, $policy->request?->context()->actorContext()?->authorization());
        self::assertSame($actor, $policy->request?->context()->actorContext()?->execution());
        self::assertStringContainsString(
            '"actors":{"origin":{"id":"user-123","type":"user"}',
            $operationRow['context'],
        );
        self::assertStringNotContainsString('credential', $operationRow['context']);
        self::assertStringNotContainsString('permission', $operationRow['context']);
    }

    public function testPolicyBackendFailureReturnsSafeCorrelatedErrorAndAttemptlessTerminalJournal(): void
    {
        $failure = new RuntimeException('authorization backend credential detail');
        $policy = new DeferredHttpAuthorizationPolicy(AuthorizationDecision::allow(), $failure);
        $handler = $this->handler(new ReflectionJsonOperationCodec(), $policy);
        $request = $this->psr17
            ->createServerRequest('POST', '/reports')
            ->withAttribute(ActorRef::class, new ActorRef('user-123', 'user'))
            ->withBody($this->psr17->createStream('{"reportName":"weekly"}'));

        $response = $handler->handle($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            '{"status":"error","code":"internal_error","operationId":"' . self::OPERATION_ID . '"}',
            (string) $response->getBody(),
        );
        self::assertStringNotContainsString('credential', (string) $response->getBody());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT count(*) FROM ' . self::SCHEMA . '.operations'));
        $records = $this->records();
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationFailed],
            array_column($records, 'event'),
        );
        self::assertSame([1, 2], array_column($records, 'sequence'));
        self::assertNull($records[0]->attempt);
        self::assertNull($records[1]->attempt);
    }

    private function handler(OperationCodec $codec, ?AuthorizationPolicy $policy = null): RequestHandlerInterface
    {
        $clock = new DeferredHttpClock();
        $identifiers = new IdentifierFactory(new DeferredHttpUuidv7Generator(), $clock);
        $sender = new PostgreSqlDeferredOperationSender(
            $this->connection,
            self::SCHEMA,
            new DateTimeImmutable('2026-07-10T00:00:01.123456Z'),
        );
        $sender->migrate();
        $this->journal->migrate();
        $registry = new OperationRegistry([$this->metadata(
            $policy === null ? null : DeferredHttpAuthorizationPolicy::class,
        )]);
        $orchestrator = new DeferredAcceptanceOrchestrator(
            $this->connection,
            $sender,
            $this->journal,
            new JournalRecordFactory($identifiers, $clock),
            authorization: $policy === null
                ? null
                : new AuthorizationEvaluator(new AuthorizationPolicyResolver(new DeferredHttpPolicyContainer($policy))),
        );
        $dispatcher = new InlineDispatcher(
            $registry,
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver(new DeferredHttpFailingContainer()),
            new JournalRecordFactory($identifiers, $clock),
            $this->journal,
        );

        $responder = new JsonOperationResponder($this->psr17, $this->psr17);
        $handler = new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute(
                'POST',
                '/reports',
                new GenerateReport(),
                ReportRequestValue::class,
            )]),
            new OperationValueBinder(),
            $dispatcher,
            $responder,
            $this->psr17,
            $dispatcher,
            new DeferredHttpOperationAcceptor(
                $registry,
                new ExecutionContextFactory($identifiers, $clock),
                $codec,
                $orchestrator,
            ),
        );
        $scope = new ExecutionScopeProvider();

        return new OperationFailureErrorBoundary(
            $handler,
            $responder,
            new FrameworkOperationFailureReporter(new ExecutionScopedLogger(new NullLogger(), $scope), $scope),
        );
    }

    /** @param class-string<AuthorizationPolicy>|null $policy */
    private function metadata(?string $policy = null): OperationMetadata
    {
        return new OperationMetadata(
            'report.generate',
            GenerateReport::class,
            ReportRequestValue::class,
            GenerateReportHandler::class,
            EmptyOutcome::class,
            Deferred::class,
            authorizationPolicy: $policy,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationRow(): array
    {
        $row = $this->connection->fetchAssociative('SELECT operation_id::text AS operation_id,
                operation_type,
                state,
                next_sequence,
                convert_from(encoded_payload, \'UTF8\') AS payload,
                convert_from(encoded_context, \'UTF8\') AS context
            FROM ' . self::SCHEMA . '.operations');

        self::assertIsArray($row);

        return $row;
    }

    /**
     * @return list<JournalRecord>
     */
    private function records(): array
    {
        return array_values(iterator_to_array($this->journal->records(OperationId::fromString(self::OPERATION_ID))));
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

#[Route(method: 'POST', path: '/reports')]
final readonly class GenerateReport implements Operation {}

final readonly class ReportRequestValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $reportName,
    ) {}
}

abstract class GenerateReportHandler implements OperationHandler {}

final class DeferredHttpAuthorizationPolicy implements AuthorizationPolicy
{
    public int $calls = 0;
    public ?AuthorizationRequest $request = null;

    public function __construct(
        private readonly AuthorizationDecision $decision,
        private readonly ?Throwable $failure = null,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        ++$this->calls;
        $this->request = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->decision;
    }
}

final readonly class DeferredHttpPolicyContainer implements ContainerInterface
{
    public function __construct(
        private AuthorizationPolicy $policy,
    ) {}

    public function get(string $id): mixed
    {
        return $this->policy;
    }

    public function has(string $id): bool
    {
        return true;
    }
}

final readonly class DeferredHttpFailingContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        self::fail('Deferred handler resolution should not run during HTTP acceptance.');
    }

    public function has(string $id): bool
    {
        return true;
    }
}

final readonly class DeferredHttpClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:00:00.000000Z');
    }
}

final class DeferredHttpUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687711',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687712',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687713',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687714',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687715',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687716',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
