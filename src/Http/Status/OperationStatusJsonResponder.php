<?php

declare(strict_types=1);

namespace BlackOps\Http\Status;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Outcome;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Outcome\Internal\StructuredOutcomeNormalizer;
use BlackOps\Status\OperationStatus;
use BlackOps\Status\OperationStatusExpired;
use BlackOps\Status\OperationStatusFound;
use BlackOps\Status\OperationStatusResult;
use BlackOps\Status\OperationStatusState;
use BlackOps\Status\OperationStatusUnavailable;
use JsonException;
use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use stdClass;

final readonly class OperationStatusJsonResponder
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private TimeCodec $time = new TimeCodec(),
        private StructuredOutcomeNormalizer $outcomes = new StructuredOutcomeNormalizer(),
    ) {}

    public function respond(OperationStatusResult $result): ResponseInterface
    {
        if ($result instanceof OperationStatusFound) {
            return $this->found($result->status());
        }

        if ($result instanceof OperationStatusUnavailable) {
            return $this->error(404, 'operation_unavailable');
        }

        if ($result instanceof OperationStatusExpired) {
            return $this->error(410, 'operation_expired');
        }

        return $this->internalError();
    }

    public function unavailable(): ResponseInterface
    {
        return $this->error(404, 'operation_unavailable');
    }

    public function protocolError(): ResponseInterface
    {
        return $this->responses->createResponse(400);
    }

    public function internalError(): ResponseInterface
    {
        return $this->error(500, 'internal_error');
    }

    private function found(OperationStatus $status): ResponseInterface
    {
        $payload = [
            'schemaVersion' => OperationStatusHttpContract::SCHEMA_VERSION,
            'operationId' => $status->operationId()->toString(),
            'operationType' => $status->operationType(),
            'state' => $status->state()->value,
            ...$this->statePayload($status),
        ];
        $response = $this->json(200, $payload);

        return $status->state()->isTerminal()
            ? $response
            : $response->withHeader('Retry-After', (string) OperationStatusHttpContract::POLLING_HINT_SECONDS);
    }

    /** @return array<string, mixed> */
    private function statePayload(OperationStatus $status): array
    {
        return match ($status->state()) {
            OperationStatusState::Accepted => [],
            OperationStatusState::Running => [
                'attempt' => $status->attempt() ?? throw new LogicException('Running status requires an attempt.'),
            ],
            OperationStatusState::RetryScheduled => [
                'attempt' => $status->attempt() ?? throw new LogicException('Retry status requires an attempt.'),
                'retryAt' => $this->time->format(
                    $status->retryAt() ?? throw new LogicException('Retry status requires a retry time.'),
                ),
            ],
            OperationStatusState::Completed => [
                'outcome' => $this->outcome(
                    $status->outcome() ?? throw new LogicException('Completed status requires an outcome.'),
                ),
            ],
            OperationStatusState::Rejected => [
                'error' => [
                    'category' => $status->error()?->category() ?? throw new LogicException(
                        'Rejected status requires an error category.',
                    ),
                    'code' => $status->error()?->code() ?? throw new LogicException(
                        'Rejected status requires an error code.',
                    ),
                ],
            ],
            OperationStatusState::Failed, OperationStatusState::DeadLettered => [
                'error' => [
                    'code' => $status->error()?->code() ?? throw new LogicException(
                        'Terminal status requires an error code.',
                    ),
                ],
            ],
        };
    }

    /** @return array<string, mixed>|stdClass */
    private function outcome(Outcome $outcome): array|stdClass
    {
        if ($outcome instanceof EmptyOutcome) {
            return new stdClass();
        }

        $payload = $this->outcomes->normalize($outcome);

        return $payload === [] ? new stdClass() : $payload;
    }

    private function error(int $status, string $code): ResponseInterface
    {
        return $this->json($status, [
            'status' => 'error',
            'code' => $code,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(int $status, array $payload): ResponseInterface
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode operation status response.', previous: $exception);
        }

        return $this->responses
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', OperationStatusHttpContract::CACHE_CONTROL)
            ->withBody($this->streams->createStream($body));
    }
}
