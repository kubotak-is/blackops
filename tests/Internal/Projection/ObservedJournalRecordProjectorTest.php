<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\Validation\Violation;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Logging\JsonlJournalRecordEncoder;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ObservedJournalRecordProjectorTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testProjectsCanonicalRecordWithoutRawJournalData(): void
    {
        $value = new ObservedProjectionValue('hello', 'open sesame');
        $canonical = new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-07T00:00:00Z'),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'projection.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
            ),
            null,
            new OperationReceivedData($value),
        );

        $observed = new ObservedJournalRecordProjector(new SensitiveProjectionFilter())->project($canonical);

        self::assertSame($canonical->recordId, $observed->recordId);
        self::assertSame($canonical->event, $observed->event);
        self::assertSame($canonical->operation, $observed->operation);
        self::assertSame(['value' => ['message' => 'hello']], $observed->data);
    }

    public function testProjectsSafeRejectedReasonWithoutRawValue(): void
    {
        $valueToHide = 'do-not-project-this-value';
        $canonical = new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationRejected,
            new DateTimeImmutable('2026-07-07T00:00:00Z'),
            2,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'projection.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
            ),
            null,
            new OperationRejectedData(RejectionReason::validation('validation.failed', [
                new Violation('password', 'not_blank', 'validation.not_blank'),
            ])),
        );

        $observed = new ObservedJournalRecordProjector(new SensitiveProjectionFilter())->project($canonical);

        self::assertSame(
            [
                'reason' => [
                    'category' => 'validation',
                    'code' => 'validation.failed',
                    'violations' => [[
                        'field' => 'password',
                        'rule' => 'not_blank',
                        'code' => 'validation.not_blank',
                    ]],
                ],
            ],
            $observed->data,
        );
        self::assertStringNotContainsString($valueToHide, serialize($observed));
    }

    public function testMasksEveryObservedActorIdAndPreservesTypesAndNulls(): void
    {
        $canonical = new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-07T00:00:00Z'),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'projection.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
                actorContext: new ActorContext(
                    null,
                    new ActorRef('authorization-user-123', 'user'),
                    new ActorRef('http-runtime-456', 'system'),
                ),
            ),
            null,
            new OperationReceivedData(new ObservedProjectionValue('hello', 'secret')),
        );

        $observed = new ObservedJournalRecordProjector(new SensitiveProjectionFilter())->project($canonical);
        $actors = $observed->operation->actorContext;

        self::assertNotSame($canonical->operation, $observed->operation);
        self::assertSame($canonical->operation->id, $observed->operation->id);
        self::assertSame($canonical->operation->type, $observed->operation->type);
        self::assertSame($canonical->operation->strategy, $observed->operation->strategy);
        self::assertSame($canonical->operation->correlationId, $observed->operation->correlationId);
        self::assertSame($canonical->operation->causationId, $observed->operation->causationId);
        self::assertNull($actors?->origin());
        self::assertSame('[masked]', $actors?->authorization()?->id());
        self::assertSame('user', $actors?->authorization()?->type());
        self::assertSame('[masked]', $actors?->execution()->id());
        self::assertSame('system', $actors?->execution()->type());
        self::assertStringNotContainsString('authorization-user-123', serialize($observed));
        self::assertStringNotContainsString('http-runtime-456', serialize($observed));
    }

    public function testPreservesScheduleContextWhileMaskingActors(): void
    {
        $schedule = new ScheduleContext(
            'reports.daily',
            new DateTimeImmutable('2026-07-22T09:00:00.654321Z'),
            'Asia/Tokyo',
        );
        $canonical = new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-07T00:00:00Z'),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'projection.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
                actorContext: new ActorContext(
                    new ActorRef('provider-user', 'user'),
                    new ActorRef('provider-user', 'user'),
                    new ActorRef('scheduled-runtime', 'system'),
                ),
                schedule: $schedule,
            ),
            null,
            new EmptyJournalData(),
        );

        $observed = new ObservedJournalRecordProjector(new SensitiveProjectionFilter())->project($canonical);

        self::assertSame('reports.daily', $observed->operation->schedule?->name());
        self::assertSame('Asia/Tokyo', $observed->operation->schedule?->timezone());
        self::assertSame(
            '2026-07-22T09:00:00.654321Z',
            $observed->operation->schedule?->scheduledAt()->format('Y-m-d\\TH:i:s.u\\Z'),
        );
        self::assertStringNotContainsString('provider-user', serialize($observed->operation->schedule));
        self::assertStringNotContainsString('scheduled-runtime', serialize($observed->operation->schedule));
    }

    public function testProjectorEncoderPreservesObservedJournalWireShapes(): void
    {
        $projector = new ObservedJournalRecordProjector(new SensitiveProjectionFilter());
        $encoder = new JsonlJournalRecordEncoder();

        $empty = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationAccepted,
                new EmptyJournalData(),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertInstanceOf(\stdClass::class, $empty->data);

        $retry = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::AttemptRetryScheduled,
                new AttemptRetryScheduledData(
                    AttemptId::fromString(self::ID),
                    2,
                    new DateTimeImmutable('2026-07-07T09:10:11.123456', new DateTimeZone('Asia/Tokyo')),
                    500,
                ),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame(self::ID, $retry->data->failedAttemptId);
        self::assertSame(2, $retry->data->nextAttemptNumber);
        self::assertSame('2026-07-07T00:10:11.123456Z', $retry->data->scheduledAt);
        self::assertSame(500, $retry->data->delayMilliseconds);

        $deadLetter = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationDeadLettered,
                new OperationDeadLetteredData(
                    AttemptId::fromString(self::ID),
                    3,
                    'RuntimeException',
                    'safe message',
                    new DateTimeImmutable('2026-07-07T09:10:11.123456', new DateTimeZone('Asia/Tokyo')),
                ),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame(self::ID, $deadLetter->data->finalAttemptId);
        self::assertSame(3, $deadLetter->data->finalAttemptNumber);
        self::assertSame('2026-07-07T00:10:11.123456Z', $deadLetter->data->movedAt);

        $deadLetterWithoutAttempt = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationDeadLettered,
                new OperationDeadLetteredData(
                    null,
                    null,
                    'RuntimeException',
                    'safe message',
                    new DateTimeImmutable('2026-07-07T09:10:11.123456', new DateTimeZone('Asia/Tokyo')),
                ),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertNull($deadLetterWithoutAttempt->data->finalAttemptId);
        self::assertNull($deadLetterWithoutAttempt->data->finalAttemptNumber);

        $completed = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationCompleted,
                new OperationCompletedData(new EmptyOutcome()),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertInstanceOf(\stdClass::class, $completed->data->outcome);

        $emptyList = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationReceived,
                new OperationReceivedData(new EmptyListProjectionValue()),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($emptyList->data->value->items);
        self::assertSame([], $emptyList->data->value->items);

        $stringable = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StringableProjectionValue('visible', 'secret-value')),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('visible', $stringable->data->value->visible);
        self::assertObjectNotHasProperty('secret', $stringable->data->value);
        self::assertStringNotContainsString(
            'secret-value',
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationReceived,
                new OperationReceivedData(new StringableProjectionValue('visible', 'secret-value')),
            ))),
        );

        $fullyRedacted = json_decode(
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationReceived,
                new OperationReceivedData(new FullyRedactedProjectionValue('fully-secret')),
            ))),
            associative: false,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertInstanceOf(\stdClass::class, $fullyRedacted->data->value);
        self::assertStringNotContainsString(
            'fully-secret',
            $encoder->encode($projector->project($this->record(
                JournalEvent::OperationReceived,
                new OperationReceivedData(new FullyRedactedProjectionValue('fully-secret')),
            ))),
        );
    }

    private function record(JournalEvent $event, JournalData $data): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            $event,
            new DateTimeImmutable('2026-07-07T00:00:00.000000Z'),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'projection.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
            ),
            null,
            $data,
        );
    }
}

final readonly class ObservedProjectionValue implements OperationValue
{
    public function __construct(
        public string $message,
        #[Sensitive, \SensitiveParameter]
        public string $password,
    ) {}
}

final readonly class EmptyListProjectionValue implements OperationValue
{
    /** @var list<mixed> */
    public function __construct(
        public array $items = [],
    ) {}
}

final readonly class StringableProjectionValue implements OperationValue, \Stringable
{
    public function __construct(
        public string $visible,
        #[Sensitive, \SensitiveParameter]
        public string $secret,
    ) {}

    public function __toString(): string
    {
        return $this->secret;
    }
}

final readonly class FullyRedactedProjectionValue implements OperationValue
{
    public function __construct(
        #[Sensitive, \SensitiveParameter]
        public string $secret,
    ) {}
}
