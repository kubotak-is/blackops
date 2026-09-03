<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Application;

use BlackOps\Audit\AuditOpaqueIdKeyProvider;
use BlackOps\Audit\AuditProtectionKeyProvider;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Internal\Application\ApplicationAuditRuntime;
use BlackOps\Internal\Application\ApplicationAuditRuntimeComposer;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationOperationInvocationLifecycle;
use BlackOps\Internal\Application\ApplicationOperationRuntimeComposition;
use BlackOps\Internal\Audit\Catalog\ImmutableAuditEventCatalogLookup;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditProtectionPurpose;
use BlackOps\Internal\Authorization\AuthorizationEvaluator;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\StorageProtection\BopdEnvelopeCodec;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Internal\Transaction\OperationTransactionCoordinator;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\JournalRecord;
use BlackOps\StorageProtection\StorageKey;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionNamedType;

final class ApplicationAuditRuntimeComposerTest extends TestCase
{
    public function testComposeHasTheExactThreeInputSignatureAndOnePublicCallable(): void
    {
        $reflection = new \ReflectionClass(ApplicationAuditRuntimeComposer::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame(
            ['compose'],
            array_map(
                static fn(ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            ),
        );
        $method = $reflection->getMethod('compose');
        self::assertSame(
            ApplicationAuditRuntime::class,
            $method->getReturnType() instanceof ReflectionNamedType ? $method->getReturnType()->getName() : null,
        );
        self::assertSame(
            [
                ApplicationConfigurationSnapshot::class,
                ApplicationOperationRuntimeComposition::class,
                ImmutableAuditEventCatalogLookup::class,
            ],
            array_map(static fn(\ReflectionParameter $parameter): ?string => $parameter->getType()
                instanceof ReflectionNamedType
                    ? $parameter->getType()->getName()
                    : null, $method->getParameters()),
        );
        self::assertSame(
            [1, 1, 1],
            array_map(
                static fn(\ReflectionParameter $parameter): int => count($parameter->getAttributes(\SensitiveParameter::class)),
                $method->getParameters(),
            ),
        );
    }

    public function testConfigurationFailurePrecedesOperationGraphAccess(): void
    {
        $configuration = new ApplicationConfigurationSnapshot('', ['audit' => null], [], [], []);
        $operations = new \ReflectionClass(ApplicationOperationRuntimeComposition::class)->newInstanceWithoutConstructor();
        $catalogs = new \ReflectionClass(ImmutableAuditEventCatalogLookup::class)->newInstanceWithoutConstructor();

        try {
            new ApplicationAuditRuntimeComposer()->compose($configuration, $operations, $catalogs);
            self::fail('invalid configuration was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertSame('Audit delivery failed.', $exception->getMessage());
        }
    }

    public function testComposeBuildsOneGraphFromCachedFrameworkConnectionAndResolvesProvidersOnce(): void
    {
        $opaqueMaterial = str_pad('opaque-runtime-canary-9f3b7a1c', 32, 'x');
        self::assertSame(32, strlen($opaqueMaterial));
        $opaqueKey = new StorageKey('opaque-compose', $opaqueMaterial);
        $opaqueProvider = new class($opaqueKey) implements AuditOpaqueIdKeyProvider {
            public int $calls = 0;

            public function __construct(
                private StorageKey $key,
            ) {}

            public function key(): StorageKey
            {
                $this->calls++;

                return $this->key;
            }
        };
        $protectionProvider = new class implements AuditProtectionKeyProvider {
            /** @var list<list<string>> */
            public array $calls = [];

            public function activeRecordKey(string $partitionId): StorageKey
            {
                $this->calls[] = ['activeRecordKey', $partitionId];

                return new StorageKey('record-compose', str_repeat('r', 32));
            }

            public function recordKey(string $keyId, string $partitionId): StorageKey
            {
                $this->calls[] = ['recordKey', $keyId, $partitionId];

                return new StorageKey($keyId, str_repeat('r', 32));
            }

            public function activeOutboxKey(string $partitionId): StorageKey
            {
                $this->calls[] = ['activeOutboxKey', $partitionId];

                return new StorageKey('outbox-compose', str_repeat('b', 32));
            }

            public function outboxKey(string $keyId, string $partitionId): StorageKey
            {
                $this->calls[] = ['outboxKey', $keyId, $partitionId];

                return new StorageKey($keyId, str_repeat('b', 32));
            }
        };
        $container = new C3ComposerContainer($opaqueProvider, $protectionProvider);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
            'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
            'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
            'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
            'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
        ]);
        $factoryCalls = 0;
        $databases = new DoctrineDatabaseManager(
            'app',
            ['app' => ['driver' => 'pdo_pgsql']],
            static function (array $parameters) use (&$factoryCalls, $connection): Connection {
                $factoryCalls++;

                return $connection;
            },
        );
        self::assertSame($connection, $databases->connection('app'));
        $operations = $this->composition($container, $databases, $connection);
        $configuration = new ApplicationConfigurationSnapshot(
            '',
            [
                'database' => [
                    'default' => 'app',
                    'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                    'framework' => ['connection' => 'app', 'schema' => 'public'],
                ],
                'audit' => ['outbox' => []],
            ],
            [],
            [],
            [],
        );

        try {
            $runtime = new ApplicationAuditRuntimeComposer()->compose(
                $configuration,
                $operations,
                new ImmutableAuditEventCatalogLookup([]),
            );
            self::assertInstanceOf(ApplicationAuditRuntime::class, $runtime);
            self::assertSame($connection, $this->privateValue($runtime->records(), 'connection'));
            self::assertSame($connection, $this->privateValue($runtime->outbox(), 'connection'));
            self::assertSame($operations->transactions, $this->privateValue($runtime->mutations(), 'transactions'));
            self::assertSame($runtime->outboxConfiguration(), $this->privateValue($runtime->outbox(), 'configuration'));
            self::assertSame($runtime->records(), $this->privateValue($runtime->delivery(), 'records'));
            self::assertSame($runtime->outbox(), $this->privateValue($runtime->delivery(), 'outbox'));
            self::assertSame($runtime->delivery(), $this->privateValue($runtime->mutations(), 'delivery'));
            self::assertSame($runtime->delivery(), $this->privateValue($runtime->protectedReads(), 'delivery'));
            self::assertSame(
                $this->privateValue($runtime->records(), 'protection'),
                $this->privateValue($runtime->outbox(), 'protection'),
            );
            self::assertSame(
                $this->privateValue($runtime->records(), 'uuids'),
                $this->privateValue($runtime->outbox(), 'uuids'),
            );
            self::assertSame(1, $opaqueProvider->calls);
            self::assertSame([], $protectionProvider->calls);
            self::assertSame(1, $container->calls(AuditOpaqueIdKeyProvider::class));
            self::assertSame(1, $container->calls(AuditProtectionKeyProvider::class));
            self::assertSame(1, $factoryCalls);

            $opaqueIds = $runtime->opaqueIds();
            $representations = [
                var_export($opaqueIds, true),
                print_r($opaqueIds, true),
                (string) json_encode($opaqueIds, JSON_THROW_ON_ERROR),
                var_export($opaqueIds->__debugInfo(), true),
                var_export($opaqueIds->__serialize(), true),
            ];
            try {
                $representations[] = serialize($opaqueIds);
            } catch (\Throwable $serializationFailure) {
                $representations[] = $serializationFailure->getMessage();
                $representations[] = $serializationFailure->getTraceAsString();
                self::assertNull($serializationFailure->getPrevious());
            }
            try {
                $opaqueIds->derive(AuditOpaqueIdPurpose::Resource, "invalid\xFF");
                self::fail('invalid opaque context was accepted');
            } catch (AuditValidationException $failure) {
                $representations[] = $failure->getMessage();
                $representations[] = $failure->getTraceAsString();
                $representations[] = (string) $failure;
                $representations[] = var_export($failure, true);
                $representations[] = print_r($failure, true);
                $representations[] = (string) json_encode($failure, JSON_THROW_ON_ERROR);
                self::assertNull($failure->getPrevious());
            }
            foreach ($representations as $representation) {
                self::assertStringNotContainsString($opaqueMaterial, $representation);
            }
        } finally {
            $connection->close();
        }
    }

    public function testMissingWrongThrowingAndMalformedOpaqueProvidersContractKeyUnavailable(): void
    {
        foreach (['missing', 'wrong', 'throwing', 'malformed'] as $case) {
            $connection = DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
                'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
                'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
                'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
                'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
            ]);
            $container = new C3ComposerContainer();
            if ($case === 'wrong') {
                $container->set(AuditOpaqueIdKeyProvider::class, new \stdClass());
            } elseif ($case === 'throwing') {
                $container->set(AuditOpaqueIdKeyProvider::class, new class implements AuditOpaqueIdKeyProvider {
                    public function key(): StorageKey
                    {
                        throw new \RuntimeException('opaque-provider-canary');
                    }
                });
            } elseif ($case === 'malformed') {
                $container->set(AuditOpaqueIdKeyProvider::class, new class implements AuditOpaqueIdKeyProvider {
                    public function key(): StorageKey
                    {
                        return new StorageKey('malformed-key', str_repeat('m', 31));
                    }
                });
            }
            $container->set(AuditProtectionKeyProvider::class, $this->protectionProvider());
            $databases = new DoctrineDatabaseManager(
                'app',
                ['app' => ['driver' => 'pdo_pgsql']],
                static fn(array $parameters): Connection => $connection,
            );
            $operations = $this->composition($container, $databases, $connection);
            $configuration = $this->configuration();
            try {
                new ApplicationAuditRuntimeComposer()->compose(
                    $configuration,
                    $operations,
                    new ImmutableAuditEventCatalogLookup([]),
                );
                self::fail($case . ' opaque provider was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::KeyUnavailable, $exception->failure());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('canary', $exception->getTraceAsString());
            } finally {
                $connection->close();
            }
        }
    }

    public function testMissingAndWrongProtectionProvidersFailCompositionWhileAdapterFailuresContractUnavailable(): void
    {
        foreach (['missing', 'wrong', 'throwing', 'malformed'] as $case) {
            $connection = DriverManager::getConnection([
                'driver' => 'pdo_pgsql',
                'host' => (string) (getenv('POSTGRES_HOST') ?: 'postgres'),
                'port' => (int) (getenv('POSTGRES_PORT') ?: 5432),
                'dbname' => (string) (getenv('POSTGRES_DB') ?: 'blackops'),
                'user' => (string) (getenv('POSTGRES_USER') ?: 'blackops'),
                'password' => (string) (getenv('POSTGRES_PASSWORD') ?: 'blackops'),
            ]);
            $container = new C3ComposerContainer();
            $container->set(AuditOpaqueIdKeyProvider::class, new class implements AuditOpaqueIdKeyProvider {
                public function key(): StorageKey
                {
                    return new StorageKey('opaque-valid', str_repeat('o', 32));
                }
            });
            $provider = null;
            if ($case === 'wrong') {
                $container->set(AuditProtectionKeyProvider::class, new \stdClass());
            } elseif ($case === 'throwing') {
                $provider = new class implements AuditProtectionKeyProvider {
                    public int $calls = 0;

                    public function activeRecordKey(string $partitionId): StorageKey
                    {
                        $this->calls++;
                        throw new \RuntimeException('protection-provider-canary');
                    }

                    public function recordKey(string $keyId, string $partitionId): StorageKey
                    {
                        $this->calls++;
                        throw new \RuntimeException('protection-provider-canary');
                    }

                    public function activeOutboxKey(string $partitionId): StorageKey
                    {
                        $this->calls++;
                        throw new \RuntimeException('protection-provider-canary');
                    }

                    public function outboxKey(string $keyId, string $partitionId): StorageKey
                    {
                        $this->calls++;
                        throw new \RuntimeException('protection-provider-canary');
                    }
                };
                $container->set(AuditProtectionKeyProvider::class, $provider);
            } elseif ($case === 'malformed') {
                $provider = new class implements AuditProtectionKeyProvider {
                    public int $calls = 0;

                    public function activeRecordKey(string $partitionId): StorageKey
                    {
                        $this->calls++;
                        return new StorageKey('malformed', str_repeat('m', 31));
                    }

                    public function recordKey(string $keyId, string $partitionId): StorageKey
                    {
                        $this->calls++;
                        return new StorageKey('malformed', str_repeat('m', 31));
                    }

                    public function activeOutboxKey(string $partitionId): StorageKey
                    {
                        $this->calls++;
                        return new StorageKey('malformed', str_repeat('m', 31));
                    }

                    public function outboxKey(string $keyId, string $partitionId): StorageKey
                    {
                        $this->calls++;
                        return new StorageKey('malformed', str_repeat('m', 31));
                    }
                };
                $container->set(AuditProtectionKeyProvider::class, $provider);
            }
            $databases = new DoctrineDatabaseManager(
                'app',
                ['app' => ['driver' => 'pdo_pgsql']],
                static fn(array $parameters): Connection => $connection,
            );
            $operations = $this->composition($container, $databases, $connection);
            try {
                $runtime = new ApplicationAuditRuntimeComposer()->compose(
                    $this->configuration(),
                    $operations,
                    new ImmutableAuditEventCatalogLookup([]),
                );
                if ($case === 'missing' || $case === 'wrong') {
                    self::fail($case . ' protection provider was accepted');
                }
                self::assertNotNull($provider);
                $source = $this->privateValue($this->privateValue($runtime->records(), 'protection'), 'keys');
                try {
                    $source->activeKey(null, AuditProtectionPurpose::AuditRecord);
                    self::fail($case . ' protection provider returned malformed key');
                } catch (AuditProtectionException $failure) {
                    self::assertSame('protection_failed', $failure->failure()->value);
                    self::assertSame('Audit protected data is unavailable.', $failure->getMessage());
                    self::assertSame(0, $failure->getCode());
                    self::assertNull($failure->getPrevious());
                    $representations = [
                        $failure->getTraceAsString(),
                        var_export($failure, true),
                        (string) $failure,
                        json_encode($failure, JSON_THROW_ON_ERROR),
                    ];
                    try {
                        $representations[] = serialize($failure);
                    } catch (\Throwable $serializationFailure) {
                        $representations[] = $serializationFailure->getMessage();
                    }
                    foreach ($representations as $representation) {
                        self::assertStringNotContainsString('protection-provider-canary', $representation);
                    }
                    self::assertSame(1, $provider->calls);
                }
            } catch (AuditDeliveryException $exception) {
                self::assertContains($case, ['missing', 'wrong']);
                self::assertSame(AuditDeliveryFailure::KeyUnavailable, $exception->failure());
                self::assertSame('Audit delivery failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('canary', $exception->getTraceAsString());
            } finally {
                $connection->close();
            }
        }
    }

    public function testSourceKeepsTheOpaqueDeriverAndComposerCallsiteBounded(): void
    {
        $sourceRoot = realpath(__DIR__ . '/../../../src');
        self::assertIsString($sourceRoot);
        $composerPath = $sourceRoot . '/Internal/Application/ApplicationAuditRuntimeComposer.php';
        $source = file_get_contents($composerPath);
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, 'new HmacAuditOpaqueIdDeriver('));
        self::assertSame(1, substr_count($source, 'new ApplicationAuditProtectionKeySource('));
        self::assertSame(1, substr_count($source, 'new AuditMutationTransactionCoordinator('));
        self::assertSame(1, substr_count($source, '->key()'));

        $callers = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $candidate = file_get_contents($file->getPathname());
            self::assertIsString($candidate);
            if (str_contains($candidate, 'new ApplicationAuditRuntimeComposer(')) {
                $callers[] = str_replace($sourceRoot . '/', '', $file->getPathname());
            }
        }
        self::assertSame([], $callers);
    }

    private function configuration(): ApplicationConfigurationSnapshot
    {
        return new ApplicationConfigurationSnapshot(
            '',
            [
                'database' => [
                    'default' => 'app',
                    'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                    'framework' => ['connection' => 'app', 'schema' => 'public'],
                ],
                'audit' => ['outbox' => []],
            ],
            [],
            [],
            [],
        );
    }

    private function protectionProvider(): AuditProtectionKeyProvider
    {
        return new class implements AuditProtectionKeyProvider {
            public function activeRecordKey(string $partitionId): StorageKey
            {
                return new StorageKey('record-compose', str_repeat('r', 32));
            }

            public function recordKey(string $keyId, string $partitionId): StorageKey
            {
                return new StorageKey($keyId, str_repeat('r', 32));
            }

            public function activeOutboxKey(string $partitionId): StorageKey
            {
                return new StorageKey('outbox-compose', str_repeat('b', 32));
            }

            public function outboxKey(string $keyId, string $partitionId): StorageKey
            {
                return new StorageKey($keyId, str_repeat('b', 32));
            }
        };
    }

    private function composition(
        ContainerInterface $container,
        DoctrineDatabaseManager $databases,
        Connection $connection,
    ): ApplicationOperationRuntimeComposition {
        $journal = new class implements CanonicalJournalWriter {
            public function append(JournalRecord $record): void {}
        };

        return new ApplicationOperationRuntimeComposition(
            'compose-test',
            $this->uninitialized(OperationRegistry::class),
            $container,
            $databases,
            $connection,
            new \BlackOps\Transport\PostgreSql\PostgreSqlSystemClock(),
            $this->uninitialized(IdentifierFactory::class),
            $journal,
            new ExecutionScopeProvider(),
            $this->uninitialized(ExecutionScopedLogger::class),
            $this->uninitialized(AuthorizationEvaluator::class),
            $this->uninitialized(OperationTransactionCoordinator::class),
            null,
            $this->uninitialized(ApplicationOperationInvocationLifecycle::class),
            $this->uninitialized(TelemetryTracer::class),
            $this->uninitialized(TelemetryMetrics::class),
            $this->uninitialized(BopdEnvelopeCodec::class),
        );
    }

    private function uninitialized(string $class): object
    {
        return new \ReflectionClass($class)->newInstanceWithoutConstructor();
    }

    private function privateValue(object $object, string $name): mixed
    {
        $property = new \ReflectionProperty($object, $name);

        return $property->getValue($object);
    }
}

final class C3ComposerContainer implements ContainerInterface
{
    /** @var array<string,mixed> */
    private array $services = [];

    /** @var array<string,int> */
    private array $resolutionCounts = [];

    public function __construct(
        ?AuditOpaqueIdKeyProvider $opaque = null,
        ?AuditProtectionKeyProvider $protection = null,
    ) {
        if ($opaque !== null) {
            $this->services[AuditOpaqueIdKeyProvider::class] = $opaque;
        }
        if ($protection !== null) {
            $this->services[AuditProtectionKeyProvider::class] = $protection;
        }
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function get(string $id): mixed
    {
        $this->resolutionCounts[$id] = ($this->resolutionCounts[$id] ?? 0) + 1;
        if (!array_key_exists($id, $this->services)) {
            throw new \RuntimeException('service unavailable');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function calls(string $id): int
    {
        return $this->resolutionCounts[$id] ?? 0;
    }
}
