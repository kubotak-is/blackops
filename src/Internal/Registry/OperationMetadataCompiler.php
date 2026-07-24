<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\ConsoleCommand;
use BlackOps\Core\Attribute\Deferred as DeferredAttribute;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Outcome;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;
use InvalidArgumentException;
use ReflectionClass;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class OperationMetadataCompiler
{
    public function __construct(
        private OperationHandlerMetadataCompiler $handlers = new OperationHandlerMetadataCompiler(),
        ?OperationValueOutcomeCompiler $valueOutcomes = null,
        private ?string $defaultTransactionConnection = null,
        private array $knownTransactionConnections = [],
        private EphemeralOutcomeContractCompiler $ephemeralOutcomes = new EphemeralOutcomeContractCompiler(),
    ) {
        $this->valueOutcomes = $valueOutcomes ?? new OperationValueOutcomeCompiler($this->handlers);
    }

    private OperationValueOutcomeCompiler $valueOutcomes;

    /** @param class-string<Operation> $definition */
    public function compile(string $definition): OperationMetadata
    {
        $reflection = new ReflectionClass($definition);
        if (!$reflection->implementsInterface(Operation::class)) {
            throw new InvalidArgumentException('Operation definition must implement Operation.');
        }
        $typeAttributes = $reflection->getAttributes(OperationType::class);
        $acceptsAttributes = $reflection->getAttributes(Accepts::class);
        $handlerAttributes = $reflection->getAttributes(HandledBy::class);
        $returnsAttributes = $reflection->getAttributes(Returns::class);
        $authorizationAttributes = $reflection->getAttributes(Authorize::class);
        if (count($typeAttributes) !== 1) {
            throw new InvalidArgumentException('Operation definition requires OperationType exactly once.');
        }
        if (count($handlerAttributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat HandledBy.');
        }
        if (count($authorizationAttributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat Authorize.');
        }
        $strategyAttributes = $reflection->getAttributes(ExecuteWith::class);
        if (count($strategyAttributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat ExecuteWith.');
        }
        $deferredAttributes = $reflection->getAttributes(DeferredAttribute::class);
        if (count($deferredAttributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat Deferred.');
        }
        if ($deferredAttributes !== [] && $strategyAttributes !== []) {
            throw new InvalidArgumentException('Operation definition must not combine Deferred and ExecuteWith.');
        }
        $strategy = match (true) {
            $deferredAttributes !== [] => Deferred::class,
            $strategyAttributes === [] => Inline::class,
            default => $strategyAttributes[0]->newInstance()->strategy,
        };
        $type = $typeAttributes[0]->newInstance();
        [$value, $outcome] = $this->valueOutcomes->compile(
            $reflection,
            $acceptsAttributes,
            $returnsAttributes,
            $handlerAttributes,
        );

        [$handler, $typedSelfHandled, $typedSelfHandledContext, $typedSelfHandledMode] = $this->handlers->compile(
            $reflection,
            $handlerAttributes,
            $value,
            $outcome,
        );
        $this->assertImplements($value, OperationValue::class);
        $this->assertImplements($outcome, Outcome::class);
        $this->assertImplements($strategy, ExecutionStrategy::class);
        $authorizationPolicy = $authorizationAttributes === []
            ? null
            : $authorizationAttributes[0]->newInstance()->policy;
        if ($authorizationPolicy !== null) {
            $this->assertImplements($authorizationPolicy, AuthorizationPolicy::class);
        }
        $transactionConnection = $this->transactionConnection($reflection, $handler);
        $ephemeral = is_a($outcome, EphemeralOutcome::class, allow_string: true);
        if ($ephemeral) {
            $this->assertEphemeralOperation($reflection, $outcome, $strategyAttributes, $strategy);
        }

        return new OperationMetadata(
            $type->id,
            $definition,
            $value,
            $handler,
            $outcome,
            $strategy,
            $typedSelfHandled,
            $typedSelfHandledContext,
            $typedSelfHandledMode,
            $authorizationPolicy,
            $transactionConnection,
        );
    }

    /**
     * @param ReflectionClass<Operation> $definition
     * @param class-string<\BlackOps\Core\Outcome> $outcome
     * @param list<\ReflectionAttribute<ExecuteWith>> $strategyAttributes
     */
    private function assertEphemeralOperation(
        ReflectionClass $definition,
        string $outcome,
        array $strategyAttributes,
        string $strategy,
    ): void {
        $routes = $definition->getAttributes(Route::class);
        if (count($routes) !== 1) {
            throw new InvalidArgumentException('Ephemeral operation requires exactly one HTTP Route.');
        }
        if (count($strategyAttributes) !== 1 || $strategy !== Inline::class) {
            throw new InvalidArgumentException('Ephemeral operation requires an explicit Inline execution strategy.');
        }
        if ($definition->getAttributes(ConsoleCommand::class) !== []) {
            throw new InvalidArgumentException('Ephemeral operation must not declare ConsoleCommand.');
        }

        /** @var class-string<EphemeralOutcome> $outcome */
        $this->ephemeralOutcomes->compile($outcome);
    }

    /** @param ReflectionClass<Operation> $definition @param class-string $handler */
    private function transactionConnection(ReflectionClass $definition, string $handler): ?string
    {
        $attributes = $definition->getAttributes(Transactional::class);
        if (count($attributes) > 1) {
            throw new InvalidArgumentException('Operation definition must not repeat Transactional.');
        }

        $transactional = $attributes === [] ? null : $attributes[0]->newInstance();

        if ($definition->getName() === $handler && $definition->hasMethod('handle')) {
            $methodAttributes = $definition->getMethod('handle')->getAttributes(Transactional::class);
            if (count($methodAttributes) > 1) {
                throw new InvalidArgumentException('Operation handle method must not repeat Transactional.');
            }

            if ($methodAttributes !== []) {
                $transactional = $methodAttributes[0]->newInstance();
            }
        }

        if (!$transactional instanceof Transactional) {
            return null;
        }

        if ($this->knownTransactionConnections === []) {
            throw new InvalidArgumentException('Transactional operation requires database configuration.');
        }

        $connection = $transactional->connection ?? $this->defaultTransactionConnection;
        if ($connection === null || !in_array($connection, $this->knownTransactionConnections, strict: true)) {
            throw new InvalidArgumentException('Transactional operation references an unknown database connection.');
        }

        return $connection;
    }

    /** @param class-string $class @param class-string $interface */
    private function assertImplements(string $class, string $interface): void
    {
        if (!is_a($class, $interface, allow_string: true)) {
            throw new InvalidArgumentException('Operation metadata class does not implement its required contract.');
        }
    }
}
