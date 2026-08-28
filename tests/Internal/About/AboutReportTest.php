<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\About;

use BlackOps\Internal\About\AboutMetadata;
use BlackOps\Internal\About\AboutReport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AboutReportTest extends TestCase
{
    public function testToArrayIsTheClosedAboutObject(): void
    {
        $report = new AboutReport(new AboutMetadata('1.2.0@dev', '8.5.0', 'cli'), 'available');

        self::assertSame(
            [
                'package' => [
                    'name' => 'blackops/framework',
                    'version' => '1.2.0@dev',
                ],
                'projectionBuildSet' => 'available',
                'runtime' => [
                    'phpVersion' => '8.5.0',
                    'sapi' => 'cli',
                    'scope' => 'current_process',
                ],
                'schemaVersion' => 1,
            ],
            $report->toArray(),
        );
    }

    public function testEveryContractedProjectionStatusIsRepresentable(): void
    {
        foreach (AboutReport::PROJECTION_STATUSES as $status) {
            self::assertSame(
                $status,
                new AboutReport(new AboutMetadata('dev-main', '8.5.0', 'cli'), $status)->projectionBuildSet,
            );
        }
    }

    public function testUnexpectedProjectionStatusIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AboutReport(new AboutMetadata('dev-main', '8.5.0', 'cli'), 'pass');
    }
}
