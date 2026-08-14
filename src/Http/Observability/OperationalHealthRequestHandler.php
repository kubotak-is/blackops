<?php

declare(strict_types=1);

namespace BlackOps\Http\Observability;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Observability\OperationalHealthCheck;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use BlackOps\Observability\OperationalHealthReport;
use BlackOps\Observability\OperationalHealthStatus;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[PublicApi]
final readonly class OperationalHealthRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private OperationalHealthQuery $query,
        private OperationalHealthKind $kind,
        private OperationalHealthJsonResponder $responder,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || trim((string) $request->getBody()) !== '') {
            return $this->responder->methodNotAllowed();
        }

        try {
            $report = $this->query->check($this->kind);
        } catch (\Throwable) {
            $report = new OperationalHealthReport(
                $this->kind,
                OperationalHealthStatus::Fail,
                new DateTimeImmutable('now'),
                [OperationalHealthCheck::fail('query_failed')],
            );
        }

        return $this->responder->respond($report);
    }
}
