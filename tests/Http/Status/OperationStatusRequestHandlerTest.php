<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Http\Status\OperationStatusJsonResponder;
use BlackOps\Http\Status\OperationStatusRequestHandler;
use BlackOps\Status\Exception\OperationStatusQueryException;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusQuery;
use BlackOps\Status\OperationStatusResult;
use BlackOps\Status\OperationStatusUnavailable;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class OperationStatusRequestHandlerTest extends TestCase
{
    private const string OPERATION_ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    /** @param array<string, mixed> $expected */
    #[DataProvider('foundStatuses')]
    public function testProjectsStateSpecificSchemaVersionOneJson(
        OperationStatus $status,
        array $expected,
        bool $retryAfter,
    ): void {
        $response = $this->handler(new RecordingStatusQuery(new OperationStatusFound($status)))->handle($this->request(
            'GET',
            '/operations/' . self::OPERATION_ID,
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($retryAfter ? '1' : '', $response->getHeaderLine('Retry-After'));
        self::assertSame($expected, json_decode(
            (string) $response->getBody(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        ));
        self::assertArrayNotHasKey('actor', $expected);
        self::assertArrayNotHasKey('attemptId', $expected);
        self::assertArrayNotHasKey('correlationId', $expected);
    }

    /** @return iterable<string, array{OperationStatus, array<string, mixed>, bool}> */
    public static function foundStatuses(): iterable
    {
        $id = OperationId::fromString(self::OPERATION_ID);
        $common = [
            'schemaVersion' => 1,
            'operationId' => self::OPERATION_ID,
            'operationType' => 'report.generate',
        ];

        yield 'accepted' => [
            OperationStatus::accepted($id, 'report.generate'),
            [...$common, 'state' => 'accepted'],
            true,
        ];
        yield 'running' => [
            OperationStatus::running($id, 'report.generate', 2),
            [...$common, 'state' => 'running', 'attempt' => 2],
            true,
        ];
        yield 'retry scheduled' => [
            OperationStatus::retryScheduled(
                $id,
                'report.generate',
                3,
                new DateTimeImmutable('2026-07-19T18:30:00.123456+09:00'),
            ),
            [...$common, 'state' => 'retry_scheduled', 'attempt' => 3, 'retryAt' => '2026-07-19T09:30:00.123456Z'],
            true,
        ];
        yield 'completed' => [
            OperationStatus::completed($id, 'report.generate', new StatusHttpOutcome('report-1042')),
            [...$common, 'state' => 'completed', 'outcome' => ['reportId' => 'report-1042']],
            false,
        ];
        yield 'rejected' => [
            OperationStatus::rejected($id, 'report.generate', 'validation', 'validation_failed'),
            [
                ...$common,
                'state' => 'rejected',
                'error' => ['category' => 'validation', 'code' => 'validation_failed'],
            ],
            false,
        ];
        yield 'failed' => [
            OperationStatus::failed($id, 'report.generate'),
            [...$common, 'state' => 'failed', 'error' => ['code' => 'operation_failed']],
            false,
        ];
        yield 'dead lettered' => [
            OperationStatus::deadLettered($id, 'report.generate'),
            [...$common, 'state' => 'dead_lettered', 'error' => ['code' => 'operation_dead_lettered']],
            false,
        ];
    }

    public function testEmptyOutcomeIsAnEmptyJsonObject(): void
    {
        $status = OperationStatus::completed(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            new EmptyOutcome(),
        );

        $response = $this->handler(new RecordingStatusQuery(new OperationStatusFound($status)))->handle($this->request(
            'GET',
            '/operations/' . self::OPERATION_ID,
        ));

        self::assertStringContainsString('"outcome":{}', (string) $response->getBody());
    }

    public function testInlineAndCompletedStatusShareTheSameStructuredOutcomeShape(): void
    {
        $author = new StatusAuthorOutcomeData('author-1', 'Alice');
        $outcome = new StatusStructuredOutcome([
            new StatusItemOutcomeData($author, 'item-1'),
            new StatusItemOutcomeData(null, 'item-2'),
        ], $author);

        $inline = new JsonOperationResponder($this->psr17, $this->psr17)->respond(OperationResult::completed($outcome));
        $status = new OperationStatusJsonResponder(
            $this->psr17,
            $this->psr17,
        )->respond(new OperationStatusFound(OperationStatus::completed(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            $outcome,
        )));
        $inlinePayload = json_decode((string) $inline->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $statusPayload = json_decode((string) $status->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'author' => ['id' => 'author-1', 'name' => 'Alice'],
                'items' => [
                    ['author' => ['id' => 'author-1', 'name' => 'Alice'], 'id' => 'item-1'],
                    ['author' => null, 'id' => 'item-2'],
                ],
            ],
            $inlinePayload,
        );
        self::assertSame($inlinePayload, $statusPayload['outcome']);
    }

    public function testInlineAndCompletedStatusEncodeZeroFieldDtoAsJsonObjects(): void
    {
        $empty = new StatusZeroFieldOutcomeData();
        $outcome = new StatusZeroFieldNestedOutcome([$empty, new StatusZeroFieldOutcomeData()], $empty, $empty);

        $inline = new JsonOperationResponder($this->psr17, $this->psr17)->respond(OperationResult::completed($outcome));
        $status = new OperationStatusJsonResponder(
            $this->psr17,
            $this->psr17,
        )->respond(new OperationStatusFound(OperationStatus::completed(
            OperationId::fromString(self::OPERATION_ID),
            'report.generate',
            $outcome,
        )));

        self::assertSame('{"items":[{},{}],"nested":{},"optional":{}}', (string) $inline->getBody());
        self::assertStringContainsString(
            '"outcome":{"items":[{},{}],"nested":{},"optional":{}}',
            (string) $status->getBody(),
        );
    }

    /** @return iterable<string, array{OperationStatusResult, int, string}> */
    public static function unavailableAndExpired(): iterable
    {
        yield 'unavailable' => [new OperationStatusUnavailable(), 404, 'operation_unavailable'];
        yield 'expired' => [new OperationStatusExpired(), 410, 'operation_expired'];
    }

    #[DataProvider('unavailableAndExpired')]
    public function testMapsUnavailableAndExpiredToSafeNonCacheableErrors(
        OperationStatusResult $result,
        int $expectedStatus,
        string $expectedCode,
    ): void {
        $response = $this->handler(new RecordingStatusQuery($result))->handle($this->request(
            'GET',
            '/operations/' . self::OPERATION_ID,
        ));

        self::assertSame($expectedStatus, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('', $response->getHeaderLine('Retry-After'));
        self::assertSame(
            ['status' => 'error', 'code' => $expectedCode],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testInvalidAndNonCanonicalIdentifiersDoNotCallQuery(): void
    {
        $query = new RecordingStatusQuery(new OperationStatusUnavailable());
        $handler = $this->handler($query);

        foreach (['not-a-uuid-private', strtoupper(self::OPERATION_ID), ''] as $value) {
            $response = $handler->handle($this->request('GET', '/operations/' . $value));
            self::assertSame(404, $response->getStatusCode());
            self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('{"status":"error","code":"operation_unavailable"}', (string) $response->getBody());
            if ($value !== '') {
                self::assertStringNotContainsString($value, (string) $response->getBody());
            }
        }

        self::assertSame(0, $query->calls);
    }

    public function testPassesOnlyAuthenticatedActorToQuery(): void
    {
        $query = new RecordingStatusQuery(new OperationStatusUnavailable());
        $actor = new ActorRef('user-123', 'user');
        $handler = $this->handler($query);

        $handler->handle($this->request('GET', '/operations/' . self::OPERATION_ID)->withAttribute(
            ActorRef::class,
            $actor,
        ));

        self::assertSame($actor, $query->actor);
        self::assertSame(self::OPERATION_ID, $query->operationId?->toString());
    }

    public function testQueryAndJsonFailuresReturnSafeInternalError(): void
    {
        $failures = [
            new RecordingStatusQuery(failure: OperationStatusQueryException::storageFailed()),
            new RecordingStatusQuery(failure: new RuntimeException('credential backend detail')),
            new RecordingStatusQuery(new OperationStatusFound(OperationStatus::completed(
                OperationId::fromString(self::OPERATION_ID),
                'report.generate',
                new InvalidJsonStatusOutcome("\xB1\x31"),
            ))),
            new RecordingStatusQuery(new OperationStatusFound(OperationStatus::completed(
                OperationId::fromString(self::OPERATION_ID),
                'report.generate',
                new InvalidListStatusOutcome(['not-a-list' => new StatusAuthorOutcomeData('author-1', 'Alice')]),
            ))),
        ];

        foreach ($failures as $query) {
            $response = $this->handler($query)->handle($this->request('GET', '/operations/' . self::OPERATION_ID));
            self::assertSame(500, $response->getStatusCode());
            self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('{"status":"error","code":"internal_error"}', (string) $response->getBody());
            self::assertStringNotContainsString('credential', (string) $response->getBody());
        }
    }

    public function testMatchesOnlyGetWithOneDirectOperationSegment(): void
    {
        $handler = $this->handler(new RecordingStatusQuery(new OperationStatusUnavailable()));

        self::assertTrue($handler->matches($this->request('GET', '/operations/' . self::OPERATION_ID)));
        self::assertTrue($handler->matches($this->request('GET', '/operations/not-valid')));
        self::assertFalse($handler->matches($this->request('HEAD', '/operations/' . self::OPERATION_ID)));
        self::assertFalse($handler->matches($this->request('POST', '/operations/' . self::OPERATION_ID)));
        self::assertFalse($handler->matches($this->request('GET', '/operations/' . self::OPERATION_ID . '/outcome')));
    }

    private function handler(OperationStatusQuery $query): OperationStatusRequestHandler
    {
        return new OperationStatusRequestHandler($query, new OperationStatusJsonResponder($this->psr17, $this->psr17));
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return $this->psr17->createServerRequest($method, $path);
    }
}

final class RecordingStatusQuery implements OperationStatusQuery
{
    public int $calls = 0;
    public ?OperationId $operationId = null;
    public ?ActorRef $actor = null;

    public function __construct(
        private readonly ?OperationStatusResult $result = null,
        private readonly ?Throwable $failure = null,
    ) {}

    public function find(OperationId $operationId, ?ActorRef $currentActor = null): OperationStatusResult
    {
        ++$this->calls;
        $this->operationId = $operationId;
        $this->actor = $currentActor;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->result ?? throw new RuntimeException('Missing status query result.');
    }
}

final readonly class StatusHttpOutcome implements Outcome
{
    public function __construct(
        public string $reportId,
    ) {}
}

final readonly class InvalidJsonStatusOutcome implements Outcome
{
    public function __construct(
        public string $value,
    ) {}
}

final readonly class StatusAuthorOutcomeData implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

final readonly class StatusItemOutcomeData implements OutcomeData
{
    public function __construct(
        public ?StatusAuthorOutcomeData $author,
        public string $id,
    ) {}
}

final readonly class StatusStructuredOutcome implements Outcome
{
    /** @param list<StatusItemOutcomeData> $items */
    public function __construct(
        #[ListOf(StatusItemOutcomeData::class)]
        public array $items,
        public StatusAuthorOutcomeData $author,
    ) {}
}

final readonly class StatusZeroFieldOutcomeData implements OutcomeData {}

final readonly class StatusZeroFieldNestedOutcome implements Outcome
{
    /** @param list<StatusZeroFieldOutcomeData> $items */
    public function __construct(
        #[ListOf(StatusZeroFieldOutcomeData::class)]
        public array $items,
        public StatusZeroFieldOutcomeData $nested,
        public ?StatusZeroFieldOutcomeData $optional,
    ) {}
}

final readonly class InvalidListStatusOutcome implements Outcome
{
    /** @param array<array-key, mixed> $items */
    public function __construct(
        #[ListOf(StatusAuthorOutcomeData::class)]
        public array $items,
    ) {}
}
