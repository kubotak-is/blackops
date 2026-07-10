<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Discovery;

use BlackOps\Internal\Discovery\ComposerAutoloadMetadataFile;
use BlackOps\Internal\Discovery\DiscoveryRoots;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ComposerAutoloadMetadataFileTest extends TestCase
{
    public function testLoadsComposerGeneratedPhpArrays(): void
    {
        $directory = $this->temporaryDirectory();
        $psr4 = $directory . '/autoload_psr4.php';
        $classmap = $directory . '/autoload_classmap.php';
        file_put_contents($psr4, '<?php return [];');
        file_put_contents($classmap, '<?php return [];');

        $metadata = new ComposerAutoloadMetadataFile()->load($directory, $psr4, $classmap);

        self::assertSame([], $metadata->candidates(DiscoveryRoots::from([$directory])));
    }

    public function testRejectsPsr4FileReturningNonArray(): void
    {
        $directory = $this->temporaryDirectory();
        $psr4 = $directory . '/autoload_psr4.php';
        $classmap = $directory . '/autoload_classmap.php';
        file_put_contents($psr4, '<?php return "invalid";');
        file_put_contents($classmap, '<?php return [];');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-4 metadata file must return an array');

        new ComposerAutoloadMetadataFile()->load($directory, $psr4, $classmap);
    }

    public function testRejectsClassmapFileReturningNonArray(): void
    {
        $directory = $this->temporaryDirectory();
        $psr4 = $directory . '/autoload_psr4.php';
        $classmap = $directory . '/autoload_classmap.php';
        file_put_contents($psr4, '<?php return [];');
        file_put_contents($classmap, '<?php return null;');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('classmap metadata file must return an array');

        new ComposerAutoloadMetadataFile()->load($directory, $psr4, $classmap);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-composer-autoload-' . bin2hex(random_bytes(8));
        mkdir($directory);

        return $directory;
    }
}
