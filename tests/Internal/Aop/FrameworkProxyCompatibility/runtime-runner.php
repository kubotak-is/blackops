<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/vendor/autoload.php';

/**
 * This process is deliberately tiny and bounded so generated proxy side effects
 * stay out of PHPUnit.
 */
if ($argc < 4) {
    fwrite(STDERR, "runner arguments missing\n");
    exit(2);
}

$containerPath = $argv[1];
$containerClass = $argv[2];
$scenario = $argv[3];
if (!is_file($containerPath)) {
    fwrite(STDERR, "compiled container missing\n");
    exit(1);
}

// The application command fixture classes live beside the PHPUnit test.  A
// child process may safely load their declarations because it never executes
// the test methods themselves.
$testsRoot = dirname(__DIR__, 3);
$fixtureFile = $testsRoot . '/Internal/Console/ApplicationBuildCompileCommandTest.php';
if (in_array($scenario, ['application', 'scheduled'], true) && is_file($fixtureFile)) {
    require_once $fixtureFile;
}
if (in_array($scenario, ['compatibility', 'never'], true)) {
    foreach ([
        'CompatibilityDependency.php',
        'CompatibilityOperationValue.php',
        'CompatibilityOperationOutcome.php',
        'CompatibilityOperation.php',
        'CompatibilityService.php',
        'SignatureMatrixContracts.php',
        'SignatureMatrixService.php',
        'ReadonlySignatureService.php',
        'InheritedSignatureParent.php',
        'InheritedSignatureService.php',
        'NeverSignatureService.php',
    ] as $fixture) {
        require_once $testsRoot . '/Fixtures/Aop/FrameworkProxyCompatibility/' . $fixture;
    }
    require_once __DIR__ . '/FrameworkProxyCompatibilityTest.php';
}

require_once $containerPath;
if (!class_exists($containerClass)) {
    fwrite(STDERR, "compiled container class missing\n");
    exit(1);
}

$container = new $containerClass();
$result = match ($scenario) {
    'application' => runApplication($container),
    'scheduled' => runScheduled($container),
    'compatibility' => runCompatibility($container),
    'signature' => runSignature($container),
    'never' => runNever($container),
    default => throw new RuntimeException('unknown runner scenario'),
};
echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";

/** @return array<string,mixed> */
function runApplication(object $container): array
{
    $connection = transactionConnection();
    $databases = new class($connection) implements \BlackOps\Database\DatabaseManager {
        public function __construct(
            private \Doctrine\DBAL\Connection $connection,
        ) {}

        public function connection(?string $name = null): \Doctrine\DBAL\Connection
        {
            return $this->connection;
        }
    };
    $container->set(\BlackOps\Database\DatabaseManager::class, $databases);
    $container->set(\Doctrine\DBAL\Connection::class, $connection);
    new \BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector()->inject(
        $container,
        $databases,
        new \BlackOps\Internal\Execution\ExecutionScopeProvider(),
    );
    $policy = $container->get(\BlackOps\Tests\Internal\Console\ApplicationBuildAuthorizationPolicy::class);
    $status = $container->get(\BlackOps\Status\OperationStatusAuthorizer::class);
    $codec = $container->get(\BlackOps\Internal\StorageProtection\BopdEnvelopeCodec::class);
    $service = $container->get(\BlackOps\Tests\Internal\Console\ApplicationBuildTransactionalService::class);
    return [
        'service' => $service->execute('application-build-aop'),
        'calls' => $service->calls,
        'framework_proxy' => str_contains($service::class, '__BlackOpsProxy_'),
        'has_database' => $container->has(\BlackOps\Database\DatabaseManager::class),
        'has_connection' => $container->has(\Doctrine\DBAL\Connection::class),
        'policy' => $policy instanceof \BlackOps\Tests\Internal\Console\ApplicationBuildAuthorizationPolicy,
        'dependency' =>
            $policy->dependency instanceof \BlackOps\Tests\Internal\Console\ApplicationBuildPolicyDependency,
        'status' => $status instanceof \BlackOps\Tests\Internal\Console\ApplicationBuildStatusAuthorizer,
        'codec' => $codec instanceof \BlackOps\Internal\StorageProtection\BopdEnvelopeCodec,
    ];
}

/** @return array{scheduled_actor:bool} */
function runScheduled(object $container): array
{
    $provider = $container->get(\BlackOps\Scheduling\ScheduledActorProvider::class);
    return [
        'scheduled_actor' =>
            $provider instanceof \BlackOps\Tests\Internal\Console\ApplicationBuildScheduledActorProvider,
    ];
}

/** @return array<string,mixed> */
function runCompatibility(object $container): array
{
    $connection = transactionConnection();
    $databases = new class($connection) implements \BlackOps\Database\DatabaseManager {
        public function __construct(
            private \Doctrine\DBAL\Connection $connection,
        ) {}

        public function connection(?string $name = null): \Doctrine\DBAL\Connection
        {
            return $this->connection;
        }
    };
    new \BlackOps\Internal\Database\RuntimeDatabaseServiceInjector()->inject($container, $databases);
    new \BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector()->inject(
        $container,
        $databases,
        new \BlackOps\Internal\Execution\ExecutionScopeProvider(),
    );
    $service = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityService::class);
    $same =
        $service === $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityService::class);
    $dependency =
        $service->dependency
        instanceof \BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityDependency;
    $typed = $service->typed('typed', 1, 2, 3);
    $value = $service->value('ok');
    $events = $service->events;
    $service->queue(true);
    $queueFailure = '';
    try {
        $service->queueAndRollback();
    } catch (\RuntimeException $exception) {
        $queueFailure = $exception->getMessage();
    }
    $valueFailure = '';
    try {
        $service->value('rollback');
    } catch (\RuntimeException $exception) {
        $valueFailure = $exception->getMessage();
    }
    $operation = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityOperation::class);
    $before = transactionCounters($connection);
    $operation->handle(new \BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityOperationValue());
    $direct = transactionCounters($connection);
    $accessor = $container->get(\BlackOps\Internal\Transaction\TransactionRuntimeAccessor::class);
    $accessor->transactional('app', fn() => $operation->handle(
        new \BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\CompatibilityOperationValue(),
    ));
    $outer = transactionCounters($connection);
    $signature = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\SignatureMatrixService::class);
    $signatureReflection = new \ReflectionClass($signature);
    $defaults = $signature->defaults();
    $intersection = new class implements \Countable, \IteratorAggregate {
        public function count(): int
        {
            return 0;
        }

        public function getIterator(): \Traversable
        {
            return new \ArrayIterator([]);
        }
    };
    $readonly = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\ReadonlySignatureService::class);
    $inherited = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\InheritedSignatureService::class);
    return [
        'same' => $same,
        'dependency' => $dependency,
        'typed' => $typed,
        'value' => $value,
        'events_before_queue' => $events,
        'events' => $service->events,
        'direct_delta' => [$direct[0] - $before[0], $direct[1] - $before[1]],
        'outer_delta' => [$outer[0] - $direct[0], $outer[1] - $direct[1]],
        'operation_calls' => $operation->calls,
        'failure' => [$queueFailure, $valueFailure],
        'signature' => [
            'union' => $signature->union('value'),
            'intersection' => $signature->intersection($intersection) === $signature,
            'variadic_positional' => $signature->variadic('positional', 1, 2, 3),
            'parent' => $signature->parentType() instanceof \stdClass,
            'dnf' => $signature->dnf(),
            'nullable' => $signature->nullable(),
            'mixed' => $signature->mixedValue(['mixed']),
            'static' => $signature->staticReturn() === $signature,
            'self' => $signature->selfReturn() === $signature,
            'defaults' => $defaults === $signature,
            'unrelated' => $signature->unrelated(),
            'methods' => array_map(
                static fn(\ReflectionMethod $method): string => $method->getName(),
                $signatureReflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
            'class_attribute' => $signatureReflection->getAttributes(\AllowDynamicProperties::class) !== [],
            'method_attribute' => $signatureReflection
                ->getMethod('unrelated')
                ->getAttributes(\ReturnTypeWillChange::class) !== [],
            'parameter_attribute' => $signatureReflection
                ->getMethod('unrelated')
                ->getParameters()[0]->getAttributes(\SensitiveParameter::class) !== [],
        ],
        'readonly' => new \ReflectionClass($readonly)->isReadOnly() && $readonly->value() === 'readonly',
        'inherited' => $inherited->inherited() === 'inherited',
    ];
}

/** @return array{named:string} */
function runSignature(object $container): array
{
    $connection = transactionConnection();
    $databases = new class($connection) implements \BlackOps\Database\DatabaseManager {
        public function __construct(
            private \Doctrine\DBAL\Connection $connection,
        ) {}

        public function connection(?string $name = null): \Doctrine\DBAL\Connection
        {
            return $this->connection;
        }
    };
    new \BlackOps\Internal\Database\RuntimeDatabaseServiceInjector()->inject($container, $databases);
    new \BlackOps\Internal\Transaction\RuntimeTransactionServiceInjector()->inject(
        $container,
        $databases,
        new \BlackOps\Internal\Execution\ExecutionScopeProvider(),
    );
    $service = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\SignatureMatrixService::class);
    return ['named' => $service->variadic(prefix: 'named', values: 4)];
}

/** @return array{never:bool,message:string} */
function runNever(object $container): array
{
    $service = $container->get(\BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility\NeverSignatureService::class);
    try {
        $service->neverReturns();
    } catch (\Throwable $exception) {
        return ['never' => true, 'message' => $exception->getMessage()];
    }
    return ['never' => false, 'message' => 'returned'];
}

/** @return array{0:int,1:int,2:int} */
function transactionCounters(\Doctrine\DBAL\Connection $connection): array
{
    return [$connection->blackopsBegins, $connection->blackopsCommits, $connection->blackopsRollbacks];
}

function transactionConnection(): \Doctrine\DBAL\Connection
{
    return new class([], new \Doctrine\DBAL\Driver\PDO\SQLite\Driver()) extends \Doctrine\DBAL\Connection {
        public int $blackopsBegins = 0;
        public int $blackopsCommits = 0;
        public int $blackopsRollbacks = 0;
        private bool $active = false;
        private int $level = 0;

        public function isTransactionActive(): bool
        {
            return $this->active;
        }

        public function getTransactionNestingLevel(): int
        {
            return $this->level;
        }

        public function beginTransaction(): void
        {
            ++$this->blackopsBegins;
            $this->active = true;
            $this->level = 1;
        }

        public function commit(): void
        {
            ++$this->blackopsCommits;
            $this->active = false;
            $this->level = 0;
        }

        public function rollBack(): void
        {
            ++$this->blackopsRollbacks;
            $this->active = false;
            $this->level = 0;
        }
    };
}
