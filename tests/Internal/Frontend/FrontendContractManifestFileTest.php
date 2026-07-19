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
            foreach ([['value', 0], ['outcome', 0]] as [$section, $index]) {
                $artifact = $this->validArtifact();
                $artifact['payload']['operations'][0][$section]['fields'][$index]['type'] = $invalidType;

                try {
                    new FrontendContractManifestCodec()->decode($artifact);
                    self::fail('Expected invalid frontend scalar kind.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString('enum field', $exception->getMessage());
                }
            }
        }
    }

    public function testDecodesAllVersionTwoScalarKindsForValuesAndOutcomes(): void
    {
        foreach (['string', 'integer', 'float', 'boolean'] as $type) {
            $artifact = $this->validArtifact();
            $artifact['payload']['operations'][0]['value']['fields'][0]['type'] = $type;
            $artifact['payload']['operations'][0]['outcome']['fields'][0]['type'] = $type;

            $manifest = new FrontendContractManifestCodec()->decode($artifact)->manifest;

            self::assertSame($type, $manifest->operations[0]->value->fields[0]->type);
            self::assertSame($type, $manifest->operations[0]->outcome->fields[0]->type);
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
                            'type' => 'float',
                            'nullable' => false,
                        ]],
                    ],
                ]],
            ],
        ];
    }
}
