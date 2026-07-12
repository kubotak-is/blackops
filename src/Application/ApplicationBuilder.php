<?php

declare(strict_types=1);

namespace BlackOps\Application;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Internal\Application\ApplicationBasePath;
use BlackOps\Internal\Application\ApplicationConfigurationLoader;
use BlackOps\Internal\Application\ApplicationConfigurationRegistrations;
use BlackOps\Internal\Application\ApplicationConfigurationSnapshot;
use BlackOps\Internal\Application\ApplicationEnvironment;
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

    /** @var array<string, array<array-key, mixed>> */
    private array $configuration = [];

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
            $this->environment = new ApplicationEnvironment()->validate($variables);
        } catch (InvalidArgumentException $exception) {
            throw new ApplicationBootstrapException($exception->getMessage(), previous: $exception);
        }

        return $this;
    }

    public function withConfiguration(?string $directory = null): self
    {
        try {
            $loader = new ApplicationConfigurationLoader();
            $this->configuration = $directory === null
                ? $loader->loadOptional($this->basePath . DIRECTORY_SEPARATOR . 'config')
                : $loader->load($directory);
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
        $validator = new ApplicationRegistrationValidator();
        $configured = new ApplicationConfigurationRegistrations($this->configuration);

        try {
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
            new ApplicationConfigurationSnapshot(
                $this->basePath,
                $this->environment,
                $this->configuration,
                $operations,
                $services,
                $commands,
            ),
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
