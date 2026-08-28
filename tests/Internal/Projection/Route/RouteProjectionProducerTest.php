<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Route;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Http\Routing\HttpOperationManifest;
use BlackOps\Http\Status\OperationStatusHttpContract;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\Route\RouteProjectionProducer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteProjectionProducerTest extends TestCase
{
    public function testProducesFixedRouteForAnEmptyManifest(): void
    {
        $projection = new RouteProjectionProducer()->produce('build-1', new HttpOperationManifest([], [], [[], []]));

        self::assertSame([OperationStatusHttpContract::descriptor()], $projection->records);
    }

    public function testProjectsReferencedOperationsAndMapsStrategiesWithoutLoadingClasses(): void
    {
        $manifest = new HttpOperationManifest(
            [
                'GET' => [
                    '/zeta/{id}' => 'zeta.read',
                    '/alpha' => 'alpha.read',
                ],
                'POST' => ['/reports' => 'report.create'],
            ],
            [
                'zeta.read' => $this->operation(Deferred::class),
                'alpha.read' => $this->operation(Inline::class),
                'report.create' => $this->operation('\\Vendor\\CustomStrategy'),
                'unused.read' => $this->operation(Inline::class),
            ],
            [[], []],
        );

        $projection = new RouteProjectionProducer()->produce('build-1', $manifest);

        self::assertSame(
            [
                [
                    'method' => 'GET',
                    'path' => '/alpha',
                    'strategy' => 'inline',
                    'operationType' => 'alpha.read',
                    'origin' => 'operation_manifest',
                ],
                OperationStatusHttpContract::descriptor(),
                [
                    'method' => 'POST',
                    'path' => '/reports',
                    'strategy' => 'custom:Vendor\\CustomStrategy',
                    'operationType' => 'report.create',
                    'origin' => 'operation_manifest',
                ],
                [
                    'method' => 'GET',
                    'path' => '/zeta/{id}',
                    'strategy' => 'deferred',
                    'operationType' => 'zeta.read',
                    'origin' => 'operation_manifest',
                ],
            ],
            $projection->records,
        );
    }

    public function testRejectsUnknownReferencedOperation(): void
    {
        $manifest = new HttpOperationManifest(['GET' => ['/missing' => 'missing.read']], [], [[], []]);

        $this->expectException(InvalidArgumentException::class);
        new RouteProjectionProducer()->produce('build-1', $manifest);
    }

    public function testRejectsInvalidStrategyThroughTheClosedCodec(): void
    {
        $manifest = new HttpOperationManifest(
            ['GET' => ['/valid' => 'valid.read']],
            ['valid.read' => $this->operation('not a class')],
            [[], []],
        );

        $this->expectException(ProjectionBuildSetValidationException::class);
        new RouteProjectionProducer()->produce('build-1', $manifest);
    }

    public function testCodecRejectsDuplicateRouteRecords(): void
    {
        $record = $this->routeRecord('/duplicate');

        $this->expectException(ProjectionBuildSetValidationException::class);
        new ProjectionBuildSetCodec()->projection(
            kind: 'blackops_route_projection',
            applicationBuildId: 'build-1',
            records: [$record, $record],
        );
    }

    public function testProducerRejectsRouteRecordCountAboveItsBound(): void
    {
        $routes = [];
        $operations = [];
        for ($index = 0; $index < ProjectionBuildSetCodec::MAX_RECORDS; ++$index) {
            $operationType = 'cap.' . $index;
            $routes['GET']['/cap/' . $index] = $operationType;
            $operations[$operationType] = $this->operation(Inline::class);
        }

        $this->expectException(ProjectionBuildSetValidationException::class);
        new RouteProjectionProducer()->produce('build-1', new HttpOperationManifest(
            ['GET' => $routes['GET']],
            $operations,
            [[], []],
        ));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function closedRouteRows(): iterable
    {
        $valid = [
            'method' => 'GET',
            'path' => '/closed',
            'strategy' => 'inline',
            'operationType' => 'closed.read',
            'origin' => 'operation_manifest',
        ];
        yield 'unknown field' => [$valid + ['unexpected' => true]];
        unset($valid['origin']);
        yield 'missing field' => [$valid];
    }

    /** @param array<string, mixed> $record */
    #[DataProvider('closedRouteRows')]
    public function testCodecRejectsUnknownOrMissingClosedRouteFields(array $record): void
    {
        $this->expectException(ProjectionBuildSetValidationException::class);
        new ProjectionBuildSetCodec()->projection(
            kind: 'blackops_route_projection',
            applicationBuildId: 'build-1',
            records: [$record],
        );
    }

    public function testProducerDoesNotAutoloadOrResolveManifestClassStrings(): void
    {
        $prefix = 'BlackOps\\Tests\\NeverLoad\\';
        $autoloaded = [];
        $loader = static function (string $class) use (&$autoloaded, $prefix): void {
            if (str_starts_with($class, $prefix)) {
                $autoloaded[] = $class;
            }
        };
        spl_autoload_register($loader);

        try {
            $manifest = new HttpOperationManifest(
                [
                    'GET' => [
                        '/purity/one' => 'purity.one',
                        '/purity/two' => 'purity.two',
                    ],
                ],
                [
                    'purity.one' => [
                        'definition' => $prefix . 'Constructor',
                        'value' => $prefix . 'Factory',
                        'handler' => $prefix . 'Provider',
                        'outcome' => $prefix . 'Container',
                        'strategy' => $prefix . 'DB',
                        'ephemeral' => false,
                    ],
                    'purity.two' => [
                        'definition' => $prefix . 'Handler',
                        'value' => $prefix . 'Middleware',
                        'handler' => $prefix . 'Source',
                        'outcome' => $prefix . 'Outcome',
                        'strategy' => $prefix . 'Strategy',
                        'ephemeral' => false,
                    ],
                ],
                [[], []],
            );

            $projection = new RouteProjectionProducer()->produce('build-1', $manifest);
        } finally {
            spl_autoload_unregister($loader);
        }

        self::assertSame([], $autoloaded);
        self::assertCount(3, $projection->records);
        self::assertContains('custom:' . $prefix . 'DB', array_column($projection->records, 'strategy'));
        self::assertContains('custom:' . $prefix . 'Strategy', array_column($projection->records, 'strategy'));
    }

    #[DataProvider('reservedRoutes')]
    public function testRejectsEveryFixedStatusCollision(string $path): void
    {
        $manifest = new HttpOperationManifest(
            ['GET' => [$path => 'collision.read']],
            ['collision.read' => $this->operation(Inline::class)],
            [[], []],
        );

        $this->expectException(InvalidArgumentException::class);
        new RouteProjectionProducer()->produce('build-1', $manifest);
    }

    /** @return iterable<string, array{string}> */
    public static function reservedRoutes(): iterable
    {
        yield 'canonical placeholder' => ['/operations/{operationId}'];
        yield 'renamed placeholder' => ['/operations/{id}'];
        yield 'static segment' => ['/operations/current'];
    }

    public function testAllowsPostAndNestedGetRoutesAroundTheFixedStatusRoute(): void
    {
        $manifest = new HttpOperationManifest(
            [
                'POST' => ['/operations/{operationId}' => 'status.submit'],
                'GET' => ['/operations/{operationId}/outcome' => 'status.outcome'],
            ],
            [
                'status.submit' => $this->operation(Inline::class),
                'status.outcome' => $this->operation(Deferred::class),
            ],
            [[], []],
        );

        $projection = new RouteProjectionProducer()->produce('build-1', $manifest);
        self::assertCount(3, $projection->records);
        self::assertSame('/operations/{operationId}', $projection->records[0]['path']);
        self::assertSame('POST', $projection->records[1]['method']);
        self::assertSame('GET', $projection->records[2]['method']);
    }

    /** @return array<string, string|bool> */
    private function operation(string $strategy): array
    {
        return [
            'definition' => 'Fixture\\Operation',
            'value' => 'Fixture\\Value',
            'handler' => 'Fixture\\Handler',
            'outcome' => 'Fixture\\Outcome',
            'strategy' => $strategy,
            'ephemeral' => false,
        ];
    }

    /** @return array{method: string, path: string, strategy: string, operationType: string, origin: string} */
    private function routeRecord(string $path): array
    {
        return [
            'method' => 'GET',
            'path' => $path,
            'strategy' => 'inline',
            'operationType' => 'route.read',
            'origin' => 'operation_manifest',
        ];
    }
}
