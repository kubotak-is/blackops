<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationBasePath;
use BlackOps\Internal\Application\ApplicationConfigurationLoader;
use BlackOps\Internal\Application\ApplicationConfigurationRegistrations;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationRegistrationValidator;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;

#[PublicApi]
final class ApplicationBuilder
{
    private readonly string $basePath;

    /** @var array<string, string> */
    private array $environment = [];

    private ?string $configurationDirectory = null;

    /** @var list<mixed> */
    private array $operationProviders = [];

    /** @var list<mixed> */
    private array $serviceProviders = [];

    /** @var list<mixed> */
    private array $commands = [];

    private function __construct(string $basePath)
    {
        try {
            $this->basePath = new ApplicationBasePath()->normalize($basePath);
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage(), previous: $exception);
        }
    }

    /** @param array<array-key, mixed>|null $variables */
    public function withEnvironment(?array $variables = null): self
    {
        $variables ??= $this->processEnvironment();

        try {
            new Environment($variables);
            /** @var array<string, string> $variables */
            $this->environment = $variables;
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage(), previous: $exception);
        }

        return $this;
    }

    public function withConfiguration(?string $directory = null): self
    {
        try {
            $loader = new ApplicationConfigurationLoader();
            $this->configurationDirectory = $directory === null
                ? $loader->resolveOptional($this->basePath . DIRECTORY_SEPARATOR . 'config')
                : $loader->resolve($directory);
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage(), previous: $exception);
        }

        return $this;
    }

    /** @param iterable<array-key, mixed> $providers */
    public function withOperations(iterable $providers = []): self
    {
        $this->operationProviders = [...$this->operationProviders, ...$providers];

        return $this;
    }

    /** @param iterable<array-key, mixed> $providers */
    public function withServices(iterable $providers = []): self
    {
        $this->serviceProviders = [...$this->serviceProviders, ...$providers];

        return $this;
    }

    /** @param iterable<array-key, mixed> $commands */
    public function withCommands(iterable $commands = []): self
    {
        $this->commands = [...$this->commands, ...$commands];

        return $this;
    }

    public function create(): Application
    {
        try {
            $environment = new Environment($this->environment);
            $configuration = $this->configurationDirectory === null
                ? []
                : new ApplicationConfigurationLoader()->load($this->configurationDirectory, $environment);
            $validator = new ApplicationRegistrationValidator();
            $configured = new ApplicationConfigurationRegistrations($configuration);
            $operations = $validator->operationProviders([
                ...$configured->operations(),
                ...$this->operationProviders,
            ]);
            $services = $validator->serviceProviders([
                ...$configured->services(),
                ...$this->serviceProviders,
            ]);
            $commands = $validator->commands([
                ...$configured->commands(),
                ...$this->commands,
            ]);
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage(), previous: $exception);
        }

        return $this->application(
            new ApplicationConfigurationSnapshot($this->basePath, $configuration, $operations, $services, $commands),
        );
    }

    private function application(ApplicationConfigurationSnapshot $configuration): Application
    {
        $reflection = new ReflectionClass(Application::class);
        $application = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            throw new LogicException('Unable to initialize the application.');
        }

        $constructor->invoke($application, $configuration);

        return $application;
    }

    /** @return array<string, string> */
    private function processEnvironment(): array
    {
        return getenv();
    }
}
