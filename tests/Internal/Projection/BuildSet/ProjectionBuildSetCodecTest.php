<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Projection\BuildSet;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @mago-expect lint:too-many-methods
 */
final class ProjectionBuildSetCodecTest extends TestCase
{
    public function testEncodesAndDecodesTheClosedThreeFamilyWire(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $command = [
            [
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List operations',
                'origin' => 'framework',
                'hidden' => false,
            ],
        ];
        $route = [
            [
                'method' => 'GET',
                'path' => '/operations/{operationId}',
                'strategy' => 'inline',
                'operationType' => 'operation.read',
                'origin' => 'operation_manifest',
            ],
        ];
        $schedule = [
            [
                'name' => 'operation.reconcile',
                'operationType' => 'operation.reconcile',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ],
        ];

        $root = $codec->root('build-2026-08-26', [
            'command' => $command,
            'route' => $route,
            'schedule' => $schedule,
        ]);

        self::assertStringEndsWith("\n", $root['bytes']);
        self::assertStringNotContainsString("\r", $root['bytes']);
        self::assertStringNotContainsString("\xEF\xBB\xBF", $root['bytes']);
        self::assertSame($root['buildSetHash'], $root['root']['buildSetHash']);

        $decodedRoot = $codec->decodeRoot($root['bytes']);
        self::assertSame('projection_build_set', $decodedRoot['kind']);
        self::assertSame(['command', 'route', 'schedule'], array_keys($decodedRoot['projections']));

        foreach ($root['projections'] as $key => $projection) {
            $decoded = $codec->decodeProjection(
                bytes: $projection->bytes,
                expectedKind: $projection->kind,
                expectedApplicationBuildId: 'build-2026-08-26',
                expectedInputHash: $projection->inputHash,
            );
            self::assertSame($projection->records, $decoded->records, $key);
            self::assertSame($projection->sha256, $decoded->sha256, $key);
        }
    }

    public function testHashGoldenAndStoredLineEndingMatrix(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $root = $codec->root('build-2026-08-26', [
            'command' => [[
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List operations',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => [[
                'method' => 'GET',
                'path' => '/operations/{operationId}',
                'strategy' => 'inline',
                'operationType' => 'operation.read',
                'origin' => 'operation_manifest',
            ]],
            'schedule' => [[
                'name' => 'operation.reconcile',
                'operationType' => 'operation.reconcile',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ]],
        ]);

        self::assertSame('c1cdf2019a0d4e130da65ce4343a1802a9d689b29825c50d0406c1b52cc264aa', $root['buildSetHash']);
        self::assertSame(
            'e81d63209fca84fe6b6b0ec7f26dfad2f95e84f63c77a7eda54a0ba308c37f4f',
            $root['projections']['command']->inputHash,
        );
        self::assertSame(
            'e0064874395b832ee98f78fcfdd7dd1f2f16936d70db3fef2b13b2080a3f2e49',
            $root['projections']['route']->inputHash,
        );
        self::assertSame(
            'f04618c73c3ba0fa162b3fc0494b26b4506fbfe94f4d96d6ceb54cfedeffec04',
            $root['projections']['schedule']->inputHash,
        );

        $projection = $root['projections']['command'];
        $invalid = [
            'bom' => "\xEF\xBB\xBF" . $projection->bytes,
            'crlf' => str_replace("\n", "\r\n", $projection->bytes),
            'extra-final-lf' => $projection->bytes . "\n",
            'leading-space' => ' ' . $projection->bytes,
        ];
        foreach ($invalid as $label => $bytes) {
            $this->assertProjectionRejected(static fn() => $codec->decodeProjection($bytes), $label);
        }
    }

    public function testCanonicalJsonLeavesUnicodeLineTerminatorsAsLiteralUtf8(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $canonical = $codec->canonicalJson(['message' => "before\u{2028}after\u{2029}"]);

        self::assertSame("{\"message\":\"before\xE2\x80\xA8after\xE2\x80\xA9\"}", $canonical);
    }

    public function testUnicodeLineTerminatorsHaveIndependentExactThreeWireHashGoldens(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $root = $codec->root("build-\u{2028}-\u{2029}", [
            'command' => [[
                'name' => 'list',
                'aliases' => ['ls'],
                'description' => 'List operations',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => [[
                'method' => 'GET',
                'path' => '/operations/{operationId}',
                'strategy' => 'inline',
                'operationType' => 'operation.read',
                'origin' => 'operation_manifest',
            ]],
            'schedule' => [[
                'name' => 'operation.reconcile',
                'operationType' => 'operation.reconcile',
                'cron' => '*/5 * * * *',
                'timezone' => 'UTC',
                'strategy' => 'deferred',
            ]],
        ]);

        self::assertSame('c6c42eab68faeaf0aa81fbf76deec891b36b5b8556eedea39e26636f9568f991', $root['buildSetHash']);
        self::assertSame(
            'd04f3f722f0439b3aa5b2014bce8bcdfc301fed4d26b5500ea454116668a51b6',
            $root['projections']['command']->inputHash,
        );
        self::assertSame(
            'b1748e7d1bde9f598885b59c50b5737c12d71b6af2da0b4ff40769930eb1742d',
            $root['projections']['command']->sha256,
        );
        self::assertSame(
            'cd747f1d3dc8e975cc42b0c5dc64ef3554852b7ecdb63869bbde61765b3f984a',
            $root['projections']['route']->inputHash,
        );
        self::assertSame(
            '250da1d69cb389537620d149ab7864812085b8a1e8961dfffbb8315099b68934',
            $root['projections']['route']->sha256,
        );
        self::assertSame(
            'c771f597c6528fda4c3ad61613ffae23aaaf05e99b7b083d678f26dbf24f0336',
            $root['projections']['schedule']->inputHash,
        );
        self::assertSame(
            'cb598e457978fc5b86f6d116fa32afbe238379400c397b3938bb1246cb9690ed',
            $root['projections']['schedule']->sha256,
        );
    }

    public function testRejectsUnicodeLineTerminatorsInCommandDescriptions(): void
    {
        $codec = new ProjectionBuildSetCodec();
        foreach (["before\u{2028}after", "before\u{2029}after"] as $description) {
            $this->assertProjectionRejected(
                static fn() => $codec->projection('blackops_command_projection', 'build-1', [[
                    'name' => 'list',
                    'aliases' => [],
                    'description' => $description,
                    'origin' => 'framework',
                    'hidden' => false,
                ]]),
                'command description line terminator',
            );
        }
    }

    public function testRejectsClosedWireMutationMatrix(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $projection = $codec->projection('blackops_command_projection', 'build-1', [[
            'name' => 'list',
            'aliases' => [],
            'description' => '',
            'origin' => 'framework',
            'hidden' => false,
        ]]);
        $document = $projection->toArray();
        $mutations = [];

        $unknown = $document;
        $unknown['unknown'] = true;
        $mutations['unknown field'] = $codec->canonicalJson($unknown) . "\n";

        $missing = $document;
        unset($missing['records']);
        $mutations['missing field'] = $codec->canonicalJson($missing) . "\n";

        $wrongType = $document;
        $wrongType['records'] = 'not-a-list';
        $mutations['wrong type'] = $codec->canonicalJson($wrongType) . "\n";

        $wrongKind = $document;
        $wrongKind['kind'] = 'blackops_route_projection';
        $mutations['wrong kind'] = $codec->canonicalJson($wrongKind) . "\n";

        $wrongInput = $document;
        $wrongInput['inputHash'] = str_repeat('0', 64);
        $mutations['wrong input hash'] = $codec->canonicalJson($wrongInput) . "\n";

        $duplicate = str_replace('"name":"list"', '"name":"list","name":"list"', $projection->bytes);
        $mutations['duplicate field'] = $duplicate;

        foreach ($mutations as $label => $bytes) {
            $this->assertProjectionRejected(static fn() => $codec->decodeProjection($bytes), $label);
        }
    }

    public function testRejectsFieldGrammarAndCapPlusOneMatrix(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $command = [
            'name' => 'list',
            'aliases' => [],
            'description' => '',
            'origin' => 'framework',
            'hidden' => false,
        ];
        $route = [
            'method' => 'GET',
            'path' => '/health',
            'strategy' => 'inline',
            'operationType' => 'operation.health',
            'origin' => 'operation_manifest',
        ];
        $schedule = [
            'name' => 'operation.health',
            'operationType' => 'operation.health',
            'cron' => '* * * * *',
            'timezone' => 'UTC',
            'strategy' => 'inline',
        ];
        $cases = [
            'command comma' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['name' => 'list,all'])],
            ),
            'command empty colon segment' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['name' => 'list::all'])],
            ),
            'command control' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['name' => "list\tall"])],
            ),
            'command non-nfc' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['name' => "e\u{301}"])],
            ),
            'command name cap plus one' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['name' => str_repeat('a', 257)])],
            ),
            'command alias cap plus one' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['aliases' => [str_repeat('a', 257)]])],
            ),
            'command description cap plus one' => static fn() => $codec->projection(
                'blackops_command_projection',
                'build-1',
                [array_replace($command, ['description' => str_repeat('a', 257)])],
            ),
            'command record cap plus one' => static function () use ($codec, $command): void {
                $records = [];
                for ($index = 0; $index < 4097; $index++) {
                    $records[] = array_replace($command, ['name' => 'item' . $index]);
                }
                $codec->projection('blackops_command_projection', 'build-1', $records);
            },
            'route method lowercase' => static fn() => $codec->projection(
                'blackops_route_projection',
                'build-1',
                [array_replace($route, ['method' => 'get'])],
            ),
            'route method cap plus one' => static fn() => $codec->projection(
                'blackops_route_projection',
                'build-1',
                [array_replace($route, ['method' => str_repeat('G', 17)])],
            ),
            'route path cap plus one' => static fn() => $codec->projection(
                'blackops_route_projection',
                'build-1',
                [array_replace($route, ['path' => '/' . str_repeat('a', 2048)])],
            ),
            'route identifier grammar' => static fn() => $codec->projection(
                'blackops_route_projection',
                'build-1',
                [array_replace($route, ['operationType' => 'Operation.Health'])],
            ),
            'route strategy cap plus one' => static fn() => $codec->projection(
                'blackops_route_projection',
                'build-1',
                [array_replace($route, ['strategy' => 'custom:' . str_repeat('A', 1020)])],
            ),
            'schedule identifier grammar' => static fn() => $codec->projection(
                'blackops_schedule_projection',
                'build-1',
                [array_replace($schedule, ['name' => 'Operation.Health'])],
            ),
            'schedule cron field count' => static fn() => $codec->projection(
                'blackops_schedule_projection',
                'build-1',
                [array_replace($schedule, ['cron' => '* * * *'])],
            ),
            'schedule timezone cap plus one' => static fn() => $codec->projection(
                'blackops_schedule_projection',
                'build-1',
                [array_replace($schedule, ['timezone' => str_repeat('A', 129)])],
            ),
            'schedule strategy cap plus one' => static fn() => $codec->projection(
                'blackops_schedule_projection',
                'build-1',
                [array_replace($schedule, ['strategy' => 'custom:' . str_repeat('A', 1020)])],
            ),
        ];

        foreach ($cases as $label => $operation) {
            $this->assertProjectionRejected($operation, $label);
        }
    }

    public function testRejectsRootSelfHashPathKindAndDuplicateMutationMatrix(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $root = $codec->root('build-1', [
            'command' => [[
                'name' => 'list',
                'aliases' => [],
                'description' => '',
                'origin' => 'framework',
                'hidden' => false,
            ]],
            'route' => [[
                'method' => 'GET',
                'path' => '/health',
                'strategy' => 'inline',
                'operationType' => 'operation.health',
                'origin' => 'operation_manifest',
            ]],
            'schedule' => [[
                'name' => 'operation.health',
                'operationType' => 'operation.health',
                'cron' => '* * * * *',
                'timezone' => 'UTC',
                'strategy' => 'inline',
            ]],
        ]);
        $mutations = [];

        $selfHash = $root['root'];
        $selfHash['buildSetHash'] = str_repeat('0', 64);
        $mutations['root self hash'] = $codec->canonicalJson($selfHash) . "\n";

        foreach (['/escape', 'command\\escape', 'route-' . str_repeat('a', 64) . '.json'] as $path) {
            $pathMutation = $root['root'];
            $pathMutation['projections']['command']['path'] = $path;
            $mutations['path ' . $path] = $this->rehashedRootBytes($pathMutation, $codec);
        }

        $kindMutation = $root['root'];
        $kindMutation['projections']['command']['kind'] = 'blackops_route_projection';
        $mutations['swapped kind'] = $this->rehashedRootBytes($kindMutation, $codec);

        $duplicatePath = $root['root'];
        $duplicatePath['projections']['command']['path'] = $duplicatePath['projections']['route']['path'];
        $mutations['duplicate path'] = $this->rehashedRootBytes($duplicatePath, $codec);

        $count = 0;
        $duplicateKind = str_replace('"command":{', '"command":{},"command":{', $root['bytes'], $count);
        self::assertSame(1, $count);
        $mutations['duplicate root key'] = $duplicateKind;

        foreach ($mutations as $label => $bytes) {
            $this->assertRootRejected($codec, $bytes, $label);
        }
    }

    public function testNormalizesAliasesAndRecordsToUnsignedByteOrder(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $projection = $codec->projection('blackops_command_projection', 'build-1', [
            [
                'name' => 'zeta',
                'aliases' => ['zz', 'aa'],
                'description' => '',
                'origin' => 'application',
                'hidden' => false,
            ],
            [
                'name' => 'alpha',
                'aliases' => [],
                'description' => '',
                'origin' => 'application',
                'hidden' => false,
            ],
        ]);

        self::assertSame('alpha', $projection->records[0]['name']);
        self::assertSame(['aa', 'zz'], $projection->records[1]['aliases']);
    }

    public function testRejectsCrossOriginRouteMetadata(): void
    {
        $this->expectException(ProjectionBuildSetValidationException::class);

        new ProjectionBuildSetCodec()->projection('blackops_route_projection', 'build-1', [[
            'method' => 'GET',
            'path' => '/health',
            'strategy' => 'inline',
            'operationType' => 'operation.health',
            'origin' => 'framework_fixed',
        ]]);
    }

    public function testRejectsNonIanaTimezoneAndDuplicateFields(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $this->expectException(ProjectionBuildSetValidationException::class);

        $codec->projection('blackops_schedule_projection', 'build-1', [[
            'name' => 'operation.reconcile',
            'operationType' => 'operation.reconcile',
            'cron' => '* * * * *',
            'timezone' => '+02:00',
            'strategy' => 'inline',
        ]]);
    }

    public function testRejectsDuplicateJsonMembersAndWrongCanonicalBytes(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $projection = $codec->projection('blackops_command_projection', 'build-1', [[
            'name' => 'list',
            'aliases' => [],
            'description' => '',
            'origin' => 'framework',
            'hidden' => false,
        ]]);
        $duplicate = str_replace('"name":"list"', '"name":"list","name":"list"', $projection->bytes);

        $this->expectException(ProjectionBuildSetValidationException::class);
        $codec->decodeProjection($duplicate);
    }

    public function testRejectsCommandAliasCollisionAndCapPlusOne(): void
    {
        $codec = new ProjectionBuildSetCodec();
        try {
            $codec->projection('blackops_command_projection', 'build-1', [[
                'name' => 'list',
                'aliases' => ['list'],
                'description' => '',
                'origin' => 'framework',
                'hidden' => false,
            ]]);
            self::fail('Expected command alias collision rejection.');
        } catch (ProjectionBuildSetValidationException) {
            self::assertTrue(true);
        }

        $aliases = [];
        for ($index = 0; $index < 33; $index++) {
            $aliases[] = 'alias' . $index;
        }
        $this->expectException(ProjectionBuildSetValidationException::class);
        $codec->projection('blackops_command_projection', 'build-1', [[
            'name' => 'list',
            'aliases' => $aliases,
            'description' => '',
            'origin' => 'framework',
            'hidden' => false,
        ]]);
    }

    public function testRejectsUnsortedStoredRecords(): void
    {
        $codec = new ProjectionBuildSetCodec();
        $first = $codec->projection('blackops_command_projection', 'build-1', [[
            'name' => 'alpha',
            'aliases' => [],
            'description' => '',
            'origin' => 'application',
            'hidden' => false,
        ]]);
        $second = $codec->projection('blackops_command_projection', 'build-1', [[
            'name' => 'zeta',
            'aliases' => [],
            'description' => '',
            'origin' => 'application',
            'hidden' => false,
        ]]);
        $document = [
            'schemaVersion' => 1,
            'kind' => 'blackops_command_projection',
            'applicationBuildId' => 'build-1',
            'inputHash' => str_repeat('a', 64),
            'records' => [$second->records[0], $first->records[0]],
        ];

        $this->expectException(ProjectionBuildSetValidationException::class);
        $codec->decodeProjection($codec->canonicalJson($document) . "\n");
    }

    private function assertProjectionRejected(callable $operation, string $label): void
    {
        try {
            $operation();
        } catch (ProjectionBuildSetValidationException) {
            self::assertTrue(true);
            return;
        }

        self::fail('Expected projection rejection: ' . $label);
    }

    private function assertRootRejected(ProjectionBuildSetCodec $codec, string $bytes, string $label): void
    {
        try {
            $codec->decodeRoot($bytes);
        } catch (ProjectionBuildSetValidationException) {
            self::assertTrue(true);
            return;
        }

        self::fail('Expected root rejection: ' . $label);
    }

    /** @param array<string, mixed> $root */
    private function rehashedRootBytes(array $root, ProjectionBuildSetCodec $codec): string
    {
        $withoutHash = $root;
        unset($withoutHash['buildSetHash']);
        $canonical = $codec->canonicalJson($withoutHash);
        $domain = 'blackops.projection-build-set.v1';
        $root['buildSetHash'] = hash(
            'sha256',
            's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($canonical) . ':' . $canonical,
        );

        return $codec->canonicalJson($root) . "\n";
    }
}
