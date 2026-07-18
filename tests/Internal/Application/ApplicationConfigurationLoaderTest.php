<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationConfigurationLoader;
use PHPUnit\Framework\TestCase;

final class ApplicationConfigurationLoaderTest extends TestCase
{
    public function testLoadsDiagnosticsConfigurationIntoTheAcceptedSnapshotInput(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-diagnostics-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/diagnostics.php', '<?php return ["viewer" => ["enabled" => true]];');

        try {
            self::assertSame(
                ['viewer' => ['enabled' => true]],
                new ApplicationConfigurationLoader()->load($directory)['diagnostics'],
            );
        } finally {
            unlink($directory . '/diagnostics.php');
            rmdir($directory);
        }
    }
}
