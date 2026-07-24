<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Execution\DispatchReceipt;
use BlackOps\Execution\Operations;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Outbox\OutboxRegistration;
use BlackOps\Outbox\TransactionalOutbox;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use LogicException;
use Psr\Clock\ClockInterface;

final readonly class TransactionalOutboxRuntime implements TransactionalOutbox, Operations
{
    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        private OperationRegistry $operations,
        private OperationCodec $codec,
        private ExecutionScopeProvider $scope,
        private TransactionRuntime $transactions,
        private Connection $connection,
        private string $connectionName,
        private PostgreSqlOutboxStore $store,
        private ExecutionContextFactory $contexts,
        private IdentifierFactory $identifiers,
        private ClockInterface $clock,
    ) {}

    public function dispatch(
        string $definition,
        OperationValue $value,
        ?DateTimeImmutable $availableAt = null,
        ?ActorRef $executionActor = null,
    ): DispatchReceipt {
        $metadata = $this->operations->findByDefinition($definition);
        if (!$metadata instanceof OperationMetadata) {
            throw new InvalidArgumentException('Deferred operation definition is not registered.');
        }
        if ($metadata->strategy !== Deferred::class) {
            throw new InvalidArgumentException('Transactional operation dispatch requires a deferred operation.');
        }
        if (!$value instanceof $metadata->value) {
            throw new InvalidArgumentException('Operation dispatch value does not match metadata.');
        }
        $registration = $this->registerMetadata($metadata, $value, $availableAt, $executionActor);

        return new DispatchReceipt($registration->operationId(), $registration->recordedAt());
    }

    public function register(
        Operation $definition,
        OperationValue $value,
        ?DateTimeImmutable $availableAt = null,
        ?ActorRef $executionActor = null,
    ): OutboxRegistration {
        $metadata = $this->operations->findByDefinition($definition::class);
        if (!$metadata instanceof OperationMetadata) {
            throw new InvalidArgumentException('Outbox operation definition is not registered.');
        }

        return $this->registerMetadata($metadata, $value, $availableAt, $executionActor);
    }

    private function registerMetadata(
        OperationMetadata $metadata,
        OperationValue $value,
        ?DateTimeImmutable $availableAt,
        ?ActorRef $executionActor,
    ): OutboxRegistration {
        $parent = $this->scope->current();
        if ($parent === null) {
            throw new LogicException('Transactional outbox registration requires an active operation context.');
        }
        if ($metadata->strategy !== Deferred::class) {
            throw new InvalidArgumentException('Transactional outbox registration requires a deferred operation.');
        }
        if (!$value instanceof $metadata->value) {
            throw new InvalidArgumentException('Outbox operation value does not match metadata.');
        }

        $transaction = $this->transactions->currentScope();
        if (
            $transaction === null
            || $transaction->connectionName !== $this->connectionName
            || $transaction->connection !== $this->connection
            || !$this->connection->isTransactionActive()
            || $this->connection->getTransactionNestingLevel() !== 1
        ) {
            throw new LogicException('Transactional outbox requires the active framework-owned root transaction.');
        }

        $recordedAt = $this->clock->now();
        $child = $this->contexts->createChild($parent->context(), null, $executionActor);
        $encoded = $this->codec->encode($metadata, $value, $child);
        $recordId = $this->identifiers->newOutboxRecordId();
        $record = new PostgreSqlOutboxRecord(
            $recordId,
            $child->operationId(),
            $encoded->operationType(),
            $encoded->schemaVersion(),
            $encoded->encodedPayload(),
            $encoded->encodedContext(),
            $availableAt ?? $recordedAt,
            $recordedAt,
            $this->connectionName,
        );
        $this->store->insert($record);

        return new OutboxRegistration($recordId, $child->operationId(), $recordedAt);
    }
}
