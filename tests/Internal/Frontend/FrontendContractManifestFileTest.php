<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Frontend;

use BlackOps\Internal\Frontend\FrontendContractManifest;
use BlackOps\Internal\Frontend\FrontendContractManifestCodec;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrontendContractManifestFileTest extends TestCase
{
    public function testWritesAndLoadsVersionedArtifactAtomically(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-frontend-file-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/frontend.php';
        $file = new FrontendContractManifestFile();
        $file->write(new FrontendContractManifest([]), $path, 'frontend-file-build');

        $artifact = $file->loadArtifact($path);
        self::assertSame(FrontendContractManifestFile::SCHEMA_VERSION, $artifact->schemaVersion);
        self::assertSame('frontend-file-build', $artifact->applicationBuildId);
        self::assertSame([], $artifact->manifest->operations);
        self::assertSame([], glob($directory . '/frontend-manifest-*') ?: []);
    }

    public function testRejectsUnsupportedSchema(): void
    {
        $path = sys_get_temp_dir() . '/blackops-frontend-invalid-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents(
            $path,
            "<?php return ['schemaVersion' => 999, 'applicationBuildId' => 'build', 'payload' => ['operations' => []]];",
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('schema version');
        new FrontendContractManifestFile()->loadArtifact($path);
    }

    public function testRejectsLegacySchemaVersion(): void
    {
        $path = sys_get_temp_dir() . '/blackops-frontend-legacy-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents(
            $path,
            "<?php return ['schemaVersion' => 1, 'applicationBuildId' => 'build', 'payload' => ['operations' => []]];",
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('schema version');
        new FrontendContractManifestFile()->loadArtifact($path);
    }

    public function testRejectsLegacyAndUnknownValueAndOutcomeScalarKinds(): void
    {
        foreach (['number', 'decimal'] as $invalidType) {
            $artifacts = [];
            $value = $this->validArtifact();
            $value['payload']['operations'][0]['value']['fields'][0]['type'] = $invalidType;
            $artifacts[] = $value;
            $outcome = $this->validArtifact();
            $outcome['payload']['operations'][0]['outcome']['fields'][0]['type']['scalar'] = $invalidType;
            $artifacts[] = $outcome;

            foreach ($artifacts as $artifact) {
                try {
                    new FrontendContractManifestCodec()->decode($artifact);
                    self::fail('Expected invalid frontend scalar kind.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString('enum field', $exception->getMessage());
                }
            }
        }
    }

    public function testRejectsManifestWithoutEphemeralFlag(): void
    {
        $artifact = $this->validArtifact();
        unset($artifact['payload']['operations'][0]['ephemeral']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('schema is invalid');
        new FrontendContractManifestCodec()->decode($artifact);
    }

    public function testDecodesAllVersionThreeScalarKindsForValuesAndOutcomes(): void
    {
        foreach (['string', 'integer', 'float', 'boolean'] as $type) {
            $artifact = $this->validArtifact();
            $artifact['payload']['operations'][0]['value']['fields'][0]['type'] = $type;
            $artifact['payload']['operations'][0]['outcome']['fields'][0]['type']['scalar'] = $type;

            $manifest = new FrontendContractManifestCodec()->decode($artifact)->manifest;

            self::assertSame($type, $manifest->operations[0]->value->fields[0]->type);
            self::assertSame($type, $manifest->operations[0]->outcome->fields[0]->type->scalar);
        }
    }

    public function testRoundTripsRecursiveDtoAndListSchema(): void
    {
        $artifact = $this->validArtifact();
        $artifact['payload']['operations'][0]['outcome']['fields'][0]['type'] = [
            'kind' => 'list',
            'nullable' => false,
            'class' => 'App\\OrderSummary',
            'fields' => [[
                'name' => 'owner',
                'type' => [
                    'kind' => 'dto',
                    'nullable' => true,
                    'class' => 'App\\OwnerSummary',
                    'fields' => [[
                        'name' => 'id',
                        'type' => ['kind' => 'scalar', 'nullable' => false, 'scalar' => 'string'],
                    ]],
                ],
            ]],
        ];

        $codec = new FrontendContractManifestCodec();
        $decoded = $codec->decode($artifact);
        $encoded = $codec->encode($decoded->manifest, $decoded->applicationBuildId);

        self::assertSame($artifact, $encoded);
        $type = $decoded->manifest->operations[0]->outcome->fields[0]->type;
        self::assertSame('list', $type->kind);
        self::assertSame('dto', $type->fields[0]->type->kind);
        self::assertTrue($type->fields[0]->type->nullable);
    }

    public function testRejectsCorruptRecursiveOutcomeSchema(): void
    {
        $cases = [];
        $unknown = $this->validArtifact();
        $unknown['payload']['operations'][0]['outcome']['fields'][0]['type']['credential'] = 'secret';
        $cases[] = $unknown;
        $nullableList = $this->validArtifact();
        $nullableList['payload']['operations'][0]['outcome']['fields'][0]['type'] = [
            'kind' => 'list',
            'nullable' => true,
            'class' => 'App\\Item',
            'fields' => [],
        ];
        $cases[] = $nullableList;
        $missingClass = $this->validArtifact();
        $missingClass['payload']['operations'][0]['outcome']['fields'][0]['type'] = [
            'kind' => 'dto',
            'nullable' => false,
            'fields' => [],
        ];
        $cases[] = $missingClass;

        foreach ($cases as $artifact) {
            try {
                new FrontendContractManifestCodec()->decode($artifact);
                self::fail('Expected corrupt recursive outcome schema.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('outcome', $exception->getMessage());
            }
        }
    }

    public function testInvalidWritePreservesExistingArtifactAndCleansTemporaryFile(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-frontend-preserve-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $path = $directory . '/frontend.php';
        $file = new FrontendContractManifestFile();
        $file->write(new FrontendContractManifest([]), $path, 'preserved-build');
        $before = file_get_contents($path);

        try {
            $file->write(new FrontendContractManifest([]), $path, '');
            self::fail('Expected invalid frontend build ID.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('build ID', $exception->getMessage());
        }

        self::assertSame($before, file_get_contents($path));
        self::assertSame([], glob($directory . '/frontend-manifest-*') ?: []);
        self::assertSame('preserved-build', $file->loadArtifact($path)->applicationBuildId);
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     applicationBuildId: string,
     *     payload: array{operations: list<array<string, mixed>>}
     * }
     */
    private function validArtifact(): array
    {
        return [
            'schemaVersion' => FrontendContractManifestCodec::SCHEMA_VERSION,
            'applicationBuildId' => 'build',
            'payload' => [
                'operations' => [[
                    'typeId' => 'order.create',
                    'definition' => 'App\\CreateOrder',
                    'exportName' => 'CreateOrder',
                    'module' => 'operations/order/create-order.ts',
                    'method' => 'POST',
                    'path' => '/orders',
                    'strategy' => 'inline',
                    'ephemeral' => false,
                    'value' => [
                        'class' => 'App\\CreateOrderValue',
                        'fields' => [[
                            'name' => 'quantity',
                            'type' => 'integer',
                            'nullable' => false,
                            'required' => true,
                            'source' => 'body',
                            'transportName' => 'quantity',
                            'sensitive' => false,
                            'validations' => [],
                        ]],
                    ],
                    'outcome' => [
                        'class' => 'App\\OrderCreated',
                        'mode' => 'outcome',
                        'fields' => [[
                            'name' => 'total',
                            'type' => [
                                'kind' => 'scalar',
                                'nullable' => false,
                                'scalar' => 'float',
                            ],
                        ]],
                    ],
                ]],
            ],
        ];
    }
}
