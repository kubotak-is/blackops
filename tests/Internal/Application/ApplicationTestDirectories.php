<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

trait ApplicationTestDirectories
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $directory,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
                ),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }

            rmdir($directory);
        }
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/blackops-application-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    private function writeConfig(string $directory, string $name, string $body): void
    {
        file_put_contents($directory . '/' . $name . '.php', "<?php\n\n" . $body . "\n");
    }

    private function snapshot(Application $application): ApplicationConfigurationSnapshot
    {
        $property = new ReflectionClass($application)->getProperty('_configuration');
        /** @var ApplicationConfigurationSnapshot $snapshot */
        $snapshot = $property->getValue($application);

        return $snapshot;
    }

    private function identity(object|string $entry): string
    {
        return is_string($entry) ? $entry : $entry::class;
    }
}
