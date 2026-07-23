<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core\Execution;

use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DeferredAcknowledgementTest extends TestCase
{
    public function testReplayMarkerIsOptionalAndDoesNotExposeHttpHeaders(): void
    {
        $acknowledgement = new DeferredAcknowledgement(
            OperationId::fromString('019f8fbf-43b2-791c-9e4b-9d73d28e477e'),
            new DateTimeImmutable('2026-07-24T00:00:00Z'),
            true,
        );

        self::assertTrue($acknowledgement->isReplayed());
        self::assertSame('2026-07-24T00:00:00+00:00', $acknowledgement->acceptedAt()->format(DATE_ATOM));
    }
}
