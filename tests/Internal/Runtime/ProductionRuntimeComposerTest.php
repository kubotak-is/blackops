<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Http\Authentication\AuthenticationMiddleware;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Http\Routing\FastRouteDispatcherDataCompiler;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Internal\Registry\OperationMetadataCompiler;
use BlackOps\Internal\Runtime\ProductionRuntimeArtifacts;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use BlackOps\Internal\Runtime\ProductionRuntimeDependencies;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Logging\JsonlJournalObserver;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusQuery;
use BlackOps\Status\OperationStatusResult;
use BlackOps\Status\OperationStatusUnavailable;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

final class ProductionRuntimeComposerTest extends TestCase
{
    public function testComposesHttpHandlerDispatcherAndJournalWriterFromRuntimeArtifacts(): void
    {
        $journal = new RuntimeCompositionJournalWriter();
        $psr17 = new Psr17Factory();
        $composition = new ProductionRuntimeComposer()->compose(
            $this->artifacts(),
            new RuntimeCompositionClock(),
            $journal,
            $psr17,
            $psr17,
        );

        $match = $composition->httpRoutes->match('GET', '/composition');
        $response = $composition->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));

        self::assertNotNull($match);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertInstanceOf(ExecutionScopeProvider::class, $composition->executionScope);
    }

    public function testComposesSharedLoggingScopeAndJournalObservationPipeline(): void
    {
        $journal = new RuntimeCompositionJournalWriter();
        $scope = new ExecutionScopeProvider();
        $innerLogger = new RuntimeCompositionPsrLogger();
        $stream = self::stream();
        $psr17 = new Psr17Factory();
        $handler = new RuntimeLoggingCompositionHandler(new ExecutionScopedLogger($innerLogger, $scope));

        new ProductionRuntimeComposer()
            ->composeWithDependencies(
                $this->artifacts($handler),
                new ProductionRuntimeDependencies(
                    new RuntimeCompositionClock(),
                    $journal,
                    $psr17,
                    $psr17,
                    $scope,
                    new JournalObservationPipeline(
                        new ObservedJournalRecordProjector(new SensitiveProjectionFilter('projection-key')),
                        new JournalObserverAggregator([
                            new JournalObserverBinding('jsonl', new JsonlJournalObserver($stream)),
                        ]),
                    ),
                ),
            )
            ->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));

        rewind($stream);
        $lines = array_values(array_filter(explode("\n", stream_get_contents($stream))));

        self::assertCount(4, $lines);
        self::assertCount(1, $innerLogger->records);
        self::assertSame('runtime.composition', $innerLogger->records[0]['context']['operation']['type']);
        self::assertSame(
            'runtime.composition',
            json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR)['operation']['type'],
        );
    }

    public function testOperationFailureReturnsSafeCorrelatedHttpErrorAndFrameworkLog(): void
    {
        $journal = new RuntimeCompositionJournalWriter();
        $scope = new ExecutionScopeProvider();
        $backend = new RuntimeCompositionPsrLogger();
        $logger = new ExecutionScopedLogger($backend, $scope);
        $psr17 = new Psr17Factory();
        $composition = new ProductionRuntimeComposer()->composeWithDependencies(
            $this->artifacts(new ThrowingRuntimeCompositionHandler()),
            new ProductionRuntimeDependencies(
                new RuntimeCompositionClock(),
                $journal,
                $psr17,
                $psr17,
                executionScope: $scope,
                executionLogger: $logger,
            ),
        );

        $response = $composition->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));
        $payload = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('error', $payload['status']);
        self::assertSame('internal_error', $payload['code']);
        self::assertSame($journal->records[0]->operation->id->toString(), $payload['operationId']);
        self::assertStringNotContainsString('credential', (string) $response->getBody());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
        self::assertCount(1, $backend->records);
        self::assertSame('Operation failed.', $backend->records[0]['message']);
        self::assertSame('framework', $backend->records[0]['context']['kind']);
        self::assertSame($payload['operationId'], $backend->records[0]['context']['operation']['id']);
        self::assertSame(\RuntimeException::class, $backend->records[0]['context']['context']['failure']['type']);
        self::assertStringNotContainsString('credential', serialize($backend->records));
        self::assertNull($scope->current());
    }

    public function testLoggerBackendFailureDoesNotChangeTerminalLifecycleOrHttpError(): void
    {
        $journal = new RuntimeCompositionJournalWriter();
        $scope = new ExecutionScopeProvider();
        $logger = new ExecutionScopedLogger(new FailingRuntimeCompositionPsrLogger(), $scope);
        $psr17 = new Psr17Factory();
        $composition = new ProductionRuntimeComposer()->composeWithDependencies(
            $this->artifacts(new ThrowingRuntimeCompositionHandler()),
            new ProductionRuntimeDependencies(
                new RuntimeCompositionClock(),
                $journal,
                $psr17,
                $psr17,
                executionScope: $scope,
                executionLogger: $logger,
            ),
        );

        $response = $composition->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptFailed,
                JournalEvent::OperationFailed,
            ],
            array_column($journal->records, 'event'),
        );
        self::assertNull($scope->current());
    }

    public function testUsesContainerResolvedSelfHandledOperationAsHttpDefinition(): void
    {
        $dependency = new RuntimeCompositionDependency('container-resolved');
        $handler = new RequiredRuntimeCompositionOperation($dependency);
        $routes = ['GET' => ['/required' => 'runtime.required']];
        $artifacts = new ProductionRuntimeArtifacts(
            new OperationRegistry([new OperationMetadataCompiler()->compile(RequiredRuntimeCompositionOperation::class)]),
            new HttpOperationManifest(
                $routes,
                [
                    'runtime.required' => [
                        'definition' => RequiredRuntimeCompositionOperation::class,
                        'value' => RuntimeCompositionValue::class,
                        'handler' => RequiredRuntimeCompositionOperation::class,
                        'outcome' => EmptyOutcome::class,
                        'strategy' => Inline::class,
                    ],
                ],
                new FastRouteDispatcherDataCompiler()->compile($routes),
            ),
            new RuntimeCompositionContainer($handler),
        );
        $psr17 = new Psr17Factory();

        $composition = new ProductionRuntimeComposer()->compose(
            $artifacts,
            new RuntimeCompositionClock(),
            new RuntimeCompositionJournalWriter(),
            $psr17,
            $psr17,
        );
        $match = $composition->httpRoutes->match('GET', '/required');
        $response = $composition->httpHandler->handle($psr17->createServerRequest('GET', '/required'));

        self::assertNotNull($match);
        self::assertSame($handler, $match->route->operation);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('container-resolved', $handler->handledWith);
    }

    public function testUsesContainerResolvedProxySubclassAsHttpDefinition(): void
    {
        $dependency = new RuntimeCompositionDependency('proxy-resolved');
        $handler = new ProxiedRequiredRuntimeCompositionOperation($dependency);
        $routes = ['GET' => ['/required' => 'runtime.required']];
        $artifacts = new ProductionRuntimeArtifacts(
            new OperationRegistry([new OperationMetadataCompiler()->compile(RequiredRuntimeCompositionOperation::class)]),
            new HttpOperationManifest(
                $routes,
                [
                    'runtime.required' => [
                        'definition' => RequiredRuntimeCompositionOperation::class,
                        'value' => RuntimeCompositionValue::class,
                        'handler' => RequiredRuntimeCompositionOperation::class,
                        'outcome' => EmptyOutcome::class,
                        'strategy' => Inline::class,
                    ],
                ],
                new FastRouteDispatcherDataCompiler()->compile($routes),
            ),
            new RuntimeCompositionContainer($handler, handlerId: RequiredRuntimeCompositionOperation::class),
        );
        $psr17 = new Psr17Factory();

        $composition = new ProductionRuntimeComposer()->compose(
            $artifacts,
            new RuntimeCompositionClock(),
            new RuntimeCompositionJournalWriter(),
            $psr17,
            $psr17,
        );
        $match = $composition->httpRoutes->match('GET', '/required');
        $response = $composition->httpHandler->handle($psr17->createServerRequest('GET', '/required'));

        self::assertNotNull($match);
        self::assertSame($handler, $match->route->operation);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('proxy-resolved', $handler->handledWith);
    }

    public function testConnectsConfiguredHttpMiddlewareToProductionHandler(): void
    {
        $psr17 = new Psr17Factory();
        $runtime = new ProductionRuntimeComposer()->composeWithDependencies($this->artifacts(), new ProductionRuntimeDependencies(
            new RuntimeCompositionClock(),
            new RuntimeCompositionJournalWriter(),
            $psr17,
            $psr17,
            httpMiddleware: [new RuntimeHeaderMiddleware()],
        ));

        $response = $runtime->httpHandler->handle($psr17->createServerRequest('GET', '/composition'));

        self::assertSame('ready', $response->getHeaderLine('X-Runtime-Middleware'));
    }

    public function testStatusResourceRunsInsideGlobalMiddlewareAndAuthentication(): void
    {
        $actor = new ActorRef('status-user', 'user');
        $query = new RuntimeStatusQuery(new OperationStatusFound(OperationStatus::accepted(
            \BlackOps\Core\Identifier\OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697'),
            'runtime.composition',
        )));
        $psr17 = new Psr17Factory();
        $runtime = new ProductionRuntimeComposer()->composeWithDependencies(
            $this->artifacts(),
            new ProductionRuntimeDependencies(
                new RuntimeCompositionClock(),
                new RuntimeCompositionJournalWriter(),
                $psr17,
                $psr17,
                httpMiddleware: [
                    new RuntimeHeaderMiddleware(),
                    new AuthenticationMiddleware(
                        new RuntimeHttpAuthenticator(AuthenticationResult::authenticated($actor)),
                        $psr17,
                        $psr17,
                    ),
                ],
                operationStatusQuery: $query,
            ),
        );

        $response = $runtime->httpHandler->handle($psr17->createServerRequest(
            'GET',
            '/operations/019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ready', $response->getHeaderLine('X-Runtime-Middleware'));
        self::assertSame($actor, $query->actor);
        self::assertSame(1, $query->calls);
    }

    public function testInvalidCredentialReturnsExistingUnauthorizedResponseBeforeStatusQuery(): void
    {
        $query = new RuntimeStatusQuery(new OperationStatusUnavailable());
        $psr17 = new Psr17Factory();
        $runtime = new ProductionRuntimeComposer()->composeWithDependencies(
            $this->artifacts(),
            new ProductionRuntimeDependencies(
                new RuntimeCompositionClock(),
                new RuntimeCompositionJournalWriter(),
                $psr17,
                $psr17,
                httpMiddleware: [new AuthenticationMiddleware(
                    new RuntimeHttpAuthenticator(AuthenticationResult::invalid('authentication.invalid')),
                    $psr17,
                    $psr17,
                )],
                operationStatusQuery: $query,
            ),
        );

        $response = $runtime->httpHandler->handle($psr17->createServerRequest(
            'GET',
            '/operations/019f32ab-2be0-7b38-a0a7-1ab2f9687697',
        ));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(0, $query->calls);
        self::assertStringContainsString('authentication.invalid', (string) $response->getBody());
    }

    public function testStatusGetWithBodyReturnsProtocolErrorBeforeStatusQuery(): void
    {
        $query = new RuntimeStatusQuery(new OperationStatusUnavailable());
        $psr17 = new Psr17Factory();
        $runtime = new ProductionRuntimeComposer()->composeWithDependencies(
            $this->artifacts(),
            new ProductionRuntimeDependencies(
                new RuntimeCompositionClock(),
                new RuntimeCompositionJournalWriter(),
                $psr17,
                $psr17,
                operationStatusQuery: $query,
            ),
        );

        $response = $runtime->httpHandler->handle(
            $psr17
                ->createServerRequest('GET', '/operations/019f32ab-2be0-7b38-a0a7-1ab2f9687697')
                ->withBody($psr17->createStream('body')),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(0, $query->calls);
    }

    public function testConnectsCompiledAuthorizationPolicyAndHttpActorToInlineRuntime(): void
    {
        $policy = new RuntimeCompositionAuthorizationPolicy();
        $psr17 = new Psr17Factory();
        $runtime = new ProductionRuntimeComposer()->compose(
            $this->artifacts(policy: $policy),
            new RuntimeCompositionClock(),
            new RuntimeCompositionJournalWriter(),
            $psr17,
            $psr17,
        );
        $actor = new ActorRef('user-123', 'user');

        $response = $runtime->httpHandler->handle($psr17->createServerRequest('GET', '/composition')->withAttribute(
            ActorRef::class,
            $actor,
        ));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame($actor, $policy->request?->actor());
        self::assertSame($actor, $policy->request?->context()->actorContext()?->origin());
        self::assertSame($actor, $policy->request?->context()->actorContext()?->authorization());
        self::assertSame($actor, $policy->request?->context()->actorContext()?->execution());
    }

    private function artifacts(
        ?OperationHandler $handler = null,
        ?AuthorizationPolicy $policy = null,
    ): ProductionRuntimeArtifacts {
        $handler ??= new RuntimeCompositionHandler();
        $routes = [
            'GET' => [
                '/composition' => 'runtime.composition',
            ],
        ];

        return new ProductionRuntimeArtifacts(
            new OperationRegistry([new OperationMetadata(
                'runtime.composition',
                RuntimeCompositionOperation::class,
                RuntimeCompositionValue::class,
                $handler::class,
                EmptyOutcome::class,
                Inline::class,
                authorizationPolicy: $policy === null ? null : RuntimeCompositionAuthorizationPolicy::class,
            )]),
            new HttpOperationManifest(
                $routes,
                [
                    'runtime.composition' => [
                        'definition' => RuntimeCompositionOperation::class,
                        'value' => RuntimeCompositionValue::class,
                        'handler' => $handler::class,
                        'outcome' => EmptyOutcome::class,
                        'strategy' => Inline::class,
                    ],
                ],
                new FastRouteDispatcherDataCompiler()->compile($routes),
            ),
            new RuntimeCompositionContainer($handler, $policy),
        );
    }

    /**
     * @return resource
     */
    private static function stream(): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        return $stream;
    }
}

final readonly class RuntimeCompositionClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10T00:00:00+00:00');
    }
}

final class RuntimeCompositionJournalWriter implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final readonly class RuntimeCompositionContainer implements ContainerInterface
{
    public function __construct(
        private OperationHandler $handler,
        private ?AuthorizationPolicy $policy = null,
        private ?string $handlerId = null,
    ) {}

    public function get(string $id): mixed
    {
        if ($id === ($this->handlerId ?? $this->handler::class)) {
            return $this->handler;
        }

        if ($this->policy !== null && $id === RuntimeCompositionAuthorizationPolicy::class) {
            return $this->policy;
        }

        throw new \LogicException('Runtime composition service is unavailable.');
    }

    public function has(string $id): bool
    {
        return (
            $id === ($this->handlerId ?? $this->handler::class)
            || $this->policy !== null
            && $id === RuntimeCompositionAuthorizationPolicy::class
        );
    }
}

#[OperationType('runtime.composition')]
#[Accepts(RuntimeCompositionValue::class)]
#[HandledBy(RuntimeCompositionHandler::class)]
#[Returns(EmptyOutcome::class)]
final readonly class RuntimeCompositionOperation implements Operation {}

final readonly class RuntimeCompositionValue implements OperationValue {}

final readonly class RuntimeCompositionHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final class RuntimeCompositionAuthorizationPolicy implements AuthorizationPolicy
{
    public ?AuthorizationRequest $request = null;

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        $this->request = $request;

        return AuthorizationDecision::allow();
    }
}

final readonly class RuntimeLoggingCompositionHandler implements OperationHandler
{
    public function __construct(
        private ExecutionScopedLogger $logger,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->logger->info('runtime handler', ['safe' => 'ok']);

        return OperationResult::completed();
    }
}

final readonly class ThrowingRuntimeCompositionHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new \RuntimeException('runtime backend credential detail');
    }
}

final readonly class RuntimeCompositionDependency
{
    public function __construct(
        public string $value,
    ) {}
}

#[OperationType('runtime.required')]
#[Accepts(RuntimeCompositionValue::class)]
#[Returns(EmptyOutcome::class)]
class RequiredRuntimeCompositionOperation implements Operation, OperationHandler
{
    public ?string $handledWith = null;

    public function __construct(
        private RuntimeCompositionDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        $this->handledWith = $this->dependency->value;

        return OperationResult::completed();
    }
}

final class ProxiedRequiredRuntimeCompositionOperation extends RequiredRuntimeCompositionOperation {}

final class RuntimeCompositionPsrLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|Stringable, context: array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}

final class FailingRuntimeCompositionPsrLogger extends AbstractLogger
{
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        throw new \RuntimeException('logger backend credential detail');
    }
}

final readonly class RuntimeHeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Runtime-Middleware', 'ready');
    }
}

final class RuntimeStatusQuery implements OperationStatusQuery
{
    public int $calls = 0;
    public ?ActorRef $actor = null;

    public function __construct(
        private readonly OperationStatusResult $result,
    ) {}

    public function find(
        \BlackOps\Core\Identifier\OperationId $operationId,
        ?ActorRef $currentActor = null,
    ): OperationStatusResult {
        ++$this->calls;
        $this->actor = $currentActor;

        return $this->result;
    }
}

final readonly class RuntimeHttpAuthenticator implements HttpAuthenticator
{
    public function __construct(
        private AuthenticationResult $result,
    ) {}

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        return $this->result;
    }
}
