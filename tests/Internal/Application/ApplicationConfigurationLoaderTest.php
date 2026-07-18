<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationConfigurationLoader;
use InvalidArgumentException;
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

    public function testLoadsLoggingConfigurationOnceAsSnapshotInput(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-logging-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/logging.php';
        file_put_contents($path, '<?php return ["backend" => ["channel" => "snapshot"]];');

        try {
            $loaded = new ApplicationConfigurationLoader()->load($directory);
            file_put_contents($path, '<?php return ["backend" => ["channel" => "mutated"]];');

            self::assertSame('snapshot', $loaded['logging']['backend']['channel']);
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function testRejectsNonArrayLoggingFileWithoutReflectingItsValue(): void
    {
        $directory = sys_get_temp_dir() . '/blackops-invalid-logging-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/logging.php';
        file_put_contents($path, '<?php return "credential-secret";');

        try {
            new ApplicationConfigurationLoader()->load($directory);
            self::fail('Invalid logging configuration file was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('credential-secret', $exception->getMessage());
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }
}
