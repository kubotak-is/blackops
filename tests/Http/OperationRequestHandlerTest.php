<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Validation\Violation;
use BlackOps\Execution\Dispatcher;
use BlackOps\Execution\ValidationRejectionRecorder;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpOperationRoute;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\OperationFailureErrorBoundary;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlCanonicalJournalStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;

final class OperationRequestHandlerTest extends TestCase
{
    private const SCHEMA = 'blackops_p1_017';

    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testWelcomeRequestReturnsJsonAndPersistsCompletedLifecycleJournal(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $journal = new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA);
        $journal->migrate();
        $handler = $this->httpHandler($this->inlineDispatcher(new WelcomeHandler(), $journal));

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"message":"Welcome to BlackOps"}', (string) $response->getBody());

        $records = $this->recordsForOnlyOperation($connection, $journal);

        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $records),
        );
        self::assertSame([1, 2, 3, 4], array_column($records, 'sequence'));
        self::assertSame('welcome.show', $records[0]->operation->type);
    }

    public function testEmptyOutcomeReturnsNoContent(): void
    {
        $handler = $this->httpHandler(new FixedDispatcher(OperationResult::completed()));

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testInlineFailureReturnsSafeCorrelatedServerErrorAndTerminalJournal(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $journal = new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA);
        $journal->migrate();
        $handler = $this->httpHandler($this->inlineDispatcher(new ThrowingWelcomeHandler(), $journal));

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"status":"error","code":"internal_error","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697"}',
            (string) $response->getBody(),
        );
        self::assertStringNotContainsString('credential', (string) $response->getBody());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($this->recordsForOnlyOperation($connection, $journal), 'event'),
        );
    }

    public function testRejectedResultReturnsStableJsonError(): void
    {
        $handler = $this->httpHandler(new FixedDispatcher(OperationResult::rejected(RejectionReason::conflict(
            'welcome_unavailable',
        ))));

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(
            '{"status":"rejected","category":"conflict","code":"welcome_unavailable"}',
            (string) $response->getBody(),
        );
    }

    public function testRejectedResultWithOperationIdReturnsCorrelatedJsonError(): void
    {
        $operationId = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697');
        $handler = $this->httpHandler(new FixedDispatcher(OperationResult::rejected(
            RejectionReason::forbidden('authorization.welcome_forbidden'),
            $operationId,
        )));

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            '{"status":"rejected","operationId":"019f32ab-2be0-7b38-a0a7-1ab2f9687697","category":"forbidden","code":"authorization.welcome_forbidden"}',
            (string) $response->getBody(),
        );
    }

    public function testAuthenticatedRequestActorIsPassedAsCompleteActorContext(): void
    {
        $dispatcher = new RecordingDispatcher(OperationResult::completed(new WelcomeShown('ok')));
        $actor = new ActorRef('user-123', 'user');
        $handler = $this->httpHandler($dispatcher);

        $response = $handler->handle($this->request('GET', '/welcome')->withAttribute(ActorRef::class, $actor));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($actor, $dispatcher->actorContext?->origin());
        self::assertSame($actor, $dispatcher->actorContext?->authorization());
        self::assertSame($actor, $dispatcher->actorContext?->execution());
    }

    public function testNonActorReservedAttributeIsIgnored(): void
    {
        $dispatcher = new RecordingDispatcher(OperationResult::completed(new WelcomeShown('ok')));
        $handler = $this->httpHandler($dispatcher);

        $handler->handle($this->request('GET', '/welcome')->withAttribute(ActorRef::class, 'credential-value'));

        self::assertNull($dispatcher->actorContext);
    }

    public function testDeferredAcceptorCannotReturnCompletedResult(): void
    {
        $handler = new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute('GET', '/welcome', new ShowWelcome(), WelcomeValue::class)]),
            new OperationValueBinder(),
            new FailingDispatcher(),
            new JsonOperationResponder($this->psr17, $this->psr17),
            $this->psr17,
            new NoopValidationRejectionRecorder(),
            new CompletedDeferredAcceptor(),
        );

        $this->expectException(LogicException::class);

        $handler->handle($this->request('GET', '/welcome'));
    }

    public function testManualValidationRejectionKeepsLegacyResponseShape(): void
    {
        $handler = $this->httpHandler(new FixedDispatcher(OperationResult::rejected(RejectionReason::validation(
            'input.invalid',
        ))));

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            '{"status":"rejected","category":"validation","code":"input.invalid"}',
            (string) $response->getBody(),
        );
    }

    public function testGetWithBodyIsRejectedBeforeDispatch(): void
    {
        $handler = $this->httpHandler(new FailingDispatcher());

        $response = $handler->handle($this->request('GET', '/welcome', 'body'));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $handler = $this->httpHandler(new FailingDispatcher());

        $response = $handler->handle($this->request('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedReturnsNotFound(): void
    {
        $handler = $this->httpHandler(new FailingDispatcher());

        $response = $handler->handle($this->request('POST', '/welcome'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRouteCompilerReadsRouteAttribute(): void
    {
        $registry = new OperationRegistry([$this->metadata()]);

        $routes = new HttpRouteCompiler($registry)->compile([new ShowWelcome()]);

        self::assertNotNull($routes->match('GET', '/welcome'));
    }

    public function testRouteCompilerBuildsManifestArray(): void
    {
        $registry = new OperationRegistry([$this->metadata()]);

        $manifest = new HttpRouteCompiler($registry)->compileManifest([new ShowWelcome()])->toArray();

        self::assertSame('welcome.show', $manifest['routes']['GET']['/welcome']);
        self::assertSame('welcome.show', $manifest['dispatcherData'][0]['GET']['/welcome']);
        self::assertSame(ShowWelcome::class, $manifest['operations']['welcome.show']['definition']);
        self::assertSame(WelcomeValue::class, $manifest['operations']['welcome.show']['value']);
    }

    public function testRouteCompilerReflectsRequiredConstructorDefinitionWithoutInstantiatingIt(): void
    {
        $metadata = new OperationMetadata(
            'welcome.required',
            RequiredWelcomeOperation::class,
            WelcomeValue::class,
            RequiredWelcomeOperation::class,
            WelcomeShown::class,
            Inline::class,
        );

        $manifest = new HttpRouteCompiler(new OperationRegistry([$metadata]))->compileManifest([
            RequiredWelcomeOperation::class,
        ]);

        self::assertSame('welcome.required', $manifest->routes['GET']['/required']);
        self::assertSame(RequiredWelcomeOperation::class, $manifest->operations['welcome.required']['handler']);
    }

    public function testRouteCompilerBuildsFastRouteDynamicDispatcherData(): void
    {
        $registry = new OperationRegistry([$this->pathMetadata('welcome.path', PathWelcomeOperation::class)]);
        $routes = new HttpRouteCompiler($registry)->compile([new PathWelcomeOperation()]);

        $match = $routes->match('GET', '/welcome/Ada%20Lovelace');

        self::assertNotNull($match);
        self::assertSame('/welcome/{name}', $match->route->path);
        self::assertSame(['name' => 'Ada Lovelace'], $match->pathParameters);
    }

    public function testRouteCompilerRejectsDuplicateRoutes(): void
    {
        $registry = new OperationRegistry([
            $this->pathMetadata('welcome.duplicate.first', DuplicateWelcomeOperation::class),
            $this->pathMetadata('welcome.duplicate.second', SecondDuplicateWelcomeOperation::class),
        ]);

        $this->expectException(InvalidArgumentException::class);

        new HttpRouteCompiler($registry)->compileManifest([
            new DuplicateWelcomeOperation(),
            new SecondDuplicateWelcomeOperation(),
        ]);
    }

    public function testRouteCompilerRejectsConflictingDynamicRoutes(): void
    {
        $registry = new OperationRegistry([
            $this->pathMetadata('welcome.path', PathWelcomeOperation::class),
            $this->pathMetadata('welcome.conflict', ConflictingWelcomeOperation::class),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate or conflicting route');

        new HttpRouteCompiler($registry)->compileManifest([
            new PathWelcomeOperation(),
            new ConflictingWelcomeOperation(),
        ]);
    }

    public function testBindingAttributesReadPathQueryHeaderAndBody(): void
    {
        $request = $this
            ->request('POST', '/items/42?ignored=1', '{"name":"Ada","note":"hello"}')
            ->withQueryParams(['search' => 'term'])
            ->withHeader('X-Trace', 'trace-1');

        $value = new OperationValueBinder()->bind(BoundHttpValueFixture::class, $request, ['id' => '42']);

        self::assertInstanceOf(BoundHttpValueFixture::class, $value);
        self::assertSame('42', $value->id);
        self::assertSame('term', $value->search);
        self::assertSame('trace-1', $value->trace);
        self::assertSame('Ada', $value->name);
        self::assertSame('hello', $value->note);
    }

    public function testDynamicPathRoutePassesPathParametersToBinder(): void
    {
        $dispatcher = new RecordingDispatcher(OperationResult::completed(new WelcomeShown('ok')));
        $handler = new OperationRequestHandler(
            new HttpRouteRegistry([
                new HttpOperationRoute('GET', '/welcome/{name}', new ShowWelcome(), PathWelcomeValue::class),
            ]),
            new OperationValueBinder(),
            $dispatcher,
            new JsonOperationResponder($this->psr17, $this->psr17),
            $this->psr17,
            new NoopValidationRejectionRecorder(),
        );

        $response = $handler->handle($this->request('GET', '/welcome/Ada'));

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(PathWelcomeValue::class, $dispatcher->value);
        self::assertSame('Ada', $dispatcher->value->name);
    }

    private function httpHandler(Dispatcher $dispatcher): RequestHandlerInterface
    {
        $responder = new JsonOperationResponder($this->psr17, $this->psr17);
        $handler = new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute('GET', '/welcome', new ShowWelcome(), WelcomeValue::class)]),
            new OperationValueBinder(),
            $dispatcher,
            $responder,
            $this->psr17,
            $dispatcher instanceof ValidationRejectionRecorder ? $dispatcher : new NoopValidationRejectionRecorder(),
        );
        $scope = new ExecutionScopeProvider();

        return new OperationFailureErrorBoundary(
            $handler,
            $responder,
            new FrameworkOperationFailureReporter(new ExecutionScopedLogger(new NullLogger(), $scope), $scope),
        );
    }

    private function inlineDispatcher(
        OperationHandler $operationHandler,
        PostgreSqlCanonicalJournalStore $journal,
    ): InlineDispatcher {
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-08T00:00:00.000000Z');
            }
        };
        $identifiers = new IdentifierFactory(new HttpSequentialUuidv7Generator(), $clock);
        $container = new class($operationHandler) implements ContainerInterface {
            public function __construct(
                private readonly object $service,
            ) {}

            public function get(string $id): mixed
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        return new InlineDispatcher(
            new OperationRegistry([$this->metadata()]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new JournalRecordFactory($identifiers, $clock),
            $journal,
        );
    }

    private function metadata(): OperationMetadata
    {
        return new OperationMetadata(
            'welcome.show',
            ShowWelcome::class,
            WelcomeValue::class,
            WelcomeHandler::class,
            WelcomeShown::class,
            Inline::class,
        );
    }

    /** @param class-string<Operation> $definition */
    private function pathMetadata(string $typeId, string $definition): OperationMetadata
    {
        return new OperationMetadata(
            $typeId,
            $definition,
            PathWelcomeValue::class,
            WelcomeHandler::class,
            WelcomeShown::class,
            Inline::class,
        );
    }

    /**
     * @return list<JournalRecord>
     */
    private function recordsForOnlyOperation(Connection $connection, PostgreSqlCanonicalJournalStore $journal): array
    {
        $operationId = $connection->fetchOne('SELECT operation_id::text FROM ' . self::SCHEMA . '.journal LIMIT 1');

        self::assertIsString($operationId);

        return array_values(iterator_to_array($journal->records(\BlackOps\Core\Identifier\OperationId::fromString(
            $operationId,
        ))));
    }

    private function request(string $method, string $path, string $body = ''): ServerRequestInterface
    {
        return $this->psr17->createServerRequest($method, $path)->withBody($this->psr17->createStream($body));
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

#[Route(method: 'GET', path: '/welcome')]
final readonly class ShowWelcome implements Operation {}

#[Route(method: 'GET', path: '/welcome/{name}')]
final readonly class PathWelcomeOperation implements Operation {}

#[Route(method: 'GET', path: '/welcome/{id}')]
final readonly class ConflictingWelcomeOperation implements Operation {}

#[Route(method: 'GET', path: '/duplicate')]
final readonly class DuplicateWelcomeOperation implements Operation {}

#[Route(method: 'GET', path: '/duplicate')]
final readonly class SecondDuplicateWelcomeOperation implements Operation {}

#[Route(method: 'GET', path: '/required')]
final readonly class RequiredWelcomeOperation implements Operation, OperationHandler
{
    public function __construct(
        private string $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new WelcomeShown($this->dependency));
    }
}

final readonly class WelcomeValue implements OperationValue {}

final readonly class PathWelcomeValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $name,
    ) {}
}

final readonly class BoundHttpValueFixture implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $id,
        #[FromQuery]
        public string $search,
        #[FromHeader('X-Trace')]
        public string $trace,
        #[FromBody]
        public string $name,
        public string $note,
    ) {}
}

final readonly class WelcomeShown implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

/** @implements OperationHandler<WelcomeValue, WelcomeShown> */
final readonly class WelcomeHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

/** @implements OperationHandler<WelcomeValue, WelcomeShown> */
final readonly class ThrowingWelcomeHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new \RuntimeException('backend credential detail');
    }
}

final readonly class FixedDispatcher implements Dispatcher
{
    public function __construct(
        private OperationResult $result,
    ) {}

    public function dispatch(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): OperationResult {
        return $this->result;
    }
}

final readonly class FailingDispatcher implements Dispatcher
{
    public function dispatch(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): OperationResult {
        self::fail('Dispatcher should not be called.');
    }
}

final class RecordingDispatcher implements Dispatcher
{
    public ?OperationValue $value = null;
    public ?ActorContext $actorContext = null;

    public function __construct(
        private readonly OperationResult $result,
    ) {}

    public function dispatch(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): OperationResult {
        $this->value = $value;
        $this->actorContext = $actorContext;

        return $this->result;
    }
}

final readonly class CompletedDeferredAcceptor implements DeferredOperationAcceptor
{
    public function accepts(Operation $definition): bool
    {
        return true;
    }

    public function accept(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
    ): \BlackOps\Core\Execution\DeferredAcknowledgement|OperationResult {
        return OperationResult::completed();
    }
}

final readonly class NoopValidationRejectionRecorder implements ValidationRejectionRecorder
{
    public function validate(OperationValue $value): array
    {
        return [];
    }

    public function rejectBinding(Operation $definition, array $violations): OperationId
    {
        self::fail('Binding rejection should not be recorded.');
    }

    public function rejectValue(Operation $definition, OperationValue $value, array $violations): OperationId
    {
        self::fail('Value rejection should not be recorded.');
    }
}

final class HttpSequentialUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687698',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687699',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769a',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769b',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769c',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
