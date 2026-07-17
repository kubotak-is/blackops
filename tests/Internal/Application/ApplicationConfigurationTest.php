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
        $this->writeConfig($config, 'middleware', "return ['http' => ['before']];");
        $this->writeConfig($config, 'unknown', "return ['ignored' => true];");

        $builder = Application::configure($directory)->withConfiguration($config);
        $this->writeConfig($config, 'app', "return ['name' => 'after'];");
        $this->writeConfig($config, 'middleware', "return ['http' => ['after']];");
        $snapshot = $this->snapshot($builder->create());

        self::assertSame('before', $snapshot->configuration()['app']['name']);
        self::assertSame('blackops_test', $snapshot->configuration()['database']['schema']);
        self::assertSame(['before'], $snapshot->configuration()['middleware']['http']);
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

    public function testHttpRejectsInvalidDatabaseConfigurationWithoutCredentialExposure(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        $credential = 'credential-that-must-not-appear';
        mkdir($config);
        $this->writeConfig(
            $config,
            'app',
            sprintf(
                "return ['build' => ['operation_manifest' => '%s', 'http_manifest' => '%s', 'container' => '%s', 'container_class' => 'Container', 'container_namespace' => '']];",
                $directory . '/operations.php',
                $directory . '/http.php',
                $directory . '/container.php',
            ),
        );
        $this->writeConfig(
            $config,
            'database',
            sprintf("return ['connection' => ['password' => '%s'], 'schema' => 'invalid-schema'];", $credential),
        );

        try {
            Application::configure($directory)->withConfiguration()->create()->http();
            self::fail('Expected invalid database configuration.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('database.schema', $exception->getMessage());
            self::assertStringNotContainsString($credential, $exception->getMessage());
        }
    }
}
