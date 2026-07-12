<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use PHPUnit\Framework\TestCase;

final class ApplicationConfigurationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testLoadsRecognizedConfigurationOnceAndIgnoresUnknownFile(): void
    {
        $directory = $this->directory();
        $config = $directory . '/custom-config';
        mkdir($config);
        $this->writeConfig($config, 'app', "return ['name' => 'before'];");
        $this->writeConfig($config, 'database', "return ['schema' => 'blackops_test'];");
        $this->writeConfig($config, 'unknown', "return ['ignored' => true];");

        $builder = Application::configure($directory)->withConfiguration($config);
        $this->writeConfig($config, 'app', "return ['name' => 'after'];");
        $snapshot = $this->snapshot($builder->create());

        self::assertSame('before', $snapshot->configuration()['app']['name']);
        self::assertSame('blackops_test', $snapshot->configuration()['database']['schema']);
        self::assertArrayNotHasKey('unknown', $snapshot->configuration());
    }

    public function testRejectsMissingExplicitConfigurationDirectoryAndNonArrayFile(): void
    {
        $directory = $this->directory();

        try {
            Application::configure($directory)->withConfiguration($directory . '/missing');
            self::fail('Expected missing configuration directory.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('configuration directory', $exception->getMessage());
        }

        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig($config, 'journal', "return 'plain-secret';");

        try {
            Application::configure($directory)->withConfiguration();
            self::fail('Expected invalid configuration file.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('journal.php', $exception->getMessage());
            self::assertStringNotContainsString('plain-secret', $exception->getMessage());
        }
    }
}
