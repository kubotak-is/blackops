<?php

declare(strict_types=1);

namespace BlackOps\Http\Responder;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Core\Validation\Violation;
use BlackOps\Http\Routing\HttpOperationRoute;
use BlackOps\Http\Status\OperationStatusHttpContract;
use BlackOps\Internal\Idempotency\IdempotencyResponseSnapshot;
use BlackOps\Outcome\Internal\StructuredOutcomeNormalizer;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use stdClass;

/**
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class JsonOperationResponder
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private TimeCodec $time = new TimeCodec(),
        private StructuredOutcomeNormalizer $outcomes = new StructuredOutcomeNormalizer(),
    ) {}

    public function respond(OperationResult $result): ResponseInterface
    {
        if ($result->isRejected()) {
            $reason = $result->rejectionReason();
            $operationId = $result->operationId();

            $response = $this->json($this->statusFor($reason->category()), [
                'status' => 'rejected',
                ...($operationId === null ? [] : ['operationId' => $operationId->toString()]),
                'category' => $reason->category()->value,
                'code' => $reason->code(),
            ]);

            return $result->isReplayed() ? $this->replayHeaders($response) : $response;
        }

        $outcome = $result->outcome();

        if ($outcome instanceof EmptyOutcome) {
            $response = $this->responses->createResponse(204);

            return $result->isReplayed() ? $this->replayHeaders($response) : $response;
        }

        $payload = $this->outcomes->normalize($outcome);

        $response = $this->json(200, $payload === [] ? new stdClass() : $payload);

        return $result->isReplayed() ? $this->replayHeaders($response) : $response;
    }

    public function respondForRoute(OperationResult $result, HttpOperationRoute $route): ResponseInterface
    {
        if ($result->isRejected() || $route->outcome === null || $route->ephemeral === null) {
            return $this->respond($result);
        }

        $outcome = $result->outcome();
        $actualEphemeral = $outcome instanceof EphemeralOutcome;
        if ($outcome::class !== $route->outcome || $actualEphemeral !== $route->ephemeral) {
            throw new RuntimeException('Operation outcome does not match the HTTP manifest contract.');
        }

        return $this->respond($result);
    }

    public function respondAcknowledgement(DeferredAcknowledgement $acknowledgement): ResponseInterface
    {
        $response = $this
            ->json(202, [
                'status' => 'accepted',
                'operationId' => $acknowledgement->operationId()->toString(),
                'acceptedAt' => $this->time->format($acknowledgement->acceptedAt()),
            ])
            ->withHeader('Location', '/operations/' . $acknowledgement->operationId()->toString())
            ->withHeader('Retry-After', (string) OperationStatusHttpContract::POLLING_HINT_SECONDS)
            ->withHeader('Cache-Control', OperationStatusHttpContract::CACHE_CONTROL);

        return $acknowledgement->isReplayed() ? $this->replayHeaders($response) : $response;
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

    public function snapshot(ResponseInterface $response): IdempotencyResponseSnapshot
    {
        $headers = [];
        foreach (['content-type', 'location', 'retry-after'] as $name) {
            if (!$response->hasHeader($name)) {
                continue;
            }
            $headers[$name] = $response->getHeaderLine($name);
        }

        $body = (string) $response->getBody();
        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        return new IdempotencyResponseSnapshot(
            IdempotencyResponseSnapshot::VERSION,
            $response->getStatusCode(),
            $headers,
            $body,
        );
    }

    public function respondSnapshot(IdempotencyResponseSnapshot $snapshot): ResponseInterface
    {
        $response = $this->responses->createResponse($snapshot->status());
        foreach ($snapshot->headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $this->replayHeaders($response->withBody($this->streams->createStream($snapshot->body())));
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
     * @param array<string, mixed>|stdClass $payload
     */
    private function json(int $status, array|stdClass $payload): ResponseInterface
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

    private function replayHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Idempotency-Replayed', 'true')->withHeader('Cache-Control', 'private, no-store');
    }
}
