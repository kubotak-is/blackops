<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\About;

use BlackOps\Internal\About\AboutMetadata;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AboutMetadataTest extends TestCase
{
    public function testClosedMetadataContainsOnlyPackageAndCurrentProcessRuntime(): void
    {
        $metadata = new AboutMetadata('1.2.0@dev', '8.5.0', 'cli');

        self::assertSame(
            [
                'package' => [
                    'name' => 'blackops/framework',
                    'version' => '1.2.0@dev',
                ],
                'runtime' => [
                    'phpVersion' => '8.5.0',
                    'sapi' => 'cli',
                    'scope' => 'current_process',
                ],
            ],
            $metadata->toArray(),
        );
        self::assertSame('1.2.0@dev', $metadata->packageVersion());
    }

    public function testAcceptedVersionAndRuntimeGrammarBoundaries(): void
    {
        foreach ([
            '1.3.0',
            'v1.3.0',
            '1.2.0@dev',
            '1.3.x-dev',
            'dev-main',
            'dev-feature/x',
        ] as $version) {
            self::assertSame($version, new AboutMetadata($version, '8.5.0', 'cli')->version);
        }

        self::assertSame(
            str_repeat('a', 128),
            new AboutMetadata(str_repeat('a', 128), str_repeat('b', 64), str_repeat('c', 32))->version,
        );
    }

    public function testVersionValidationHasIndependentBoundariesAndGrammarChecks(): void
    {
        self::assertSame('a', new AboutMetadata('a', '8.5.0', 'cli')->version);
        self::assertSame(str_repeat('a', 128), new AboutMetadata(str_repeat('a', 128), '8.5.0', 'cli')->version);

        foreach (['', str_repeat('a', 129), '=1.2.0', '1.2.0 dev', "1.2.0\nsecret", '1.2.0é'] as $version) {
            $this->assertInvalidMetadata($version, '8.5.0', 'cli');
        }
    }

    public function testPhpVersionValidationHasIndependentBoundariesAndGrammarChecks(): void
    {
        self::assertSame('a', new AboutMetadata('1.2.0', 'a', 'cli')->phpVersion);
        self::assertSame(str_repeat('a', 64), new AboutMetadata('1.2.0', str_repeat('a', 64), 'cli')->phpVersion);

        foreach (['', str_repeat('a', 65), '=8.5.0', '8.5.0 dev', "8.5.0\nsecret", '8.5.0é'] as $phpVersion) {
            $this->assertInvalidMetadata('1.2.0', $phpVersion, 'cli');
        }
    }

    public function testSapiValidationHasIndependentBoundariesAndGrammarChecks(): void
    {
        self::assertSame('a', new AboutMetadata('1.2.0', '8.5.0', 'a')->sapi);
        self::assertSame(str_repeat('a', 32), new AboutMetadata('1.2.0', '8.5.0', str_repeat('a', 32))->sapi);

        foreach (['', str_repeat('a', 33), '=cli', 'cli worker', "cli\nsecret", 'clié'] as $sapi) {
            $this->assertInvalidMetadata('1.2.0', '8.5.0', $sapi);
        }
    }

    private function assertInvalidMetadata(string $version, string $phpVersion, string $sapi): void
    {
        try {
            new AboutMetadata($version, $phpVersion, $sapi);
            self::fail('Expected invalid metadata to be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
