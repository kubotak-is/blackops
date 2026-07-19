<?php

declare(strict_types=1);

namespace BlackOps\Http\Responder;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Core\Validation\Violation;
use BlackOps\Http\Status\OperationStatusHttpContract;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use ReflectionClass;
use RuntimeException;

final readonly class JsonOperationResponder
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private TimeCodec $time = new TimeCodec(),
    ) {}

    public function respond(OperationResult $result): ResponseInterface
    {
        if ($result->isRejected()) {
            $reason = $result->rejectionReason();
            $operationId = $result->operationId();

            return $this->json($this->statusFor($reason->category()), [
                'status' => 'rejected',
                ...($operationId === null ? [] : ['operationId' => $operationId->toString()]),
                'category' => $reason->category()->value,
                'code' => $reason->code(),
            ]);
        }

        $outcome = $result->outcome();

        if ($outcome instanceof EmptyOutcome) {
            return $this->responses->createResponse(204);
        }

        return $this->json(200, $this->normalizeOutcome($outcome));
    }

    public function respondAcknowledgement(DeferredAcknowledgement $acknowledgement): ResponseInterface
    {
        return $this
            ->json(202, [
                'status' => 'accepted',
                'operationId' => $acknowledgement->operationId()->toString(),
                'acceptedAt' => $this->time->format($acknowledgement->acceptedAt()),
            ])
            ->withHeader('Location', '/operations/' . $acknowledgement->operationId()->toString())
            ->withHeader('Retry-After', (string) OperationStatusHttpContract::POLLING_HINT_SECONDS)
            ->withHeader('Cache-Control', OperationStatusHttpContract::CACHE_CONTROL);
    }

    public function respondProtocolError(string $code): ResponseInterface
    {
        return $this->json(400, [
            'status' => 'error',
            'code' => $code,
        ]);
    }

    public function respondInternalError(OperationId $operationId): ResponseInterface
    {
        return $this->json(500, [
            'status' => 'error',
            'code' => 'internal_error',
            'operationId' => $operationId->toString(),
        ]);
    }

    public function respondUncorrelatedInternalError(): ResponseInterface
    {
        return $this->json(500, [
            'status' => 'error',
            'code' => 'internal_error',
        ]);
    }

    /**
     * @param list<Violation> $violations
     */
    public function respondValidationRejection(OperationId $operationId, array $violations): ResponseInterface
    {
        return $this->json(422, [
            'status' => 'rejected',
            'operationId' => $operationId->toString(),
            'category' => RejectionCategory::Validation->value,
            'code' => 'validation.failed',
            'violations' => array_map(static fn(Violation $violation): array => [
                'field' => $violation->field,
                'rule' => $violation->rule,
                'code' => $violation->code,
            ], $violations),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOutcome(Outcome $outcome): array
    {
        $data = [];

        foreach (new ReflectionClass($outcome)->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }

            $data[$property->getName()] = $property->getValue($outcome);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(int $status, array $payload): ResponseInterface
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode HTTP response payload.', previous: $exception);
        }

        return $this->responses
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream($body));
    }

    private function statusFor(RejectionCategory $category): int
    {
        return match ($category) {
            RejectionCategory::Validation => 422,
            RejectionCategory::Unauthorized => 401,
            RejectionCategory::Forbidden => 403,
            RejectionCategory::NotFound => 404,
            RejectionCategory::Conflict => 409,
            RejectionCategory::BusinessRule => 400,
        };
    }
}
