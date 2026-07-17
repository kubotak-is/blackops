<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;

final readonly class RuntimeAopCompiler
{
    public function __construct(
        private AopArtifactDirectory $artifacts = new AopArtifactDirectory(),
        private AopServiceDefinitionCompiler $definitions = new AopServiceDefinitionCompiler(),
    ) {}

    /** @param list<string> $connectionNames */
    public function compile(
        ContainerBuilder $builder,
        string $containerPath,
        ?string $defaultConnection,
        array $connectionNames,
    ): RuntimeAopCompilation {
        $context = new AopCompilationContext(
            $this->artifacts->prepare($containerPath),
            $defaultConnection,
            array_fill_keys(keys: $connectionNames, value: true),
        );
        $definitions = $builder->getDefinitions();
        ksort($definitions);
        $proxyFiles = [];

        try {
            foreach ($definitions as $id => $definition) {
                $proxyFile = $this->definitions->compile($builder, $id, $definition, $context);

                if ($proxyFile !== null) {
                    $proxyFiles[] = $proxyFile;
                }
            }
        } catch (Throwable $throwable) {
            $this->artifacts->clear($containerPath);

            throw $throwable;
        }

        $proxyFiles = array_values(array_unique($proxyFiles));
        sort($proxyFiles);

        return new RuntimeAopCompilation($proxyFiles);
    }

    public function discard(string $containerPath): void
    {
        $this->artifacts->clear($containerPath);
    }
}
