<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\ScheduledBy;
use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationProvider;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\TenantRef;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\Application\ApplicationBuildConfiguration;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationConsoleKernel;
use BlackOps\Internal\Console\ApplicationBuildCompileCommand;
use BlackOps\Internal\Frontend\FrontendContractManifestFile;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetPublisher;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Registry\OperationManifestFile;
use BlackOps\Scheduling\ScheduledActorProvider;
use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;
use BlackOps\StorageProtection\StorageKey;
use BlackOps\StorageProtection\StorageKeyProvider;
use BlackOps\StorageProtection\StoragePurpose;
use BlackOps\Tests\Fixtures\Aop\TransactionalOperation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplicationBuildCompileCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/blackops-application-build-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o755, true);
    }

    public function testGeneratedContainerResolvesAutowiredAuthorizationPolicy(): void
    {
        $operationManifest = $this->path('operation-manifest');
        $httpManifest = $this->path('http-manifest');
        $frontendManifest = $this->path('frontend-manifest');
        $containerPath = $this->path('container');
        $class = 'ApplicationBuildContainer' . bin2hex(random_bytes(8));
        $namespace = __NAMESPACE__ . '\\Generated';
        $configuration = new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 3),
            [
                'app' => [
                    'build' => [
                        'operation_manifest' => $operationManifest,
                        'http_manifest' => $httpManifest,
                        'frontend_manifest' => $frontendManifest,
                        'container' => $containerPath,
                        'container_class' => $class,
                        'container_namespace' => $namespace,
                        'application_build_id' => 'application-build-authorization',
                    ],
                ],
                'database' => [
                    'default' => 'app',
                    'connections' => [
                        'app' => [
                            'driver' => 'pdo_pgsql',
                            'password' => 'build-credential-that-must-not-appear',
                        ],
                    ],
                    'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                ],
            ],
            [ApplicationBuildOperationProvider::class],
            [ApplicationBuildServiceProvider::class],
            [],
        );

        $status = new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([]);
        $metadata = new OperationManifestFile()
            ->load($operationManifest)
            ->findByTypeId('application.build.authorized');
        $transactionalMetadata = new OperationManifestFile()
            ->load($operationManifest)
            ->findByTypeId('application.build.transactional');
        $operationArtifact = new OperationManifestFile()->loadArtifact($operationManifest);
        $httpArtifact = new HttpOperationManifestFile()->loadArtifact($httpManifest);
        $frontendArtifact = new FrontendContractManifestFile()->loadArtifact($frontendManifest);

        self::assertSame(0, $status);
        self::assertSame(FrontendContractManifestFile::SCHEMA_VERSION, $frontendArtifact->schemaVersion);
        self::assertSame('application-build-authorization', $operationArtifact->applicationBuildId);
        self::assertSame($operationArtifact->applicationBuildId, $httpArtifact->applicationBuildId);
        self::assertSame($operationArtifact->applicationBuildId, $frontendArtifact->applicationBuildId);
        self::assertSame(ApplicationBuildAuthorizationPolicy::class, $metadata?->authorizationPolicy);
        self::assertSame('app', $transactionalMetadata?->transactionConnection);
        $runtime = $this->runCompiledContainer($containerPath, $namespace . '\\' . $class, 'application');
        self::assertSame('application-build-aop', $runtime['service']);
        self::assertSame(1, $runtime['calls']);
        self::assertTrue($runtime['framework_proxy']);
        self::assertTrue($runtime['has_database']);
        self::assertTrue($runtime['has_connection']);
        self::assertTrue($runtime['policy']);
        self::assertTrue($runtime['dependency']);
        self::assertTrue($runtime['status']);
        self::assertTrue($runtime['codec']);
        $source = (string) file_get_contents($containerPath);
        self::assertStringContainsString('ProxyProfileArtifactLoader', $source);
        self::assertStringNotContainsString('build-credential-that-must-not-appear', $source);
        self::assertStringNotContainsString("'password'", $source);
    }

    public function testFrameworkProfileDumpsAndInitializesGeneratedProxy(): void
    {
        $configuration = new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 3),
            [
                'app' => ['build' => [
                    'operation_manifest' => $this->path('framework-operation-manifest'),
                    'http_manifest' => $this->path('framework-http-manifest'),
                    'frontend_manifest' => $this->path('framework-frontend-manifest'),
                    'container' => $this->path('framework-container'),
                    'container_class' => 'FrameworkBuildContainer' . bin2hex(random_bytes(8)),
                    'container_namespace' => __NAMESPACE__ . '\\FrameworkGenerated',
                    'application_build_id' => 'application-build-framework',
                ]],
                'database' => [
                    'default' => 'app',
                    'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                    'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                ],
            ],
            ['BlackOps\\Tests\\Internal\\Console\\ApplicationBuildOperationProvider'],
            ['BlackOps\\Tests\\Internal\\Console\\ApplicationBuildServiceProvider'],
            [],
        );
        $tester = new CommandTester(new ApplicationBuildCompileCommand($configuration));
        self::assertSame(0, $tester->execute([]));
        $build = $configuration->configuration()['app']['build'];
        $runtime = $this->runCompiledContainer(
            (string) $build['container'],
            __NAMESPACE__ . '\\FrameworkGenerated\\' . $build['container_class'],
            'application',
        );
        self::assertSame('application-build-aop', $runtime['service']);
        self::assertSame(1, $runtime['calls']);
        self::assertTrue($runtime['framework_proxy']);
        self::assertTrue($runtime['has_database']);
        self::assertTrue($runtime['has_connection']);
        self::assertTrue($runtime['policy']);
        self::assertTrue($runtime['dependency']);
        self::assertTrue($runtime['status']);
        self::assertTrue($runtime['codec']);
        self::assertStringContainsString(
            'ProxyProfileArtifactLoader',
            (string) file_get_contents((string) $build['container']),
        );
    }

    public function testRejectsAuthorizedScheduledOperationWhenActorProviderIsMissing(): void
    {
        $configuration = $this->scheduledConfiguration(
            [ApplicationBuildScheduledOperationProvider::class],
            [],
            'application-build-scheduled-missing-provider',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('scheduled actor provider');

        new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([]);
    }

    public function testRegistersConfiguredScheduledActorProviderInCompiledContainer(): void
    {
        $configuration = $this->scheduledConfiguration(
            [ApplicationBuildScheduledOperationProvider::class],
            [ApplicationBuildScheduledActorServiceProvider::class],
            'application-build-scheduled-provider',
        );
        $build = $configuration->configuration()['app']['build'];
        $containerPath = (string) $build['container'];
        $containerClass = __NAMESPACE__ . '\\GeneratedScheduled\\' . (string) $build['container_class'];

        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($configuration))->execute([]));
        $runtime = $this->runCompiledContainer($containerPath, $containerClass, 'scheduled');
        self::assertTrue($runtime['scheduled_actor']);
    }

    public function testSuccessfulBuildPublishesOneCompleteRootReadByEveryProjectionCommand(): void
    {
        $configuration = $this->scheduledConfiguration(
            [ApplicationBuildScheduledOperationProvider::class],
            [ApplicationBuildScheduledActorServiceProvider::class],
            'application-build-projection',
        );
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        self::assertFileDoesNotExist($build->projectionBuildSetRoot . '/projection-build-set.json');
        $checkpoints = [];
        $publisher = new ProjectionBuildSetPublisher(
            new ProjectionBuildSetLocator($build->projectionBuildSetRoot),
            checkpoint: static function (string $event) use (&$checkpoints): void {
                $checkpoints[] = $event;
            },
        );

        $tester = new CommandTester(new ApplicationBuildCompileCommand($configuration, $publisher));
        self::assertSame(0, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame("Build artifacts written.\n", $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame(
            [
                'after-exclusive-lock',
                'before-first-temporary',
                'projection-temporary-written:command',
                'projection-generation-renamed:command',
                'projection-temporary-written:route',
                'projection-generation-renamed:route',
                'projection-temporary-written:schedule',
                'projection-generation-renamed:schedule',
                'projection-directory-synced',
                'root-temporary-written',
                'root-committed',
                'before-gc',
            ],
            $checkpoints,
        );
        self::assertSame(
            [
                'projection-generation-renamed:command',
                'projection-generation-renamed:route',
                'projection-generation-renamed:schedule',
            ],
            array_values(array_filter($checkpoints, static fn(string $event): bool => str_starts_with(
                $event,
                'projection-generation-renamed:',
            ))),
        );

        $locator = new ProjectionBuildSetLocator($build->projectionBuildSetRoot);
        $read = new ProjectionBuildSetReader($locator)->read('schedule');
        self::assertSame('application-build-projection', $read->applicationBuildId);
        self::assertCount(3, $read->projections);
        self::assertCount(1, $read->records('schedule'));

        $kernel = new ApplicationConsoleKernel($configuration);
        $list = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'list',
            '--format' => 'json',
        ]), $list));
        $listDocument = json_decode($list->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertContains('schedule:list', array_column($listDocument['commands'], 'name'));

        $route = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'route:ls',
            '--json' => true,
        ]), $route));
        $routeDocument = json_decode($route->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($routeDocument['routes']);

        $schedule = new BufferedOutput();
        self::assertSame(0, $kernel->run(new ArrayInput([
            'command' => 'schedule:list',
            '--json' => true,
        ]), $schedule));
        $scheduleDocument = json_decode($schedule->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('application.build.scheduled', $scheduleDocument['schedules'][0]['operationType']);
    }

    public function testNonProjectionArtifactWriteFailureInvokesPublisherZeroTimes(): void
    {
        $configuration = $this->scheduledConfiguration(
            [],
            [],
            'application-build-artifact-failure',
            '/dev/null/blackops-operation-manifest.php',
        );
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $events = [];
        $publisher = new ProjectionBuildSetPublisher(
            new ProjectionBuildSetLocator($build->projectionBuildSetRoot),
            checkpoint: static function (string $event) use (&$events): void {
                $events[] = $event;
            },
        );
        $tester = new CommandTester(new ApplicationBuildCompileCommand($configuration, $publisher));

        try {
            $tester->execute([], ['capture_stderr_separately' => true]);
            self::fail('Expected non-projection artifact write failure.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Operation manifest directory does not exist.', $exception->getMessage());
        }

        self::assertSame([], $events);
        self::assertSame('', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertFileDoesNotExist(new ProjectionBuildSetLocator($build->projectionBuildSetRoot)->rootPath());
    }

    public function testPreCommitPublisherFailureRetainsExistingRootBytesAndBuildIdWithoutSuccess(): void
    {
        $previous = $this->scheduledConfiguration([], [], 'application-build-retained-old');
        $previousBuild = ApplicationBuildConfiguration::fromConfiguration($previous->configuration());
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($previous))->execute([]));
        $locator = new ProjectionBuildSetLocator($previousBuild->projectionBuildSetRoot);
        $oldRootBytes = (string) file_get_contents($locator->rootPath());

        $current = $this->scheduledConfiguration([], [], 'application-build-retained-new');
        $publisher = new ProjectionBuildSetPublisher($locator, checkpoint: static function (string $event): void {
            if ($event === 'before-first-temporary') {
                throw new \RuntimeException('retained-root precommit failure');
            }
        });
        $tester = new CommandTester(new ApplicationBuildCompileCommand($current, $publisher));

        try {
            $tester->execute([], ['capture_stderr_separately' => true]);
            self::fail('Expected publisher failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('retained-root precommit failure', $exception->getMessage());
        }

        self::assertSame('', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame($oldRootBytes, file_get_contents($locator->rootPath()));
        self::assertSame(
            'application-build-retained-old',
            new ProjectionBuildSetReader($locator)->read()->applicationBuildId,
        );
    }

    public function testSameBuildIdPreCommitFailureExplicitlyRetainsCompleteRoot(): void
    {
        $buildId = 'application-build-retained-same';
        $previous = $this->scheduledConfiguration([], [], $buildId);
        $previousBuild = ApplicationBuildConfiguration::fromConfiguration($previous->configuration());
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($previous))->execute([]));
        $locator = new ProjectionBuildSetLocator($previousBuild->projectionBuildSetRoot);
        $oldRootBytes = (string) file_get_contents($locator->rootPath());

        $current = $this->scheduledConfiguration([], [], $buildId);
        $publisher = new ProjectionBuildSetPublisher($locator, checkpoint: static function (string $event): void {
            if ($event === 'before-first-temporary') {
                throw new \RuntimeException('same-build-id precommit failure');
            }
        });
        $tester = new CommandTester(new ApplicationBuildCompileCommand($current, $publisher));

        try {
            $tester->execute([], ['capture_stderr_separately' => true]);
            self::fail('Expected same-build-id publisher failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('same-build-id precommit failure', $exception->getMessage());
        }

        self::assertSame('', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame($oldRootBytes, file_get_contents($locator->rootPath()));
        $read = new ProjectionBuildSetReader($locator)->read();
        self::assertSame($buildId, $read->applicationBuildId);
        self::assertCount(3, $read->projections);
    }

    public function testSecondSuccessfulBuildReplacesRootWithOneCompleteNewGeneration(): void
    {
        $previous = $this->scheduledConfiguration([], [], 'application-build-generation-old');
        $previousBuild = ApplicationBuildConfiguration::fromConfiguration($previous->configuration());
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($previous))->execute([]));
        $locator = new ProjectionBuildSetLocator($previousBuild->projectionBuildSetRoot);
        $oldRootBytes = (string) file_get_contents($locator->rootPath());

        $current = $this->scheduledConfiguration([], [], 'application-build-generation-new');
        $checkpoints = [];
        $publisher = new ProjectionBuildSetPublisher($locator, checkpoint: static function (string $event) use (
            &$checkpoints,
        ): void {
            $checkpoints[] = $event;
        });
        $tester = new CommandTester(new ApplicationBuildCompileCommand($current, $publisher));

        self::assertSame(0, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame("Build artifacts written.\n", $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertNotSame($oldRootBytes, file_get_contents($locator->rootPath()));
        self::assertSame(
            [
                'projection-generation-renamed:command',
                'projection-generation-renamed:route',
                'projection-generation-renamed:schedule',
            ],
            array_values(array_filter($checkpoints, static fn(string $event): bool => str_starts_with(
                $event,
                'projection-generation-renamed:',
            ))),
        );

        $read = new ProjectionBuildSetReader($locator)->read();
        self::assertSame('application-build-generation-new', $read->applicationBuildId);
        self::assertCount(3, $read->projections);
        foreach ($read->projections as $projection) {
            self::assertSame('application-build-generation-new', $projection->applicationBuildId);
        }
        $root = json_decode((string) file_get_contents($locator->rootPath()), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($root);
        foreach (['command', 'route', 'schedule'] as $key) {
            self::assertSame(
                $root['projections'][$key]['path'],
                $read->projections[$key]->path,
                $key . ' generation is bound to the current root',
            );
        }
    }

    public function testPreCommitPublisherFailureLeavesAbsentRootAndDoesNotPrintSuccess(): void
    {
        $configuration = $this->scheduledConfiguration([], [], 'application-build-precommit-failure');
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $locator = new ProjectionBuildSetLocator($build->projectionBuildSetRoot);
        $publisher = new ProjectionBuildSetPublisher($locator, checkpoint: static function (string $event): void {
            if ($event === 'before-first-temporary') {
                throw new \RuntimeException('precommit failure detail');
            }
        });
        $tester = new CommandTester(new ApplicationBuildCompileCommand($configuration, $publisher));

        try {
            $tester->execute([], ['capture_stderr_separately' => true]);
            self::fail('Expected publisher failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('precommit failure detail', $exception->getMessage());
        }
        self::assertSame('', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertFileDoesNotExist($locator->rootPath());
        self::assertFileExists((string) $build->container);
        self::assertFileExists((string) $build->operationManifest);
    }

    public function testPostCommitCleanupFailureReportsCommittedBuildAndFailureExit(): void
    {
        $configuration = $this->scheduledConfiguration([], [], 'application-build-cleanup-failure');
        $build = ApplicationBuildConfiguration::fromConfiguration($configuration->configuration());
        $publisher = new ProjectionBuildSetPublisher(
            new ProjectionBuildSetLocator($build->projectionBuildSetRoot),
            checkpoint: static function (string $event): void {
                if ($event === 'before-gc') {
                    throw new \RuntimeException('cleanup detail');
                }
            },
        );
        $tester = new CommandTester(new ApplicationBuildCompileCommand($configuration, $publisher));

        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertSame("Build artifacts written.\n", $tester->getDisplay());
        self::assertSame("Projection build-set cleanup is incomplete.\n", $tester->getErrorOutput());
        self::assertSame(
            'application-build-cleanup-failure',
            new ProjectionBuildSetReader(new ProjectionBuildSetLocator($build->projectionBuildSetRoot))->read()->applicationBuildId,
        );
    }

    public function testPreviousCompleteBuildRollsBackAndCrossBuildUnitIsRejected(): void
    {
        $previous = $this->scheduledConfiguration(
            [ApplicationBuildScheduledOperationProvider::class],
            [ApplicationBuildScheduledActorServiceProvider::class],
            'rollback-previous-' . bin2hex(random_bytes(3)),
        );
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($previous))->execute([]));
        $current = $this->scheduledConfiguration(
            [ApplicationBuildScheduledOperationProvider::class],
            [ApplicationBuildScheduledActorServiceProvider::class],
            'rollback-current-' . bin2hex(random_bytes(3)),
        );
        self::assertSame(0, new CommandTester(new ApplicationBuildCompileCommand($current))->execute([]));
        $previousBuild = $previous->configuration()['app']['build'];
        $currentBuild = $current->configuration()['app']['build'];
        $previousClass = __NAMESPACE__ . '\\GeneratedScheduled\\' . $previousBuild['container_class'];
        $previousRuntime = $this->runCompiledContainer(
            (string) $previousBuild['container'],
            $previousClass,
            'scheduled',
        );
        self::assertTrue($previousRuntime['scheduled_actor']);
        $mutated = $this->path('rollback-cross-build-container');
        $source = (string) file_get_contents((string) $previousBuild['container']);
        file_put_contents($mutated, str_replace(
            (string) $previousBuild['application_build_id'],
            (string) $currentBuild['application_build_id'],
            $source,
        ));
        $this->runCompiledContainer($mutated, $previousClass, 'scheduled', expectFailure: true);
    }

    /** @param list<class-string<OperationProvider>> $operations @param list<class-string<ServiceProvider>> $services */
    private function scheduledConfiguration(
        array $operations,
        array $services,
        string $buildId,
        ?string $operationManifest = null,
    ): ApplicationConfigurationSnapshot {
        return new ApplicationConfigurationSnapshot(
            dirname(__DIR__, 3),
            [
                'app' => [
                    'build' => [
                        'operation_manifest' => $operationManifest ?? $this->path(
                            'scheduled-operation-manifest-' . bin2hex(random_bytes(4)),
                        ),
                        'http_manifest' => $this->path('scheduled-http-manifest-' . bin2hex(random_bytes(4))),
                        'frontend_manifest' => $this->path('scheduled-frontend-manifest-' . bin2hex(random_bytes(4))),
                        'container' => $this->path('scheduled-container-' . bin2hex(random_bytes(4))),
                        'container_class' => 'ScheduledApplicationBuildContainer' . bin2hex(random_bytes(4)),
                        'container_namespace' => __NAMESPACE__ . '\\GeneratedScheduled',
                        'application_build_id' => $buildId,
                    ],
                ],
                'database' => [
                    'default' => 'app',
                    'connections' => ['app' => ['driver' => 'pdo_pgsql']],
                    'framework' => ['connection' => 'app', 'schema' => 'blackops'],
                ],
            ],
            $operations,
            $services,
            [],
        );
    }

    private function path(string $name): string
    {
        return $this->directory . '/' . $name . '.php';
    }

    /** @return array<string,mixed> */
    private function runCompiledContainer(
        string $path,
        string $class,
        string $scenario,
        bool $expectFailure = false,
    ): array {
        $process = proc_open(
            [
                PHP_BINARY,
                dirname(__DIR__) . '/Aop/FrameworkProxyCompatibility/runtime-runner.php',
                $path,
                $class,
                $scenario,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $started = microtime(true);
        $stdout = '';
        $stderr = '';
        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            if (!$status['running']) {
                break;
            }
            if ((microtime(true) - $started) > 15.0) {
                proc_terminate($process);
                self::fail('compiled container runner timed out');
            }
            usleep(10_000);
        }
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit === -1 && isset($status['exitcode'])) {
            $exit = (int) $status['exitcode'];
        }
        if ($expectFailure) {
            self::assertNotSame(0, $exit, trim($stderr . "\n" . $stdout));
            return [];
        }
        self::assertSame(0, $exit, trim($stderr . "\n" . $stdout));
        $decoded = json_decode(trim($stdout), true);
        self::assertIsArray($decoded, $stderr);
        return $decoded;
    }
}

final readonly class ApplicationBuildOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ApplicationBuildOperation::class, TransactionalOperation::class];
    }
}

final readonly class ApplicationBuildServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ApplicationBuildPolicyDependency::class);
        $services->autowire(ApplicationBuildTransactionalService::class);
        $services->autowire(OperationStatusAuthorizer::class, ApplicationBuildStatusAuthorizer::class);
        $services->autowire(StorageKeyProvider::class, ApplicationBuildStorageKeyProvider::class);
    }
}

final readonly class ApplicationBuildStorageKeyProvider implements StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey('application:v1', str_repeat('a', 32));
    }

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey
    {
        return new StorageKey($keyId, str_repeat('a', 32));
    }
}

final readonly class ApplicationBuildPolicyDependency {}

#[Transactional]
class ApplicationBuildTransactionalService
{
    public int $calls = 0;

    public function execute(string $value): string
    {
        $this->calls++;

        return $value;
    }
}

final readonly class ApplicationBuildStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        return OperationStatusAuthorizationDecision::allow();
    }
}

final readonly class ApplicationBuildValue implements OperationValue {}

final readonly class ApplicationBuildOutcome implements Outcome {}

#[OperationType('application.build.authorized')]
#[Authorize(ApplicationBuildAuthorizationPolicy::class)]
final readonly class ApplicationBuildOperation implements Operation
{
    public function handle(ApplicationBuildValue $value): ApplicationBuildOutcome
    {
        return new ApplicationBuildOutcome();
    }
}

final readonly class ApplicationBuildAuthorizationPolicy implements AuthorizationPolicy
{
    public function __construct(
        public ApplicationBuildPolicyDependency $dependency,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}

final readonly class ApplicationBuildScheduledOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ApplicationBuildScheduledOperation::class];
    }
}

final readonly class ApplicationBuildScheduledActorServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ScheduledActorProvider::class, ApplicationBuildScheduledActorProvider::class);
    }
}

final readonly class ApplicationBuildScheduledActorProvider implements ScheduledActorProvider
{
    public function actor(ScheduleContext $context): ?ActorRef
    {
        return null;
    }
}

final readonly class ApplicationBuildScheduledValue implements OperationValue {}

final readonly class ApplicationBuildScheduledOutcome implements Outcome {}

#[OperationType('application.build.scheduled')]
#[ScheduledBy(name: 'application.build.scheduled', cron: '* * * * *')]
#[Authorize(ApplicationBuildScheduledAuthorizationPolicy::class)]
final readonly class ApplicationBuildScheduledOperation implements Operation
{
    public function handle(ApplicationBuildScheduledValue $value): ApplicationBuildScheduledOutcome
    {
        return new ApplicationBuildScheduledOutcome();
    }
}

final readonly class ApplicationBuildScheduledAuthorizationPolicy implements AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}
