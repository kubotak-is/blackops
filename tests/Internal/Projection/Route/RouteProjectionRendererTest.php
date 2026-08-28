<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\Route;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use BlackOps\Internal\Projection\Route\RouteProjectionRenderer;
use PHPUnit\Framework\TestCase;

final class RouteProjectionRendererTest extends TestCase
{
    public function testHumanOutputUsesExactHeadersNullCellsAndFinalLineFeed(): void
    {
        $bytes = new RouteProjectionRenderer()->render($this->records());

        self::assertSame(
            "+--------+---------------------------+----------+----------------+--------------------+\n"
            . "| Method | Path                      | Strategy | Operation Type | Origin             |\n"
            . "+--------+---------------------------+----------+----------------+--------------------+\n"
            . "| GET    | /operations/{operationId} |          |                | framework_fixed    |\n"
            . "| POST   | /reports                  | inline   | report.create  | operation_manifest |\n"
            . "+--------+---------------------------+----------+----------------+--------------------+\n",
            $bytes,
        );
        self::assertStringContainsString('Method', $bytes);
        self::assertStringContainsString('Path', $bytes);
        self::assertStringContainsString('Strategy', $bytes);
        self::assertStringContainsString('Operation Type', $bytes);
        self::assertStringContainsString('Origin', $bytes);
        self::assertStringContainsString('/operations/{operationId}', $bytes);
        self::assertStringContainsString('inline', $bytes);
        self::assertStringNotContainsString("\x1B", $bytes);
        self::assertStringEndsWith("\n", $bytes);
        self::assertFalse(str_ends_with($bytes, "\n\n"));
    }

    public function testJsonIsClosedJcsRouteWireWithoutApplicationStatus(): void
    {
        $bytes = new RouteProjectionRenderer()->render($this->records(), json: true);

        self::assertSame(
            '{"routes":[{"method":"GET","operationType":null,"origin":"framework_fixed",'
            . '"path":"/operations/{operationId}","strategy":null},{"method":"POST",'
            . '"operationType":"report.create","origin":"operation_manifest","path":"/reports",'
            . '"strategy":"inline"}],"schemaVersion":1}'
            . "\n",
            $bytes,
        );
        self::assertSame(
            [
                'routes' => [
                    [
                        'method' => 'GET',
                        'operationType' => null,
                        'origin' => 'framework_fixed',
                        'path' => '/operations/{operationId}',
                        'strategy' => null,
                    ],
                    [
                        'method' => 'POST',
                        'operationType' => 'report.create',
                        'origin' => 'operation_manifest',
                        'path' => '/reports',
                        'strategy' => 'inline',
                    ],
                ],
                'schemaVersion' => 1,
            ],
            json_decode($bytes, true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString('applicationProjectionStatus', $bytes);
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringNotContainsString("\xEF\xBB\xBF", $bytes);
        self::assertStringEndsWith("\n", $bytes);
    }

    public function testAdversarialPathRemainsLiteralForHumanAndJson(): void
    {
        $records = [[
            'method' => 'GET',
            'path' => '/literal/<info>path</info>',
            'strategy' => 'custom:Vendor\\Strategy',
            'operationType' => 'literal.read',
            'origin' => 'operation_manifest',
        ]];

        $human = new RouteProjectionRenderer()->render($records, false);
        $json = new RouteProjectionRenderer()->render($records, true);

        self::assertStringContainsString('/literal/<info>path</info>', $human);
        self::assertStringContainsString('/literal/<info>path</info>', $json);
        self::assertStringNotContainsString("\x1B", $human . $json);
    }

    public function testMalformedRowsFailClosed(): void
    {
        $this->expectException(ProjectionBuildSetValidationException::class);
        new RouteProjectionRenderer()->render([[
            'method' => 'get',
            'path' => '/invalid',
            'strategy' => 'inline',
            'operationType' => 'invalid.read',
            'origin' => 'operation_manifest',
        ]]);
    }

    /** @return list<array<string, mixed>> */
    private function records(): array
    {
        return [
            [
                'method' => 'POST',
                'path' => '/reports',
                'strategy' => 'inline',
                'operationType' => 'report.create',
                'origin' => 'operation_manifest',
            ],
            [
                'method' => 'GET',
                'path' => '/operations/{operationId}',
                'strategy' => null,
                'operationType' => null,
                'origin' => 'framework_fixed',
            ],
        ];
    }
}
