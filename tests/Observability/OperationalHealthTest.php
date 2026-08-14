<?php

declare(strict_types=1);

namespace BlackOps\Tests\Observability;

use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQueryFactory;
use PHPUnit\Framework\TestCase;

final class OperationalHealthTest extends TestCase
{
    public function testPublicFactoryComposesBoundedReadinessChecksWithoutInternalTypes(): void
    {
        $checks = [];
        foreach (OperationalHealthQueryFactory::requiredReadinessCheckCodes() as $code) {
            $checks[$code] = static fn(): bool => true;
        }

        $report = OperationalHealthQueryFactory::fromCallbacks($checks)->check(OperationalHealthKind::Readiness);

        self::assertSame('pass', $report->toArray()['status']);
        self::assertSame(OperationalHealthQueryFactory::requiredReadinessCheckCodes(), array_column(
            $report->toArray()['checks'],
            'code',
        ));
        self::assertSame(
            array_map(static fn(string $code): array => [
                'code' => $code,
                'status' => 'pass',
            ], OperationalHealthQueryFactory::requiredReadinessCheckCodes()),
            $report->toArray()['checks'],
        );
    }

    public function testPublicQueryConstructorRejectsMissingReadinessCallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new \BlackOps\Observability\CallbackOperationalHealthQuery([]);
    }
}
