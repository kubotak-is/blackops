<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Schedule;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Registry\OperationScheduleMetadata;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\Route\RouteProjectionProducer;
use BlackOps\Internal\Projection\Schedule\ScheduleProjectionProducer;
use PHPUnit\Framework\TestCase;

final class ScheduleProjectionProducerTest extends TestCase
{
    public function testEmptyRegistryProducesAnEmptyScheduleProjection(): void
    {
        $projection = new ScheduleProjectionProducer()->produce('build-1', new OperationRegistry([]));

        self::assertSame([], $projection->records);
    }

    public function testProducesMixedStrategiesAndSortsByNameThenOperationType(): void
    {
        $registry = new OperationRegistry([
            $this->metadata('zeta.operation', 'zeta.run', Deferred::class, '*/5 * * * *', 'UTC'),
            $this->metadata('alpha.operation', 'alpha.run', Inline::class, '0 1 * * *', 'Asia/Tokyo'),
            $this->metadata('alpha.other', 'alpha.other', '\\Vendor\\Queue\\Strategy', '15 2 * * *', 'UTC'),
            $this->metadata('unscheduled.operation', null, Inline::class),
        ]);

        $projection = new ScheduleProjectionProducer()->produce('build-1', $registry);

        self::assertSame(
            [
                [
                    'name' => 'alpha.other',
                    'operationType' => 'alpha.other',
                    'cron' => '15 2 * * *',
                    'timezone' => 'UTC',
                    'strategy' => 'custom:Vendor\\Queue\\Strategy',
                ],
                [
                    'name' => 'alpha.run',
                    'operationType' => 'alpha.operation',
                    'cron' => '0 1 * * *',
                    'timezone' => 'Asia/Tokyo',
                    'strategy' => 'inline',
                ],
                [
                    'name' => 'zeta.run',
                    'operationType' => 'zeta.operation',
                    'cron' => '*/5 * * * *',
                    'timezone' => 'UTC',
                    'strategy' => 'deferred',
                ],
            ],
            $projection->records,
        );
    }

    public function testProducerNeverLoadsDeclaredApplicationClasses(): void
    {
        $autoloaded = [];
        $prefix = 'BlackOps\\Tests\\NeverLoad\\';
        $loader = static function (string $class) use (&$autoloaded, $prefix): void {
            if (str_starts_with($class, $prefix)) {
                $autoloaded[] = $class;
            }
        };
        spl_autoload_register($loader);

        try {
            $registry = new OperationRegistry([
                $this->metadata('purity.operation', 'purity.run', $prefix . 'Strategy', '*/10 * * * *', 'UTC'),
            ]);
            $projection = new ScheduleProjectionProducer()->produce('build-1', $registry);
        } finally {
            spl_autoload_unregister($loader);
        }

        self::assertSame([], $autoloaded);
        self::assertSame('custom:' . $prefix . 'Strategy', $projection->records[0]['strategy']);
    }

    public function testRouteAndScheduleUseTheSameStrategyWire(): void
    {
        $strategy = '\\Vendor\\Queue\\Strategy';
        $operation = $this->metadata('shared.operation', 'shared.run', $strategy, '*/5 * * * *', 'UTC');
        $schedule = new ScheduleProjectionProducer()->produce('build-1', new OperationRegistry([$operation]));
        $route = new RouteProjectionProducer()->produce(
            'build-1',
            new HttpOperationManifest(
                ['GET' => ['/shared' => 'shared.operation']],
                ['shared.operation' => [
                    'definition' => 'Fixture\\Operation',
                    'value' => 'Fixture\\Value',
                    'handler' => 'Fixture\\Handler',
                    'outcome' => 'Fixture\\Outcome',
                    'strategy' => $strategy,
                    'ephemeral' => false,
                ]],
                [[], []],
            ),
        );

        self::assertSame('custom:Vendor\\Queue\\Strategy', $schedule->records[0]['strategy']);
        self::assertSame('custom:Vendor\\Queue\\Strategy', $route->records[1]['strategy']);
    }

    public function testCodecRejectsDuplicateScheduleRows(): void
    {
        $record = $this->record();

        $this->expectException(ProjectionBuildSetValidationException::class);
        new ProjectionBuildSetCodec()->projection(
            kind: 'blackops_schedule_projection',
            applicationBuildId: 'build-1',
            records: [$record, $record],
        );
    }

    public function testProducerRejectsScheduleRecordCountAboveTheCodecBound(): void
    {
        $metadata = [];
        for ($index = 0; $index <= ProjectionBuildSetCodec::MAX_RECORDS; ++$index) {
            $metadata[] = $this->metadata('cap.' . $index, 'cap.' . $index, Inline::class, '* * * * *', 'UTC');
        }

        $this->expectException(ProjectionBuildSetValidationException::class);
        new ScheduleProjectionProducer()->produce('build-1', new OperationRegistry($metadata));
    }

    public function testCodecRejectsClosedRowsAndInvalidCronTimezoneAndStrategy(): void
    {
        $codec = new ProjectionBuildSetCodec();
        foreach ([
            $this->record() + ['unexpected' => true],
            $this->record(cron: 'not cron'),
            $this->record(timezone: 'Mars/Phobos'),
            $this->record(strategy: 'custom:'),
        ] as $record) {
            try {
                $codec->projection(
                    kind: 'blackops_schedule_projection',
                    applicationBuildId: 'build-1',
                    records: [$record],
                );
                self::fail('Expected invalid schedule record.');
            } catch (ProjectionBuildSetValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function metadata(
        string $typeId,
        ?string $scheduleName,
        string $strategy,
        string $cron = '* * * * *',
        string $timezone = 'UTC',
    ): OperationMetadata {
        $suffix = str_replace('.', '_', $typeId);

        return new OperationMetadata(
            $typeId,
            'Fixture\\Operation\\' . $suffix,
            'Fixture\\Value\\' . $suffix,
            'Fixture\\Handler\\' . $suffix,
            'Fixture\\Outcome\\' . $suffix,
            $strategy,
            schedule: $scheduleName === null ? null : new OperationScheduleMetadata($scheduleName, $cron, $timezone),
        );
    }

    /** @return array{name: string, operationType: string, cron: string, timezone: string, strategy: string} */
    private function record(string $cron = '* * * * *', string $timezone = 'UTC', string $strategy = 'inline'): array
    {
        return [
            'name' => 'fixture.run',
            'operationType' => 'fixture.run',
            'cron' => $cron,
            'timezone' => $timezone,
            'strategy' => $strategy,
        ];
    }
}
