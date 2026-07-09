<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

final class ExecutionScopedLogger extends AbstractLogger
{
    public function __construct(
        private LoggerInterface $inner,
        private ExecutionScopeProvider $scope,
        private SensitiveProjectionFilter $sensitive = new SensitiveProjectionFilter(),
    ) {}

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        $this->inner->log($level, $message, $this->enrich($context));
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function enrich(array $context): array
    {
        $enriched = [
            'context' => $this->sensitive->projectArray($context),
        ];
        $operation = $this->scope->current();

        if ($operation !== null) {
            $enriched['operation'] = $this->operation($operation);
        }

        return $enriched;
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(OperationEnvelope $operation): array
    {
        $context = $operation->context();
        $attempt = $context->attempt();

        return [
            'id' => $context->operationId()->toString(),
            'type' => $this->scope->currentOperationTypeId(),
            'attemptId' => $attempt === null ? null : $attempt->id()->toString(),
            'correlationId' => $context->correlationId()->toString(),
            'causationId' => $context->causationId()?->toString(),
            'strategy' => $operation->strategy()::class,
        ];
    }
}
