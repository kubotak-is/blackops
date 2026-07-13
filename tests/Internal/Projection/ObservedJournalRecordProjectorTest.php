<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Core\Validation\Violation;
use BlackOps\Internal\Projection\ObservedJournalRecordProjector;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
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
        $secret = 'do-not-project-this-value';
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
        self::assertStringNotContainsString($secret, serialize($observed));
    }
}

final readonly class ObservedProjectionValue implements OperationValue
{
    public function __construct(
        public string $message,
        #[Sensitive]
        public string $password,
    ) {}
}
