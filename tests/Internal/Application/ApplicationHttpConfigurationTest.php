<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationBuildId;
use BlackOps\Internal\Application\ApplicationDatabaseConfiguration;
use BlackOps\Internal\Application\ApplicationRetentionConfiguration;
use BlackOps\Internal\Application\ApplicationWorkerConfiguration;
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

    public function testValidatesApplicationBuildId(): void
    {
        $configuration = self::configuration();
        $configuration['app']['build']['application_build_id'] = 'release-1';

        self::assertSame('release-1', ApplicationBuildId::fromConfiguration($configuration));

        $this->expectException(InvalidArgumentException::class);
        ApplicationBuildId::fromConfiguration(self::configuration());
    }

    public function testValidatesWorkerDefaultsAndTiming(): void
    {
        $worker = ApplicationWorkerConfiguration::fromConfiguration([
            'execution' => ['worker' => ['id' => 'worker-1']],
        ]);
        self::assertSame(60, $worker->leaseSeconds);
        self::assertSame(10, $worker->heartbeatSeconds);
        self::assertSame(20, $worker->graceSeconds);
        self::assertTrue($worker->continueAfterHandlerFailure);

        $this->expectException(InvalidArgumentException::class);
        ApplicationWorkerConfiguration::fromConfiguration([
            'execution' => ['worker' => ['id' => 'worker-1', 'lease_seconds' => 10, 'heartbeat_seconds' => 10]],
        ]);
    }

    public function testValidatesRetentionPolicyConfiguration(): void
    {
        $retention = ApplicationRetentionConfiguration::fromConfiguration([
            'retention' => [
                'transport_payload_days' => 30,
                'journal_days' => 90,
                'outcome_days' => 30,
                'dead_letter_days' => 90,
                'policy_ref' => 'default-v1',
                'actor' => 'maintenance',
            ],
        ]);
        self::assertSame('default-v1', $retention->policyRef->toString());
        self::assertSame('maintenance', $retention->actor->toString());

        $this->expectException(InvalidArgumentException::class);
        ApplicationRetentionConfiguration::fromConfiguration([
            'retention' => [
                'transport_payload_days' => 0,
                'journal_days' => 90,
                'outcome_days' => 30,
                'dead_letter_days' => 90,
                'policy_ref' => 'default-v1',
                'actor' => 'maintenance',
            ],
        ]);
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
