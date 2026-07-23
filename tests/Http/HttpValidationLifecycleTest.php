<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Validation\Attribute\Email;
use BlackOps\Core\Validation\Attribute\Regex;
use BlackOps\Execution\Dispatcher;
use BlackOps\Http\Attribute\FromQuery;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Http\OperationRequestHandler;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Routing\HttpOperationRoute;
use BlackOps\Http\Routing\HttpRouteRegistry;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalObservationPipeline;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\ObservedJournalRecord;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HttpValidationLifecycleTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    /** @return iterable<string, array{string, string}> */
    public static function protocolBodies(): iterable
    {
        yield 'malformed json' => ['{"email":', 'http.malformed_json'];
        yield 'json array' => ['["not-an-object"]', 'http.body_not_object'];
        yield 'json scalar' => ['"not-an-object"', 'http.body_not_object'];
    }

    #[DataProvider('protocolBodies')]
    public function testProtocolFailureReturns400WithoutOperationOrJournal(string $body, string $code): void
    {
        $journal = new ValidationRecordingJournal();
        $observer = new ValidationRecordingObserver();
        $handler = $this->handler(Inline::class, $journal, $observer);

        $response = $handler->handle($this->request($body));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"status":"error","code":"' . $code . '"}', (string) $response->getBody());
        self::assertStringNotContainsString('operationId', (string) $response->getBody());
        self::assertSame([], $journal->records);
        self::assertSame([], $observer->records);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function bindingBodies(): iterable
    {
        yield 'missing required field' => [
            '{"email":"reader@example.com","secret":"binding-sensitive-value"}',
            'quantity',
            'required',
        ];
        yield 'native type mismatch' => [
            '{"email":"reader@example.com","quantity":"not-an-int","secret":"binding-sensitive-value"}',
            'quantity',
            'type',
        ];
        yield 'nested value is unsupported' => [
            '{"email":{"raw":"nested-sensitive-value"},"quantity":1,"secret":"binding-sensitive-value"}',
            'email',
            'type',
        ];
    }

    #[DataProvider('bindingBodies')]
    public function testBindingFailureReturnsSafe422AndSingleRejectedRecord(
        string $body,
        string $field,
        string $rule,
    ): void {
        $journal = new ValidationRecordingJournal();
        $observer = new ValidationRecordingObserver();
        $handler = $this->handler(Inline::class, $journal, $observer);

        $response = $handler->handle($this->request($body));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('validation.failed', $payload['code']);
        self::assertSame(
            [[
                'field' => $field,
                'rule' => $rule,
                'code' => 'binding.' . $rule,
            ]],
            $payload['violations'],
        );
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $payload['operationId']);
        self::assertStringNotContainsString('binding-sensitive-value', (string) $response->getBody());
        self::assertStringNotContainsString('nested-sensitive-value', (string) $response->getBody());

        self::assertCount(1, $journal->records);
        self::assertSame(JournalEvent::OperationRejected, $journal->records[0]->event);
        self::assertSame(1, $journal->records[0]->sequence);
        self::assertSame($payload['operationId'], $journal->records[0]->operation->id->toString());
        self::assertInstanceOf(OperationRejectedData::class, $journal->records[0]->data);
        self::assertStringNotContainsString('sensitive-value', serialize($journal->records[0]->data));
        self::assertSame($observer->records[0]->data['reason']['violations'], $payload['violations']);
    }

    public function testValueFailureReturnsEveryViolationAndReceivedThenRejected(): void
    {
        $secret = 'raw-sensitive-token';
        $journal = new ValidationRecordingJournal();
        $observer = new ValidationRecordingObserver();
        $handler = $this->handler(Inline::class, $journal, $observer);

        $response = $handler->handle($this->request(json_encode([
            'email' => 'not-an-email',
            'quantity' => 1,
            'secret' => $secret,
        ], JSON_THROW_ON_ERROR)));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame(
            [
                ['field' => 'email', 'rule' => 'email', 'code' => 'validation.email'],
                ['field' => 'secret', 'rule' => 'regex', 'code' => 'validation.regex'],
            ],
            $payload['violations'],
        );
        self::assertStringNotContainsString($secret, (string) $response->getBody());
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationRejected],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame([1, 2], array_column($journal->records, 'sequence'));
        self::assertInstanceOf(OperationReceivedData::class, $journal->records[0]->data);
        self::assertSame($secret, $journal->records[0]->data->value->secret);
        self::assertInstanceOf(OperationRejectedData::class, $journal->records[1]->data);
        self::assertStringNotContainsString($secret, serialize($journal->records[1]->data));
        self::assertArrayNotHasKey('secret', $observer->records[0]->data['value']);
        self::assertStringNotContainsString($secret, serialize($observer->records));
        self::assertSame($payload['violations'], $observer->records[1]->data['reason']['violations']);
    }

    public function testInvalidSensitiveQueryScalarReturnsSafeCorrelatedBindingRejection(): void
    {
        $raw = '01-sensitive-page';
        $journal = new ValidationRecordingJournal();
        $observer = new ValidationRecordingObserver();
        $handler = $this->handler(Inline::class, $journal, $observer);
        $request = $this->request(
            '{"email":"reader@example.com","quantity":1,"secret":"token-safe"}',
        )->withQueryParams(['page' => $raw]);

        $response = $handler->handle($request);
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('validation', $payload['category']);
        self::assertSame('validation.failed', $payload['code']);
        self::assertSame([['field' => 'page', 'rule' => 'type', 'code' => 'binding.type']], $payload['violations']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $payload['operationId']);
        self::assertStringNotContainsString($raw, (string) $response->getBody());
        self::assertCount(1, $journal->records);
        self::assertSame(JournalEvent::OperationRejected, $journal->records[0]->event);
        self::assertSame(1, $journal->records[0]->sequence);
        self::assertStringNotContainsString($raw, serialize($journal->records));
        self::assertStringNotContainsString($raw, serialize($observer->records));
    }

    public function testDeferredValueFailureIsRejectedBeforeAcceptance(): void
    {
        $journal = new ValidationRecordingJournal();
        $observer = new ValidationRecordingObserver();
        $deferred = new ValidationFailingDeferredAcceptor();
        $handler = $this->handler(Deferred::class, $journal, $observer, $deferred);

        $response = $handler->handle($this->request('{"email":"invalid","quantity":1,"secret":"raw-sensitive-token"}'));

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($deferred->accepted);
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::OperationRejected],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame('deferred', $journal->records[0]->operation->strategy);
    }

    public function testDeferredScalarBindingFailureDoesNotReachAcceptance(): void
    {
        $journal = new ValidationRecordingJournal();
        $observer = new ValidationRecordingObserver();
        $deferred = new ValidationFailingDeferredAcceptor();
        $handler = $this->handler(Deferred::class, $journal, $observer, $deferred);
        $request = $this->request(
            '{"email":"reader@example.com","quantity":1,"secret":"token-safe"}',
        )->withQueryParams(['page' => '01']);

        $response = $handler->handle($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($deferred->accepted);
        self::assertSame([JournalEvent::OperationRejected], array_column($journal->records, 'event'));
        self::assertSame([1], array_column($journal->records, 'sequence'));
        self::assertSame('deferred', $journal->records[0]->operation->strategy);
    }

    /** @param class-string<Inline|Deferred> $strategy */
    private function handler(
        string $strategy,
        ValidationRecordingJournal $journal,
        ValidationRecordingObserver $observer,
        ?DeferredOperationAcceptor $deferred = null,
    ): OperationRequestHandler {
        $clock = new ValidationClock();
        $identifiers = new IdentifierFactory(new ValidationUuidv7Generator(), $clock);
        $metadata = new OperationMetadata(
            'submission.validate',
            ValidateSubmission::class,
            ValidateSubmissionValue::class,
            ValidateSubmission::class,
            EmptyOutcome::class,
            $strategy,
        );
        $dispatcher = new InlineDispatcher(
            new OperationRegistry([$metadata]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver(new ValidationFailingContainer()),
            new JournalRecordFactory($identifiers, $clock),
            $journal,
            observations: new JournalObservationPipeline(
                new ObservedJournalRecordProjector(new SensitiveProjectionFilter()),
                new JournalObserverAggregator([new JournalObserverBinding('validation-test', $observer)]),
            ),
        );

        return new OperationRequestHandler(
            new HttpRouteRegistry([new HttpOperationRoute(
                'POST',
                '/submissions',
                new ValidateSubmission(),
                ValidateSubmissionValue::class,
            )]),
            new OperationValueBinder(),
            $dispatcher,
            new JsonOperationResponder($this->psr17, $this->psr17),
            $this->psr17,
            $dispatcher,
            $deferred,
        );
    }

    private function request(string $body): ServerRequestInterface
    {
        return $this->psr17->createServerRequest('POST', '/submissions')->withBody($this->psr17->createStream($body));
    }
}

final readonly class ValidateSubmission implements Operation {}

final readonly class ValidateSubmissionValue implements OperationValue
{
    public function __construct(
        #[Email]
        public string $email,
        public int $quantity,
        #[Sensitive]
        #[Regex('/^token-[a-z]+$/')]
        public string $secret,
        #[Sensitive]
        #[FromQuery]
        public int $page = 1,
    ) {}
}

final class ValidationRecordingJournal implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final class ValidationRecordingObserver implements JournalObserver
{
    /** @var list<ObservedJournalRecord> */
    public array $records = [];

    public function observe(ObservedJournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final class ValidationFailingDeferredAcceptor implements DeferredOperationAcceptor
{
    public bool $accepted = false;

    public function accepts(Operation $definition): bool
    {
        return true;
    }

    public function accept(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
        ?IdempotencyKey $idempotencyKey = null,
    ): DeferredAcknowledgement|OperationResult {
        $this->accepted = true;
        self::fail('Deferred acceptance should not run after validation failure.');
    }
}

final readonly class ValidationFailingContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        self::fail('Handler resolution should not run after validation failure.');
    }

    public function has(string $id): bool
    {
        return true;
    }
}

final readonly class ValidationClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-14T00:00:00.123456Z');
    }
}

final class ValidationUuidv7Generator implements Uuidv7Generator
{
    private int $index = 0;

    /** @var list<string> */
    private array $values = [
        '019f32ab-2be0-7b38-a0a7-1ab2f9687801',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687802',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687803',
        '019f32ab-2be0-7b38-a0a7-1ab2f9687804',
    ];

    public function generate(DateTimeImmutable $time): string
    {
        return $this->values[$this->index++];
    }
}
