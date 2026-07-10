<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Internal\Discovery\ComposerAutoloadMetadata;
use BlackOps\Internal\Discovery\DiscoveryRoots;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ComposerAutoloadMetadataTest extends TestCase
{
    public function testBuildsCandidatesFromPsr4AndClassmapMetadataWithinRoots(): void
    {
        $metadata = new ComposerAutoloadMetadata(
            $this->fixtureBase(),
            [
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\Convention\\' => 'DiscoveryRoot/Convention',
            ],
            [
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\ClassmapOperation' => 'DiscoveryRoot/MismatchedOperations.php',
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\Outside\\OutsideOperation' => 'Outside/OutsideOperation.php',
            ],
        );

        $candidates = $metadata->candidates(DiscoveryRoots::from([$this->discoveryRoot()]));

        self::assertSame(
            [
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\ClassmapOperation',
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\Convention\\Psr4Operation',
            ],
            array_keys($candidates),
        );
        self::assertArrayNotHasKey(
            'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\Outside\\OutsideOperation',
            $candidates,
        );
    }

    public function testRejectsInvalidPsr4Metadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ComposerAutoloadMetadata($this->fixtureBase(), ['InvalidPrefix' => 'DiscoveryRoot'], []);
    }

    public function testRejectsInvalidClassmapMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ComposerAutoloadMetadata($this->fixtureBase(), [], ['Example\\Missing' => 'DiscoveryRoot/missing.php']);
    }

    public function testRejectsClassMappedToDifferentFiles(): void
    {
        $metadata = new ComposerAutoloadMetadata(
            $this->fixtureBase(),
            [
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\Convention\\' => 'DiscoveryRoot/Convention',
            ],
            [
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\Convention\\Psr4Operation' => 'DiscoveryRoot/MismatchedOperations.php',
            ],
        );

        $this->expectException(InvalidArgumentException::class);

        $metadata->candidates(DiscoveryRoots::from([$this->discoveryRoot()]));
    }

    private function fixtureBase(): string
    {
        return __DIR__ . '/Fixture';
    }

    private function discoveryRoot(): string
    {
        return $this->fixtureBase() . '/DiscoveryRoot';
    }
}
