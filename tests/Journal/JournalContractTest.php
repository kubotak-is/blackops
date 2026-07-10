<?php

declare(strict_types=1);

namespace BlackOps\Tests\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationCompletedData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\Data\OperationRejectedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalData;
use BlackOps\Journal\JournalEvent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class JournalContractTest extends TestCase
{
    public function testEventWireNames(): void
    {
        self::assertSame(
            [
                'operation.received',
                'operation.accepted',
                'attempt.started',
                'attempt.succeeded',
                'attempt.failed',
                'attempt.retry_scheduled',
                'operation.completed',
                'operation.rejected',
                'operation.failed',
                'operation.dead_lettered',
            ],
            array_column(JournalEvent::cases(), 'value'),
        );
    }

    public function testPublicContractShapes(): void
    {
        $event = new ReflectionClass(JournalEvent::class);
        $data = new ReflectionClass(JournalData::class);
        $empty = new ReflectionClass(EmptyJournalData::class);

        self::assertCount(1, $event->getAttributes(PublicApi::class));
        self::assertTrue($data->isInterface());
        self::assertSame([], $data->getMethods());
        self::assertCount(1, $data->getAttributes(PublicApi::class));
        self::assertTrue($empty->isFinal());
        self::assertTrue($empty->isReadOnly());
        self::assertTrue($empty->implementsInterface(JournalData::class));
        self::assertCount(1, $empty->getAttributes(PublicApi::class));
    }

    public function testDataClassesArePublicApi(): void
    {
        foreach ([
            AttemptFailedData::class,
            AttemptRetryScheduledData::class,
            OperationCompletedData::class,
            OperationDeadLetteredData::class,
            OperationFailedData::class,
            OperationReceivedData::class,
            OperationRejectedData::class,
        ] as $type) {
            $reflection = new ReflectionClass($type);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            self::assertTrue($reflection->implementsInterface(JournalData::class));
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        }
    }
}
