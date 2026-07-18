<?php

declare(strict_types=1);

namespace BlackOps\Internal\Logging;

use BlackOps\Core\ActorRef;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Projection\SensitiveProjectionFilter;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

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
        $this->write($level, $message, $context, 'application');
    }

    public function frameworkError(
        string $failureType,
        bool $journalRecorded,
        ?string $secondaryFailureType = null,
    ): void {
        $failure = [
            'classification' => 'internal_error',
            'type' => $failureType,
            'journalRecorded' => $journalRecorded,
        ];

        if ($secondaryFailureType !== null) {
            $failure['secondary'] = [
                'classification' => 'failure_recording_failed',
                'type' => $secondaryFailureType,
            ];
        }

        $this->write(LogLevel::ERROR, 'Operation failed.', ['failure' => $failure], 'framework');
    }

    /**
     * @param array<array-key, mixed> $context
     */
    /** @mago-expect lint:no-empty-catch-clause */
    private function write(mixed $level, Stringable|string $message, array $context, string $kind): void
    {
        try {
            $this->inner->log($level, $message, $this->enrich($context, $kind));
        } catch (Throwable) {
        }
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function enrich(array $context, string $kind): array
    {
        $enriched = [
            'schemaVersion' => 1,
            'kind' => $kind,
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
        $actors = $context->actorContext();

        return [
            'id' => $context->operationId()->toString(),
            'type' => $this->scope->currentOperationTypeId(),
            'attemptId' => $attempt === null ? null : $attempt->id()->toString(),
            'correlationId' => $context->correlationId()->toString(),
            'causationId' => $context->causationId()?->toString(),
            'strategy' => $operation->strategy()::class,
            'actors' => $actors === null
                ? null
                : [
                    'origin' => $this->actor($actors->origin()),
                    'authorization' => $this->actor($actors->authorization()),
                    'execution' => $this->actor($actors->execution()),
                ],
        ];
    }

    /** @return array{id: string, type: string}|null */
    private function actor(?ActorRef $actor): ?array
    {
        return (
            $actor === null
                ? null
                : [
                    'id' => '[masked]',
                    'type' => $actor->type(),
                ]
        );
    }
}
