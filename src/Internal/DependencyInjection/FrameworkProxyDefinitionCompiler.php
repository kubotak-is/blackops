<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContract;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnostic;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyOwnershipGuard;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyProfile;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionCompilation;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionException;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionRegistry;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerationTarget;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyGenerator;
use BlackOps\Internal\Runtime\FrameworkProxyArtifactLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Replaces attributed Symfony Definitions with generated Framework subclasses.
 *
 * This compiler deliberately records binding metadata separately. Runtime
 * invocation wiring is owned by the following phase and must not alter the
 * Definition contract while this preservation boundary is being compiled.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrameworkProxyDefinitionCompiler
{
    public function __construct(
        private FrameworkProxyContract $contract = new FrameworkProxyContract(),
        private FrameworkProxyOwnershipGuard $ownership = new FrameworkProxyOwnershipGuard(),
        private FrameworkProxyGenerator $generator = new FrameworkProxyGenerator(),
        private FrameworkProxyDefinitionRegistry $registry = new FrameworkProxyDefinitionRegistry(),
    ) {}

    /**
     * @mago-expect lint:halstead
     * @mago-expect lint:excessive-parameter-list
     *
     * @param array<string,true>|list<string> $connectionNames
     */
    public function compile(
        ContainerBuilder $builder,
        string $buildId,
        string $artifactDirectory,
        string|FrameworkProxyProfile $profile = FrameworkProxyProfile::FRAMEWORK,
        ?string $defaultConnection = null,
        array $connectionNames = [],
    ): FrameworkProxyDefinitionCompilation {
        $profile = FrameworkProxyProfile::from($profile);
        $this->ownership->assertProfile($profile, FrameworkProxyProfile::FRAMEWORK);
        $definitions = $builder->getDefinitions();
        ksort($definitions);
        $targets = [];
        $metadataById = [];
        $seenSources = [];

        foreach ($definitions as $id => $definition) {
            $class = $this->class($id, $definition);
            if ($class === null) {
                continue;
            }
            $metadata = $this->contract->inspect($class, $profile, $id, $buildId, $defaultConnection, $connectionNames);
            if (!$metadata->proxyTarget) {
                continue;
            }

            $this->assertSingleOwnership($id, $class, $buildId);
            $this->assertSupported($id, $class, $definition, $buildId);
            $metadataById[$id] = $metadata;
            if (array_key_exists($class, $seenSources)) {
                continue;
            }
            $seenSources[$class] = true;
            $targets[] = new FrameworkProxyGenerationTarget(
                $class,
                serviceId: $id,
                defaultConnection: $defaultConnection,
                connectionNames: $connectionNames,
            );
        }

        if ($targets === []) {
            return new FrameworkProxyDefinitionCompilation([]);
        }

        $generation = $this->generator->generateBatch($targets, $buildId, $artifactDirectory, $profile);
        new FrameworkProxyArtifactLoader()->load(
            $generation->directory,
            $buildId,
            $generation->manifest->manifestHash,
            $profile,
        );

        foreach ($metadataById as $id => $metadata) {
            $class = $metadata->sourceClass;
            if (!is_string($generation->classMap[$class] ?? null)) {
                throw $this->error(FrameworkProxyDefinitionDiagnosticCode::MAP_MISMATCH, $id, $class, $buildId);
            }
        }

        $bindings = [];
        foreach ($metadataById as $id => $metadata) {
            $definition = $definitions[$id];
            $class = $metadata->sourceClass;
            $proxyClass = $generation->classMap[$class] ?? null;
            if (!is_string($proxyClass)) {
                throw $this->error(FrameworkProxyDefinitionDiagnosticCode::MAP_MISMATCH, $id, $class, $buildId);
            }
            $definition->setClass($proxyClass);
            $binding = new FrameworkProxyDefinitionBinding(
                $id,
                $class,
                $proxyClass,
                $metadata,
                $this->ownership->marker($metadata),
            );
            $bindings[$id] = $binding;
            $this->registry->set($definition, $binding);
        }

        return new FrameworkProxyDefinitionCompilation($bindings, $generation);
    }

    public function binding(Definition $definition): ?FrameworkProxyDefinitionBinding
    {
        return $this->registry->get($definition);
    }

    /** @return class-string|null */
    private function class(string $id, Definition $definition): ?string
    {
        $class = $definition->getClass();
        if ($class === null && class_exists($id)) {
            return $id;
        }
        if ($class === null || str_contains($class, '%') || !class_exists($class)) {
            return null;
        }
        return $class;
    }

    private function assertSupported(string $id, string $class, Definition $definition, string $buildId): void
    {
        $code = match (true) {
            $definition->getFactory() !== null => FrameworkProxyDiagnosticCode::DEFINITION_FACTORY,
            $definition->isLazy() => FrameworkProxyDiagnosticCode::DEFINITION_LAZY,
            $definition->isSynthetic() => FrameworkProxyDiagnosticCode::DEFINITION_SYNTHETIC,
            $definition->isAbstract() => FrameworkProxyDiagnosticCode::DEFINITION_ABSTRACT,
            $definition->getDecoratedService() !== null => FrameworkProxyDiagnosticCode::DEFINITION_DECORATION,
            default => null,
        };
        if ($code !== null) {
            throw $this->error($code, $id, $class, $buildId);
        }
    }

    private function assertSingleOwnership(string $id, string $class, string $buildId): void
    {
        if (
            str_contains($class, '\\__BlackOpsProxy_')
            || str_starts_with($class, '__BlackOpsProxy_')
            || is_a($class, class: 'Ray\\Aop\\WeavedInterface', allow_string: true)
        ) {
            throw $this->error(FrameworkProxyDiagnosticCode::MODE_CONFLICT, $id, $class, $buildId);
        }
    }

    private function error(string $code, string $id, string $class, string $buildId): FrameworkProxyDefinitionException
    {
        return new FrameworkProxyDefinitionException(
            new FrameworkProxyDiagnostic($code, serviceId: $id, sourceClass: $class, buildId: $buildId),
        );
    }
}
