<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyRuntime;

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyContractException;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnostic;
use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionBinding;
use BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyInvocation;

/**
 * Runtime composition seam for the compiled-container owner.
 *
 * The container integration phase supplies the generated proxy instance and
 * invokes its generated initializer; this class intentionally does not scan
 * source or discover definitions at runtime.
 */
final readonly class FrameworkProxyRuntimeInitializer
{
    public function __construct(
        private FrameworkProxyRuntimeInvocationFactory $factory,
    ) {}

    public function invocation(FrameworkProxyDefinitionBinding $binding): FrameworkProxyInvocation
    {
        return $this->factory->create($binding);
    }

    public function initialize(object $proxy, FrameworkProxyDefinitionBinding $binding): void
    {
        if ($proxy::class !== $binding->proxyClass) {
            throw new FrameworkProxyContractException(
                new FrameworkProxyDiagnostic(
                    FrameworkProxyDiagnosticCode::OWNERSHIP_CONFLICT,
                    serviceId: $binding->serviceId,
                    sourceClass: $binding->sourceClass,
                ),
            );
        }
        if (!method_exists($proxy, '__blackopsInitialize')) {
            throw new \LogicException('Framework proxy initializer is unavailable.');
        }

        /** @var callable(\BlackOps\Internal\Aop\FrameworkProxyGenerator\FrameworkProxyInvocation): void $initializer */
        $initializer = [$proxy, '__blackopsInitialize'];
        $initializer($this->factory->create($binding));
    }
}
