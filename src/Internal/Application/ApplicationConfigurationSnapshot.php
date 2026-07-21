<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationProvider;
use Symfony\Component\Console\Command\Command;

final readonly class ApplicationConfigurationSnapshot
{
    /**
     * @param array<string, array<array-key, mixed>> $configuration
     * @param list<OperationProvider|class-string<OperationProvider>> $operationProviders
     * @param list<ServiceProvider|class-string<ServiceProvider>> $serviceProviders
     * @param list<Command|class-string<Command>> $commands
     */
    public function __construct(
        private string $basePath,
        private array $configuration,
        private array $operationProviders,
        private array $serviceProviders,
        private array $commands,
    ) {}

    public function basePath(): string
    {
        return $this->basePath;
    }

    /** @return array<string, array<array-key, mixed>> */
    public function configuration(): array
    {
        return $this->configuration;
    }

    /** @return list<OperationProvider|class-string<OperationProvider>> */
    public function operationProviders(): array
    {
        return $this->operationProviders;
    }

    /** @return list<ServiceProvider|class-string<ServiceProvider>> */
    public function serviceProviders(): array
    {
        return $this->serviceProviders;
    }

    /** @return list<Command|class-string<Command>> */
    public function commands(): array
    {
        return $this->commands;
    }
}
