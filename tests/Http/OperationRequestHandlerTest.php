<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\ExecutionStrategy;
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
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Retention\RetentionPeriod;
use BlackOps\Execution\Dispatcher;
use BlackOps\Execution\ValidationRejectionRecorder;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use BlackOps\Http\Attribute\Route;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\Binding\OperationValueBindingException;
use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpOperationRoute;
use BlackOps\Http\Routing\HttpRouteCompiler;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Idempotency\IdempotencyKeyHash;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Http\OperationFailureErrorBoundary;
use BlackOps\Internal\Idempotency\IdempotencyClaimResult;
use BlackOps\Internal\Idempotency\IdempotencyRecordState;
use BlackOps\Internal\Idempotency\IdempotencyResponseSnapshot;
use BlackOps\Internal\Idempotency\IdempotencyScopeHash;
use BlackOps\Internal\Idempotency\IdempotencyScopeHasher;
use BlackOps\Internal\Idempotency\IdempotencyStore;
use BlackOps\Internal\Idempotency\InMemoryIdempotencyStore;
use BlackOps\Internal\Idempotency\OperationFingerprint;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use BlackOps\Internal\Idempotency\ProcessingRecord;
use BlackOps\Internal\Idempotency\TerminalRecord;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;

/** @mago-expect lint:too-many-methods */
final class OperationRequestHandlerTest extends TestCase
{
    private const SCHEMA = 'blackops_p1_017';

    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testRealPostMutationReplayUsesOriginalResponseAndRunsHandlerOnce(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $journal = new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA);
        $journal->migrate();
        $store = new InMemoryIdempotencyStore();
        $operationHandler = new CountingWelcomeHandler();
        $metadata = new OperationMetadata(
            'welcome.mutate',
            ShowWelcome::class,
            WelcomeValue::class,
            $operationHandler::class,
            WelcomeShown::class,
            Inline::class,
        );
        $dispatcher = $this->inlineDispatcher($operationHandler, $journal, $store, RetentionPeriod::days(3), $metadata);
        $responder = new JsonOperationResponder($this->psr17, $this->psr17);
        $requestHandler = new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute('POST', '/welcome', new ShowWelcome(), WelcomeValue::class)]),
            new OperationValueBinder(),
            $dispatcher,
            $responder,
            $this->psr17,
            $dispatcher,
            status: null,
            idempotency: $store,
        );
        $actor = new ActorRef('user-1', 'user');
        $request = $this
            ->request('POST', '/welcome')
            ->withHeader('Idempotency-Key', 'http-replay-key')
            ->withAttribute(ActorRef::class, $actor);
        $first = $requestHandler->handle($request);
        $second = $requestHandler->handle($request);

        self::assertSame(200, $first->getStatusCode());
        self::assertSame((string) $first->getBody(), (string) $second->getBody());
        self::assertSame('true', $second->getHeaderLine('Idempotency-Replayed'));
        self::assertSame('private, no-store', $second->getHeaderLine('Cache-Control'));
        self::assertSame(1, $operationHandler->calls);
    }

    public function testResponderSnapshotKeepsOnlyFrameworkAllowlistAndReplayHeaders(): void
    {
        $response = $this->psr17
            ->createResponse(202)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Location', '/operations/id')
            ->withHeader('Retry-After', '5')
            ->withHeader('Authorization', 'Bearer secret')
            ->withHeader('Cookie', 'session=secret')
            ->withHeader('Set-Cookie', 'session=secret')
            ->withHeader('X-App', 'private');
        $responder = new JsonOperationResponder($this->psr17, $this->psr17);
        $replayed = $responder->respondSnapshot($responder->snapshot($response));

        self::assertSame('application/json', $replayed->getHeaderLine('Content-Type'));
        self::assertSame('/operations/id', $replayed->getHeaderLine('Location'));
        self::assertSame('5', $replayed->getHeaderLine('Retry-After'));
        self::assertSame('true', $replayed->getHeaderLine('Idempotency-Replayed'));
        self::assertSame('private, no-store', $replayed->getHeaderLine('Cache-Control'));
        self::assertFalse($replayed->hasHeader('Authorization'));
        self::assertFalse($replayed->hasHeader('Cookie'));
        self::assertFalse($replayed->hasHeader('Set-Cookie'));
        self::assertFalse($replayed->hasHeader('X-App'));
    }

    public function testRealHttpConflictInProgressAndExpiredResponsesAre409WithoutOperationId(): void
    {
        $operationHandler = new CountingMutationHandler();
        $store = new CountingIdempotencyStore();
        $handler = $this->mutationHttpHandler($operationHandler, $store);
        $actor = new ActorRef('user-1', 'user');
        $firstRequest = $this
            ->request('POST', '/mutate', '{"message":"first"}')
            ->withHeader('Idempotency-Key', 'matrix-conflict')
            ->withAttribute(ActorRef::class, $actor);
        $first = $handler->handle($firstRequest);
        $conflict = $handler->handle(
            $this
                ->request('POST', '/mutate', '{"message":"different"}')
                ->withHeader('Idempotency-Key', 'matrix-conflict')
                ->withAttribute(ActorRef::class, $actor),
        );

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame(
            '{"status":"rejected","category":"conflict","code":"idempotency_conflict"}',
            (string) $conflict->getBody(),
        );
        self::assertStringNotContainsString('operationId', (string) $conflict->getBody());
        self::assertSame(1, $operationHandler->calls);

        $processingStore = new CountingIdempotencyStore();
        $processingHandler = $this->mutationHttpHandler(new CountingMutationHandler(), $processingStore);
        $processingKey = new IdempotencyKey('matrix-processing');
        $processingValue = new HttpMutationValue('processing');
        $processingRecord = $this->seedMutationRecord($processingStore, $actor, $processingKey, $processingValue);
        $processingResponse = $processingHandler->handle(
            $this
                ->request('POST', '/mutate', '{"message":"processing"}')
                ->withHeader('Idempotency-Key', 'matrix-processing')
                ->withAttribute(ActorRef::class, $actor),
        );

        self::assertSame(409, $processingResponse->getStatusCode());
        self::assertSame(
            '{"status":"rejected","category":"conflict","code":"idempotency_in_progress"}',
            (string) $processingResponse->getBody(),
        );
        self::assertStringNotContainsString('operationId', (string) $processingResponse->getBody());
        self::assertSame(1, $processingStore->claims);
        self::assertSame('019f32ab-2be0-7b38-a0a7-1ab2f96876b0', $processingRecord->operationId()->toString());

        $expiredStore = new CountingIdempotencyStore();
        $expiredHandler = $this->mutationHttpHandler(new CountingMutationHandler(), $expiredStore);
        $expiredKey = new IdempotencyKey('matrix-expired');
        $expiredValue = new HttpMutationValue('expired');
        $this->seedMutationRecord($expiredStore, $actor, $expiredKey, $expiredValue, true);
        $expiredResponse = $expiredHandler->handle(
            $this
                ->request('POST', '/mutate', '{"message":"expired"}')
                ->withHeader('Idempotency-Key', 'matrix-expired')
                ->withAttribute(ActorRef::class, $actor),
        );

        self::assertSame(409, $expiredResponse->getStatusCode());
        self::assertSame(
            '{"status":"rejected","category":"conflict","code":"idempotency_expired"}',
            (string) $expiredResponse->getBody(),
        );
        self::assertStringNotContainsString('operationId', (string) $expiredResponse->getBody());
        self::assertSame(1, $expiredStore->claims);
    }

    public function testRealHttpMalformedUnsupportedAndAnonymousRequestsClaimNothing(): void
    {
        $store = new CountingIdempotencyStore();
        $responder = new JsonOperationResponder($this->psr17, $this->psr17);
        $handler = $this->routedHandler(
            new FailingDispatcher(),
            new HttpRouteRegistry([
                new HttpOperationRoute('POST', '/mutate', new MutateWelcomeOperation(), HttpMutationValue::class),
                new HttpOperationRoute('GET', '/welcome', new ShowWelcome(), WelcomeValue::class),
                new HttpOperationRoute('HEAD', '/head', new ShowWelcome(), WelcomeValue::class),
                new HttpOperationRoute('POST', '/ephemeral', new ShowWelcome(), WelcomeValue::class, null, true),
            ]),
            $store,
            $responder,
        );

        foreach ([
            'empty' => $this->request('POST', '/mutate', '{"message":"empty"}')->withHeader('Idempotency-Key', ''),
            'comma' => $this->request('POST', '/mutate', '{"message":"comma"}')->withHeader(
                'Idempotency-Key',
                'one,two',
            ),
            'multiple' => $this->request('POST', '/mutate', '{"message":"multiple"}')->withHeader('Idempotency-Key', [
                'one',
                'two',
            ]),
            'get' => $this->request('GET', '/welcome')->withHeader('Idempotency-Key', 'get-key'),
            'head' => $this->request('HEAD', '/head')->withHeader('Idempotency-Key', 'head-key'),
            'ephemeral' => $this->request('POST', '/ephemeral')->withHeader('Idempotency-Key', 'ephemeral-key'),
        ] as $name => $request) {
            $response = $handler->handle($request);

            self::assertSame(400, $response->getStatusCode(), $name);
            self::assertContains(
                json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR)['code'],
                ['invalid_idempotency_key', 'idempotency_not_supported'],
                $name,
            );
        }

        self::assertSame(0, $store->claims);

        $anonymousStore = new CountingIdempotencyStore();
        $anonymousOperationHandler = new CountingMutationHandler();
        $anonymousHandler = $this->mutationHttpHandler($anonymousOperationHandler, $anonymousStore);
        $anonymousResponse = $anonymousHandler->handle($this->request(
            'POST',
            '/mutate',
            '{"message":"anonymous"}',
        )->withHeader('Idempotency-Key', 'anonymous-key'));

        self::assertSame(400, $anonymousResponse->getStatusCode());
        self::assertSame(
            'idempotency_requires_authenticated_actor',
            json_decode((string) $anonymousResponse->getBody(), true, flags: JSON_THROW_ON_ERROR)['code'],
        );
        self::assertSame(0, $anonymousStore->claims);
        self::assertSame(0, $anonymousOperationHandler->calls);
    }

    public function testRealHttpInternalFailureIsSafelyTerminalizedAndReplayed(): void
    {
        $store = new InMemoryIdempotencyStore();
        $operationHandler = new CountingThrowingMutationHandler();
        [$handler, $journal] = $this->mutationHttpBoundaryRuntime($operationHandler, $store);
        $actor = new ActorRef('user-1', 'user');
        $request = $this
            ->request('POST', '/mutate', '{"message":"failure"}')
            ->withHeader('Idempotency-Key', 'http-failure-key')
            ->withAttribute(ActorRef::class, $actor);

        $first = $handler->handle($request);
        $firstPayload = json_decode((string) $first->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(500, $first->getStatusCode());
        self::assertSame('error', $firstPayload['status']);
        self::assertSame('internal_error', $firstPayload['code']);
        self::assertArrayHasKey('operationId', $firstPayload);
        self::assertStringNotContainsString('backend credential detail', (string) $first->getBody());
        self::assertStringNotContainsString(CountingThrowingMutationHandler::class, (string) $first->getBody());
        self::assertStringNotContainsString('RuntimeException', (string) $first->getBody());
        self::assertFalse($first->hasHeader('Idempotency-Replayed'));

        $operationId = OperationId::fromString($firstPayload['operationId']);
        $scope = new IdempotencyScopeHasher()->hash('mutate.welcome', $actor, new IdempotencyKey('http-failure-key'));
        $record = $store->find($scope);
        self::assertInstanceOf(TerminalRecord::class, $record);
        self::assertNotNull($record->result());
        self::assertTrue($record->result()->isInternalFailure());
        self::assertSame($operationId->toString(), $record->result()->internalFailureOperationId()->toString());
        self::assertNotNull($store->response($operationId));
        $journalCount = count(iterator_to_array($journal->records($operationId)));

        $second = $handler->handle($request);
        self::assertSame(500, $second->getStatusCode());
        self::assertSame((string) $first->getBody(), (string) $second->getBody());
        self::assertSame('true', $second->getHeaderLine('Idempotency-Replayed'));
        self::assertSame('private, no-store', $second->getHeaderLine('Cache-Control'));
        self::assertSame($journalCount, count(iterator_to_array($journal->records($operationId))));
        self::assertSame(1, $operationHandler->calls);
    }

    public function testRealHttpAttachResponseFailureReturnsSafeCorrelated500(): void
    {
        $store = new AttachFailureIdempotencyStore();
        $operationHandler = new CountingMutationHandler();
        [$handler] = $this->mutationHttpBoundaryRuntime($operationHandler, $store);
        $response = $handler->handle(
            $this
                ->request('POST', '/mutate', '{"message":"attach-failure"}')
                ->withHeader('Idempotency-Key', 'attach-failure-key')
                ->withAttribute(ActorRef::class, new ActorRef('user-1', 'user')),
        );
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('error', $payload['status']);
        self::assertSame('internal_error', $payload['code']);
        self::assertArrayHasKey('operationId', $payload);
        self::assertStringNotContainsString('mutation-completed', (string) $response->getBody());
        self::assertStringNotContainsString('attach-failure', (string) $response->getBody());
        self::assertSame(1, $operationHandler->calls);
    }

    public function testHandlerOriginForbiddenResultIsSnapshottedAndReplayed(): void
    {
        $store = new InMemoryIdempotencyStore();
        [$handler] = $this->mutationHttpBoundaryRuntime(new ForbiddenMutationHandler(), $store);
        $request = $this
            ->request('POST', '/mutate', '{"message":"forbidden"}')
            ->withHeader('Idempotency-Key', 'handler-forbidden-key')
            ->withAttribute(ActorRef::class, new ActorRef('user-1', 'user'));

        $first = $handler->handle($request);
        $second = $handler->handle($request);

        self::assertSame(403, $first->getStatusCode());
        self::assertSame(403, $second->getStatusCode());
        self::assertSame((string) $first->getBody(), (string) $second->getBody());
        self::assertStringContainsString('handler.forbidden', (string) $first->getBody());
        self::assertSame('true', $second->getHeaderLine('Idempotency-Replayed'));
        self::assertSame('private, no-store', $second->getHeaderLine('Cache-Control'));
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

    public function testKeyedGetAndMalformedHeaderAreRejectedBeforeDispatch(): void
    {
        $dispatcher = new FailingDispatcher();
        $handler = $this->httpHandler($dispatcher);

        $unsupported = $handler->handle($this->request('GET', '/welcome')->withHeader('Idempotency-Key', 'retry-1'));
        self::assertSame(400, $unsupported->getStatusCode());
        self::assertSame('idempotency_not_supported', json_decode((string) $unsupported->getBody(), true)['code']);

        $malformed = $handler->handle($this->request('GET', '/welcome')->withHeader('Idempotency-Key', 'bad,key'));
        self::assertSame(400, $malformed->getStatusCode());
        self::assertSame('invalid_idempotency_key', json_decode((string) $malformed->getBody(), true)['code']);
    }

    public function testReplayedResultProjectsOnlyFrameworkReplayHeaders(): void
    {
        $handler = $this->httpHandler(
            new FixedDispatcher(
                OperationResult::completed(
                    new WelcomeShown('replayed'),
                    OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697'),
                )->asReplayed(),
            ),
        );

        $response = $handler->handle($this->request('GET', '/welcome'));

        self::assertSame('true', $response->getHeaderLine('Idempotency-Replayed'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('', $response->getHeaderLine('Location'));
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

    public function testZeroFieldNonEmptyOutcomeIsAJsonObject(): void
    {
        $response = $this->httpHandler(new FixedDispatcher(OperationResult::completed(
            new ZeroFieldOutcomeFixture(),
        )))->handle($this->request('GET', '/welcome'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{}', (string) $response->getBody());
    }

    public function testEphemeralOutcomeReturnsExactJsonAndEmptyEphemeralOutcomeReturnsObject(): void
    {
        foreach ([
            [new HttpEphemeralOutcome('raw-secret-must-not-appear'), '{"token":"raw-secret-must-not-appear"}'],
            [new EmptyHttpEphemeralOutcome(), '{}'],
        ] as [$outcome, $expected]) {
            $handler = new OperationRequestHandler(
                new HttpRouteRegistry([new HttpOperationRoute(
                    'GET',
                    '/welcome',
                    new ShowWelcome(),
                    WelcomeValue::class,
                    $outcome::class,
                    true,
                )]),
                new OperationValueBinder(),
                new FixedDispatcher(OperationResult::completed($outcome)),
                new JsonOperationResponder($this->psr17, $this->psr17),
                $this->psr17,
                new NoopValidationRejectionRecorder(),
            );

            $response = $handler->handle($this->request('GET', '/welcome'));
            self::assertSame(200, $response->getStatusCode());
            self::assertSame($expected, (string) $response->getBody());
        }
    }

    public function testHttpManifestMismatchFailsWithoutDumpingEphemeralValue(): void
    {
        $handler = new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute(
                'GET',
                '/welcome',
                new ShowWelcome(),
                WelcomeValue::class,
                WelcomeShown::class,
                false,
            )]),
            new OperationValueBinder(),
            new FixedDispatcher(OperationResult::completed(new HttpEphemeralOutcome('raw-secret-must-not-appear'))),
            new JsonOperationResponder($this->psr17, $this->psr17),
            $this->psr17,
            new NoopValidationRejectionRecorder(),
        );

        try {
            $handler->handle($this->request('GET', '/welcome'));
            self::fail('Expected HTTP manifest mismatch.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('manifest contract', $exception->getMessage());
            self::assertStringNotContainsString('raw-secret-must-not-appear', $exception->getMessage());
        }
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

    public function testUnknownGetRouteWithBodyKeepsNotFoundPrecedence(): void
    {
        $handler = $this->httpHandler(new FailingDispatcher());

        $response = $handler->handle($this->request('GET', '/missing', 'body'));

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

    /** @param class-string<Operation> $definition */
    #[DataProvider('reservedStatusRouteDefinitions')]
    public function testRouteCompilerRejectsReservedOperationStatusCollisions(string $definition): void
    {
        $registry = new OperationRegistry([$this->pathMetadata('status.route.collision', $definition)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('framework reserved resource');

        new HttpRouteCompiler($registry)->compileManifest([$definition]);
    }

    /** @return iterable<string, array{class-string<Operation>}> */
    public static function reservedStatusRouteDefinitions(): iterable
    {
        yield 'canonical parameter' => [ReservedCanonicalStatusOperation::class];
        yield 'renamed parameter' => [ReservedRenamedStatusOperation::class];
        yield 'static segment' => [ReservedStaticStatusOperation::class];
    }

    public function testRouteCompilerKeepsNonConflictingMethodsAndSegmentCounts(): void
    {
        $registry = new OperationRegistry([
            $this->pathMetadata('status.route.post', NonConflictingStatusPostOperation::class),
            $this->pathMetadata('status.route.nested', NonConflictingNestedStatusOperation::class),
        ]);

        $manifest = new HttpRouteCompiler($registry)->compileManifest([
            NonConflictingStatusPostOperation::class,
            NonConflictingNestedStatusOperation::class,
        ]);

        self::assertSame('status.route.post', $manifest->routes['POST']['/operations/{operationId}']);
        self::assertSame('status.route.nested', $manifest->routes['GET']['/operations/{operationId}/outcome']);
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

    public function testStructuredOutcomeSupportDoesNotEnableArrayOperationValueInput(): void
    {
        try {
            new OperationValueBinder()->bind(ArrayInputValueFixture::class, $this->request(
                'POST',
                '/items',
                '{"items":[{"id":"item-1"}]}',
            ));
            self::fail('Expected array input binding to remain unsupported.');
        } catch (OperationValueBindingException $exception) {
            self::assertSame('binding.type', $exception->violations()[0]->code);
        }
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
        ?IdempotencyStore $idempotency = null,
        ?RetentionPeriod $retention = null,
        ?OperationMetadata $metadata = null,
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
            new OperationRegistry([$metadata ?? $this->metadata($operationHandler)]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new JournalRecordFactory($identifiers, $clock),
            $journal,
            idempotency: $idempotency,
            idempotencyRetention: $retention,
        );
    }

    private function metadata(?OperationHandler $handler = null): OperationMetadata
    {
        return new OperationMetadata(
            'welcome.show',
            ShowWelcome::class,
            WelcomeValue::class,
            $handler === null ? WelcomeHandler::class : $handler::class,
            WelcomeShown::class,
            Inline::class,
        );
    }

    private function mutationHttpHandler(
        OperationHandler $operationHandler,
        CountingIdempotencyStore $store,
    ): OperationRequestHandler {
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $journal = new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA);
        $journal->migrate();
        $dispatcher = $this->inlineDispatcher(
            $operationHandler,
            $journal,
            $store,
            RetentionPeriod::days(3),
            $this->mutationMetadata($operationHandler),
        );

        return new OperationRequestHandler(
            new HttpRouteRegistry([
                new HttpOperationRoute('POST', '/mutate', new MutateWelcomeOperation(), HttpMutationValue::class),
            ]),
            new OperationValueBinder(),
            $dispatcher,
            new JsonOperationResponder($this->psr17, $this->psr17),
            $this->psr17,
            $dispatcher,
            status: null,
            idempotency: $store,
        );
    }

    /** @return array{RequestHandlerInterface, PostgreSqlCanonicalJournalStore} */
    private function mutationHttpBoundaryRuntime(OperationHandler $operationHandler, IdempotencyStore $store): array
    {
        $connection = $this->connection();
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA . ' CASCADE');
        $journal = new PostgreSqlCanonicalJournalStore($connection, self::SCHEMA);
        $journal->migrate();
        $dispatcher = $this->inlineDispatcher(
            $operationHandler,
            $journal,
            $store,
            RetentionPeriod::days(3),
            $this->mutationMetadata($operationHandler),
        );
        $responder = new JsonOperationResponder($this->psr17, $this->psr17);
        $requestHandler = new OperationRequestHandler(
            new HttpRouteRegistry([
                new HttpOperationRoute('POST', '/mutate', new MutateWelcomeOperation(), HttpMutationValue::class),
            ]),
            new OperationValueBinder(),
            $dispatcher,
            $responder,
            $this->psr17,
            $dispatcher,
            status: null,
            idempotency: $store,
        );
        $scope = new ExecutionScopeProvider();

        return [
            new OperationFailureErrorBoundary(
                $requestHandler,
                $responder,
                new FrameworkOperationFailureReporter(new ExecutionScopedLogger(new NullLogger(), $scope), $scope),
                $store,
            ),
            $journal,
        ];
    }

    private function mutationMetadata(OperationHandler $handler): OperationMetadata
    {
        return new OperationMetadata(
            'mutate.welcome',
            MutateWelcomeOperation::class,
            HttpMutationValue::class,
            $handler::class,
            WelcomeShown::class,
            Inline::class,
        );
    }

    private function seedMutationRecord(
        CountingIdempotencyStore $store,
        ActorRef $actor,
        IdempotencyKey $key,
        HttpMutationValue $value,
        bool $expired = false,
    ): ProcessingRecord {
        $scope = new IdempotencyScopeHasher()->hash('mutate.welcome', $actor, $key);
        $fingerprint = new OperationValueFingerprinter()->fingerprint('mutate.welcome', $value);
        $createdAt = new DateTimeImmutable($expired ? '2026-07-06T00:00:00Z' : '2026-07-08T00:00:00Z');
        $expiresAt = new DateTimeImmutable($expired ? '2026-07-07T00:00:00Z' : '2026-07-09T00:00:00Z');
        $operationId = OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f96876b0');
        $claim = $store->seedClaim(
            $scope,
            $key->hash(),
            $fingerprint,
            $operationId,
            new Inline(),
            $createdAt,
            $expiresAt,
        );
        self::assertSame(\BlackOps\Internal\Idempotency\IdempotencyClaimStatus::Claimed, $claim->status());
        self::assertInstanceOf(ProcessingRecord::class, $claim->record());
        if ($expired) {
            self::assertTrue($store->terminalize(
                $operationId,
                new TerminalRecord(
                    $scope,
                    $key->hash(),
                    $fingerprint,
                    $operationId,
                    new Inline(),
                    $createdAt,
                    $expiresAt,
                ),
            ));
        }

        return $claim->record();
    }

    private function routedHandler(
        Dispatcher $dispatcher,
        HttpRouteRegistry $routes,
        CountingIdempotencyStore $store,
        JsonOperationResponder $responder,
    ): OperationRequestHandler {
        return new OperationRequestHandler(
            $routes,
            new OperationValueBinder(),
            $dispatcher,
            $responder,
            $this->psr17,
            new NoopValidationRejectionRecorder(),
            status: null,
            idempotency: $store,
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

#[Route(method: 'GET', path: '/operations/{operationId}')]
final readonly class ReservedCanonicalStatusOperation implements Operation {}

#[Route(method: 'GET', path: '/operations/{id}')]
final readonly class ReservedRenamedStatusOperation implements Operation {}

#[Route(method: 'GET', path: '/operations/example')]
final readonly class ReservedStaticStatusOperation implements Operation {}

#[Route(method: 'POST', path: '/operations/{operationId}')]
final readonly class NonConflictingStatusPostOperation implements Operation {}

#[Route(method: 'GET', path: '/operations/{operationId}/outcome')]
final readonly class NonConflictingNestedStatusOperation implements Operation {}

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

final readonly class MutateWelcomeOperation implements Operation {}

final readonly class HttpMutationValue implements OperationValue
{
    public function __construct(
        #[FromBody]
        public string $message,
    ) {}
}

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

final readonly class ArrayInputValueFixture implements OperationValue
{
    /** @param list<array{id: string}> $items */
    public function __construct(
        public array $items,
    ) {}
}

final readonly class WelcomeShown implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class ZeroFieldOutcomeFixture implements Outcome {}

final readonly class HttpEphemeralOutcome implements EphemeralOutcome
{
    public function __construct(
        #[\BlackOps\Core\Attribute\Sensitive]
        #[\SensitiveParameter]
        public string $token,
    ) {}
}

final readonly class EmptyHttpEphemeralOutcome implements EphemeralOutcome {}

/** @implements OperationHandler<WelcomeValue, WelcomeShown> */
final readonly class WelcomeHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

final class CountingWelcomeHandler implements OperationHandler
{
    public int $calls = 0;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;

        return OperationResult::completed(new WelcomeShown('Welcome to BlackOps'));
    }
}

final class CountingMutationHandler implements OperationHandler
{
    public int $calls = 0;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;

        return OperationResult::completed(new WelcomeShown('mutation-completed'));
    }
}

final class CountingThrowingMutationHandler implements OperationHandler
{
    public int $calls = 0;

    public function handle(OperationEnvelope $operation): OperationResult
    {
        ++$this->calls;

        throw new \RuntimeException('backend credential detail');
    }
}

final class ForbiddenMutationHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::rejected(RejectionReason::forbidden('handler.forbidden'));
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
        ?IdempotencyKey $idempotencyKey = null,
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
        ?IdempotencyKey $idempotencyKey = null,
    ): OperationResult {
        self::fail('Dispatcher should not be called.');
    }
}

final class CountingIdempotencyStore implements IdempotencyStore
{
    private readonly InMemoryIdempotencyStore $inner;

    public int $claims = 0;

    public function __construct()
    {
        $this->inner = new InMemoryIdempotencyStore();
    }

    public function claim(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult {
        ++$this->claims;

        return $this->inner->claim($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt);
    }

    public function seedClaim(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult {
        return $this->inner->claim($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt);
    }

    public function terminalize(
        OperationId $operationId,
        TerminalRecord $record,
        IdempotencyRecordState $expectedState = IdempotencyRecordState::Processing,
    ): bool {
        return $this->inner->terminalize($operationId, $record, $expectedState);
    }

    public function find(IdempotencyScopeHash $scope): ProcessingRecord|TerminalRecord|null
    {
        return $this->inner->find($scope);
    }

    public function attachResponse(OperationId $operationId, IdempotencyResponseSnapshot $snapshot): bool
    {
        return $this->inner->attachResponse($operationId, $snapshot);
    }

    public function response(OperationId $operationId): ?IdempotencyResponseSnapshot
    {
        return $this->inner->response($operationId);
    }
}

final class AttachFailureIdempotencyStore implements IdempotencyStore
{
    private readonly InMemoryIdempotencyStore $inner;

    public function __construct()
    {
        $this->inner = new InMemoryIdempotencyStore();
    }

    public function claim(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult {
        return $this->inner->claim($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt);
    }

    public function terminalize(
        OperationId $operationId,
        TerminalRecord $record,
        IdempotencyRecordState $expectedState = IdempotencyRecordState::Processing,
    ): bool {
        return $this->inner->terminalize($operationId, $record, $expectedState);
    }

    public function find(IdempotencyScopeHash $scope): ProcessingRecord|TerminalRecord|null
    {
        return $this->inner->find($scope);
    }

    public function attachResponse(OperationId $operationId, IdempotencyResponseSnapshot $snapshot): bool
    {
        return false;
    }

    public function response(OperationId $operationId): ?IdempotencyResponseSnapshot
    {
        return $this->inner->response($operationId);
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
        ?IdempotencyKey $idempotencyKey = null,
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
        ?IdempotencyKey $idempotencyKey = null,
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
        '019f32ab-2be0-7b38-a0a7-1ab2f968769d',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769e',
        '019f32ab-2be0-7b38-a0a7-1ab2f968769f',
        '019f32ab-2be0-7b38-a0a7-1ab2f96876a0',
        '019f32ab-2be0-7b38-a0a7-1ab2f96876a1',
        '019f32ab-2be0-7b38-a0a7-1ab2f96876a2',
        '019f32ab-2be0-7b38-a0a7-1ab2f96876a3',
        '019f32ab-2be0-7b38-a0a7-1ab2f96876a4',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
