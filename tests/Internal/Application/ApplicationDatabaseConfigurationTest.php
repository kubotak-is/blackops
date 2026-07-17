<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationDatabaseConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationDatabaseConfigurationTest extends TestCase
{
    public function testNormalizesCanonicalNamedConfiguration(): void
    {
        $configuration = ApplicationDatabaseConfiguration::fromConfiguration([
            'database' => [
                'default' => ' app ',
                'connections' => [
                    ' app ' => ['driver' => 'pdo_pgsql'],
                    'analytics' => ['driver' => 'pdo_pgsql', 'dbname' => 'analytics'],
                ],
                'framework' => [
                    'connection' => 'analytics',
                    'schema' => 'blackops_runtime',
                ],
            ],
        ]);

        self::assertSame('app', $configuration->default);
        self::assertSame(
            [
                'app' => ['driver' => 'pdo_pgsql'],
                'analytics' => ['driver' => 'pdo_pgsql', 'dbname' => 'analytics'],
            ],
            $configuration->connections,
        );
        self::assertSame('analytics', $configuration->frameworkConnection);
        self::assertSame('blackops_runtime', $configuration->schema);
    }

    public function testNormalizesLegacyConfigurationToOneDefaultFrameworkConnection(): void
    {
        $parameters = ['driver' => 'pdo_pgsql', 'host' => 'postgres'];
        $configuration = ApplicationDatabaseConfiguration::fromConfiguration(['database' => [
            'connection' => $parameters,
            'schema' => 'blackops_legacy',
        ]]);

        self::assertSame('default', $configuration->default);
        self::assertSame(['default' => $parameters], $configuration->connections);
        self::assertSame('default', $configuration->frameworkConnection);
        self::assertSame('blackops_legacy', $configuration->schema);
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'missing database' => [[]];
        yield 'mixed legacy and canonical' => [[
            'database' => [
                'connection' => [],
                'schema' => 'blackops',
                'default' => 'app',
            ],
        ]];
        yield 'empty default name' => [[
            'database' => [
                'default' => ' ',
                'connections' => ['app' => []],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'empty connection map' => [[
            'database' => [
                'default' => 'app',
                'connections' => [],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'numeric connection name' => [[
            'database' => [
                'default' => '1',
                'connections' => [1 => []],
                'framework' => ['connection' => '1', 'schema' => 'blackops'],
            ],
        ]];
        yield 'duplicate normalized name' => [[
            'database' => [
                'default' => 'app',
                'connections' => ['app' => [], ' app ' => []],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'non-map parameters' => [[
            'database' => [
                'default' => 'app',
                'connections' => ['app' => 'secret'],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'numeric parameter key' => [[
            'database' => [
                'default' => 'app',
                'connections' => ['app' => [0 => 'secret']],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'empty parameter key' => [[
            'database' => [
                'default' => 'app',
                'connections' => ['app' => [' ' => 'secret']],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'unknown default' => [[
            'database' => [
                'default' => 'missing',
                'connections' => ['app' => []],
                'framework' => ['connection' => 'app', 'schema' => 'blackops'],
            ],
        ]];
        yield 'unknown framework connection' => [[
            'database' => [
                'default' => 'app',
                'connections' => ['app' => []],
                'framework' => ['connection' => 'missing', 'schema' => 'blackops'],
            ],
        ]];
        yield 'invalid framework schema' => [[
            'database' => [
                'default' => 'app',
                'connections' => ['app' => []],
                'framework' => ['connection' => 'app', 'schema' => 'invalid-schema'],
            ],
        ]];
    }

    /** @param array<array-key, mixed> $configuration */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfigurationWithoutExposingValues(array $configuration): void
    {
        $credential = 'credential-that-must-not-appear';
        if (isset($configuration['database']) && is_array($configuration['database'])) {
            $configuration['database']['credential_probe'] = $credential;
        }

        try {
            ApplicationDatabaseConfiguration::fromConfiguration($configuration);
            self::fail('Expected invalid database configuration.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString($credential, $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }
}
