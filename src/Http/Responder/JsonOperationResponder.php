<?php

declare(strict_types=1);

namespace BlackOps\Http\Responder;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Outcome;
use BlackOps\Core\Rejection\RejectionCategory;
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
    ) {}

    public function respond(OperationResult $result): ResponseInterface
    {
        if ($result->isRejected()) {
            $reason = $result->rejectionReason();

            return $this->json($this->statusFor($reason->category()), [
                'status' => 'rejected',
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
