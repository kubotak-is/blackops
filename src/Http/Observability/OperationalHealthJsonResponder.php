<?php

declare(strict_types=1);

namespace BlackOps\Http\Observability;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Observability\OperationalHealthReport;
use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

#[PublicApi]
final readonly class OperationalHealthJsonResponder
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function respond(OperationalHealthReport $report): ResponseInterface
    {
        try {
            $body = json_encode($report->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode operational health response.', previous: $exception);
        }

        return $this->responses
            ->createResponse($report->isPassing() ? 200 : 503)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streams->createStream($body));
    }

    public function methodNotAllowed(): ResponseInterface
    {
        return $this->responses
            ->createResponse(405)
            ->withHeader('Allow', 'GET')
            ->withHeader('Cache-Control', 'no-store');
    }
}
