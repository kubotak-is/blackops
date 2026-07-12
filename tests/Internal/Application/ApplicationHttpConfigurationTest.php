<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationDatabaseConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationHttpConfigurationTest extends TestCase
{
    /** @return iterable<string, array{array<string, array<array-key, mixed>>, string}> */
    public static function invalidBuildConfigurations(): iterable
    {
        yield 'missing build section' => [['app' => []], 'app.build'];
        yield 'relative operation manifest' => [self::configuration('relative.php'), 'operation_manifest'];
        yield 'empty HTTP manifest' => [self::configuration('/operations.php', ''), 'http_manifest'];
        yield 'invalid container class' => [
            self::configuration('/operations.php', '/http.php', '/container.php', 'Invalid-Class'),
            'container_class',
        ];
        yield 'invalid namespace type' => [
            self::configuration('/operations.php', '/http.php', '/container.php', 'Container', 42),
            'container_namespace',
        ];
    }

    /** @param array<string, array<array-key, mixed>> $configuration */
    #[DataProvider('invalidBuildConfigurations')]
    public function testRejectsInvalidBuildConfiguration(array $configuration, string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($key);

        ApplicationBuildConfiguration::fromConfiguration($configuration);
    }

    public function testAcceptsValidBuildConfiguration(): void
    {
        $configuration = ApplicationBuildConfiguration::fromConfiguration(self::configuration());

        self::assertSame('/operations.php', $configuration->operationManifest);
        self::assertSame('/http.php', $configuration->httpManifest);
        self::assertSame('/container.php', $configuration->container);
        self::assertSame('CompiledContainer', $configuration->containerClass);
        self::assertSame('', $configuration->containerNamespace);
    }

    public function testRejectsInvalidDatabaseConfigurationWithoutExposingConnectionValues(): void
    {
        $credential = 'credential-that-must-not-appear';

        foreach ([
            [],
            ['database' => ['connection' => $credential, 'schema' => 'blackops']],
            ['database' => ['connection' => ['password' => $credential], 'schema' => 'invalid-schema']],
        ] as $configuration) {
            try {
                ApplicationDatabaseConfiguration::fromConfiguration($configuration);
                self::fail('Expected invalid database configuration.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringNotContainsString($credential, $exception->getMessage());
            }
        }
    }

    public function testAcceptsDatabaseConnectionParametersAndSchema(): void
    {
        $configuration = ApplicationDatabaseConfiguration::fromConfiguration([
            'database' => [
                'connection' => ['driver' => 'pdo_pgsql'],
                'schema' => 'blackops_runtime',
            ],
        ]);

        self::assertSame(['driver' => 'pdo_pgsql'], $configuration->connection);
        self::assertSame('blackops_runtime', $configuration->schema);
    }

    /**
     * @return array<string, array<array-key, mixed>>
     */
    private static function configuration(
        string $operationManifest = '/operations.php',
        string $httpManifest = '/http.php',
        string $container = '/container.php',
        string $containerClass = 'CompiledContainer',
        mixed $containerNamespace = '',
    ): array {
        return [
            'app' => [
                'build' => [
                    'operation_manifest' => $operationManifest,
                    'http_manifest' => $httpManifest,
                    'container' => $container,
                    'container_class' => $containerClass,
                    'container_namespace' => $containerNamespace,
                ],
            ],
        ];
    }
}
