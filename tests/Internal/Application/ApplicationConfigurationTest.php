<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Application\Application;
use BlackOps\Application\ApplicationBootstrapException;
use PHPUnit\Framework\TestCase;

final class ApplicationConfigurationTest extends TestCase
{
    use ApplicationTestDirectories;

    public function testLoadsRecognizedConfigurationAtCreateAndIgnoresUnknownFile(): void
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

        self::assertSame('after', $snapshot->configuration()['app']['name']);
        self::assertSame('blackops_test', $snapshot->configuration()['database']['schema']);
        self::assertSame(['after'], $snapshot->configuration()['middleware']['http']);
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
            Application::configure($directory)->withConfiguration()->create();
            self::fail('Expected invalid configuration file.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('journal.php', $exception->getMessage());
            self::assertStringNotContainsString('plain-secret', $exception->getMessage());
        }
    }

    public function testUsesFinalEnvironmentRegardlessOfBuilderCallOrderAndOneInstancePerCreate(): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig(
            $config,
            'app',
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["value" => $env->string("VALUE"), "environment_id" => spl_object_id($env)];',
        );
        $this->writeConfig(
            $config,
            'database',
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["environment_id" => spl_object_id($env)];',
        );

        $configurationFirst = Application::configure($directory)
            ->withConfiguration()
            ->withEnvironment(['VALUE' => 'final']);
        $environmentFirst = Application::configure($directory)
            ->withEnvironment(['VALUE' => 'final'])
            ->withConfiguration();

        $first = $this->snapshot($configurationFirst->create())->configuration();
        $second = $this->snapshot($configurationFirst->create())->configuration();
        $oppositeOrder = $this->snapshot($environmentFirst->create())->configuration();

        self::assertSame('final', $first['app']['value']);
        self::assertSame('final', $oppositeOrder['app']['value']);
        self::assertSame($first['app']['environment_id'], $first['database']['environment_id']);
        self::assertSame($second['app']['environment_id'], $second['database']['environment_id']);
        self::assertNotSame($first['app']['environment_id'], $second['app']['environment_id']);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidConfigurationClosures(): iterable
    {
        yield 'no parameter' => ['return static fn (): array => [];'];
        yield 'untyped parameter' => ['return static fn ($env): array => [];'];
        yield 'non-static closure' => [
            'use BlackOps\\Application\\Environment; return fn (Environment $env): array => [];',
        ];
        yield 'wrong parameter' => ['return static fn (string $env): array => [];'];
        yield 'optional parameter' => [
            'use BlackOps\\Application\\Environment; return static fn (Environment $env = new Environment([])): array => [];',
        ];
        yield 'missing return type' => [
            'use BlackOps\\Application\\Environment; return static fn (Environment $env) => [];',
        ];
        yield 'wrong return type' => [
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): string => "plain-secret";',
        ];
        yield 'non-closure callable' => ['return "trim";'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidConfigurationClosures')]
    public function testRejectsInvalidConfigurationClosureShapesAtCreate(string $body): void
    {
        $directory = $this->directory();
        $config = $directory . '/config';
        mkdir($config);
        $this->writeConfig($config, 'app', $body);

        try {
            Application::configure($directory)->withConfiguration()->create();
            self::fail('Expected invalid configuration closure.');
        } catch (ApplicationBootstrapException $exception) {
            self::assertStringContainsString('app.php', $exception->getMessage());
            self::assertStringNotContainsString('plain-secret', $exception->getMessage());
        }
    }

    public function testWrapsClosureAndEnvironmentAccessorFailureWithoutRawDetail(): void
    {
        $invalidValue = 'not-an-integer';

        foreach ([
            'use BlackOps\\Application\\Environment; return static fn (Environment $env): array => ["value" => $env->int("INVALID_INTEGER")];',
            'use BlackOps\\Application\\Environment; return static function (Environment $env): array { throw new RuntimeException("private-detail"); };',
        ] as $body) {
            $directory = $this->directory();
            $config = $directory . '/config';
            mkdir($config);
            $this->writeConfig($config, 'app', $body);

            try {
                Application::configure($directory)
                    ->withEnvironment(['INVALID_INTEGER' => $invalidValue])
                    ->withConfiguration()
                    ->create();
                self::fail('Expected configuration evaluation failure.');
            } catch (ApplicationBootstrapException $exception) {
                self::assertStringContainsString('app.php', $exception->getMessage());
                self::assertStringNotContainsString($invalidValue, $exception->getMessage());
                self::assertStringNotContainsString($invalidValue, $exception->getPrevious()?->getMessage() ?? '');
                self::assertStringNotContainsString('private-detail', $exception->getMessage());
                self::assertStringNotContainsString('private-detail', $exception->getPrevious()?->getMessage() ?? '');
            }
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
                "return ['build' => ['operation_manifest' => '%s', 'http_manifest' => '%s', 'frontend_manifest' => '%s', 'container' => '%s', 'container_class' => 'Container', 'container_namespace' => '']];",
                $directory . '/operations.php',
                $directory . '/http.php',
                $directory . '/frontend.php',
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
