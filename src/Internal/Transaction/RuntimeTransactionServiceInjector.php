<?php

declare(strict_types=1);

namespace BlackOps\Internal\Transaction;

use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use Symfony\Component\DependencyInjection\Container;
use Throwable;

final readonly class RuntimeTransactionServiceInjector
{
    public function inject(
        ContainerInterface $container,
        DatabaseManager $databases,
        ExecutionScopeProvider $executionScope,
    ): TransactionRuntime {
        if (!$container instanceof Container) {
            throw new InvalidArgumentException('Runtime container does not support transaction service injection.');
        }

        /** @var mixed $synthetic */
        $synthetic = new ReflectionObject($container)
            ->getProperty('syntheticIds')
            ->getValue($container);
        $serviceIds = $container->getServiceIds();
        $runtimeDefinition = is_array($synthetic) && ($synthetic[TransactionRuntime::class] ?? false) === true;

        if (!$runtimeDefinition && !in_array(TransactionRuntime::class, $serviceIds, strict: true)) {
            throw new InvalidArgumentException('Runtime container is missing a valid transaction service definition.');
        }

        $runtime = new TransactionRuntime($databases, new DefaultAfterCommitFailureReporter(), $executionScope);

        try {
            $container->set(TransactionRuntime::class, $runtime);
            $accessor = $container->get(TransactionRuntimeAccessor::class);
            $reporter = $container->get(AfterCommitFailureReporter::class);
        } catch (Throwable) {
            throw new InvalidArgumentException('Runtime container is missing valid transaction service definitions.');
        }

        if (!$reporter instanceof AfterCommitFailureReporter) {
            throw new InvalidArgumentException('Runtime after-commit failure reporter is invalid.');
        }

        if (!$accessor instanceof TransactionRuntimeAccessor) {
            throw new InvalidArgumentException('Runtime transaction accessor is invalid.');
        }

        $runtime->replaceReporter($reporter);
        $accessor->set($runtime);

        return $runtime;
    }
}
