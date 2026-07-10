<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Internal\Discovery\ComposerAutoloadMetadata;
use BlackOps\Internal\Discovery\OperationSourceDiscovery;
use BlackOps\Tests\Internal\Discovery\Fixture\DiscoveryRoot\ClassmapOperation;
use BlackOps\Tests\Internal\Discovery\Fixture\DiscoveryRoot\Convention\Psr4Operation;
use BlackOps\Tests\Internal\Discovery\Fixture\DiscoveryRoot\IndirectOperation;
use BlackOps\Tests\Internal\Discovery\Fixture\DiscoveryRoot\TokenOnlyOperation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationSourceDiscoveryTest extends TestCase
{
    public function testDiscoversConcreteOperationsFromMetadataAndTokenFallbackDeterministically(): void
    {
        $marker = sys_get_temp_dir() . '/blackops-discovery-side-effect-' . bin2hex(random_bytes(8));
        putenv('BLACKOPS_DISCOVERY_SIDE_EFFECT_MARKER=' . $marker);

        try {
            $definitions = new OperationSourceDiscovery()->discover(
                [$this->discoveryRoot(), $this->discoveryRoot()],
                $this->metadata(),
            );
        } finally {
            putenv('BLACKOPS_DISCOVERY_SIDE_EFFECT_MARKER');
        }

        self::assertSame(
            [
                ClassmapOperation::class,
                Psr4Operation::class,
                IndirectOperation::class,
                TokenOnlyOperation::class,
            ],
            $definitions,
        );
        self::assertFileDoesNotExist($marker);
    }

    public function testTokenFallbackFindsOperationsWhenComposerMetadataIsEmpty(): void
    {
        $definitions = new OperationSourceDiscovery()->discover(
            [$this->discoveryRoot()],
            new ComposerAutoloadMetadata($this->fixtureBase(), [], []),
        );

        self::assertContains(TokenOnlyOperation::class, $definitions);
        self::assertContains(Psr4Operation::class, $definitions);
    }

    public function testRejectsMissingRoots(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationSourceDiscovery()->discover([], $this->metadata());
    }

    public function testRejectsSourceSymlinkThatEscapesRoot(): void
    {
        $root = sys_get_temp_dir() . '/blackops-discovery-root-' . bin2hex(random_bytes(8));
        $outside = sys_get_temp_dir() . '/blackops-discovery-outside-' . bin2hex(random_bytes(8)) . '.php';
        mkdir($root);
        file_put_contents($outside, '<?php final class OutsideSymlinkFixture {}');
        symlink($outside, $root . '/escape.php');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('symlink escapes configured roots');

        new OperationSourceDiscovery()->discover([$root], new ComposerAutoloadMetadata($root, [], []));
    }

    private function metadata(): ComposerAutoloadMetadata
    {
        return new ComposerAutoloadMetadata(
            $this->fixtureBase(),
            [
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\DiscoveryRoot\\Convention\\' => 'DiscoveryRoot/Convention',
            ],
            [
                ClassmapOperation::class => 'DiscoveryRoot/MismatchedOperations.php',
                'BlackOps\\Tests\\Internal\\Discovery\\Fixture\\Outside\\OutsideOperation' => 'Outside/OutsideOperation.php',
            ],
        );
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
