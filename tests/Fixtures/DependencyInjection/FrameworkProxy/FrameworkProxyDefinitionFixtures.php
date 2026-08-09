<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\DependencyInjection\FrameworkProxy;

use BlackOps\Database\Attribute\Transactional;

final class FrameworkProxyDefinitionDependency {}

class PreservedFrameworkService
{
    public string $configured = '';

    public function __construct(
        public FrameworkProxyDefinitionDependency $dependency,
    ) {}

    public function configure(): void
    {
        $this->configured .= '->configure';
    }

    public function returnsClone(): self
    {
        return $this;
    }

    #[Transactional]
    public function execute(): string
    {
        return 'executed';
    }

    public static function configureService(self $service): void
    {
        $service->configured .= '->configurator';
    }

    public static function create(): self
    {
        return new self(new FrameworkProxyDefinitionDependency());
    }
}

#[Transactional]
class SyntheticFrameworkService {}

class PlainFrameworkService {}

#[Transactional]
class RayOwnedFrameworkService implements \Ray\Aop\WeavedInterface
{
    public function _setBindings(array $bindings): void {}
}
