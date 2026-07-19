<?php

declare(strict_types=1);

namespace BlackOps\Http\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Status\OperationStatusQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final readonly class OperationStatusRequestHandler implements RequestHandlerInterface
{
    private const string PATH_PREFIX = '/operations/';
    private const string PATH_SEPARATOR = '/';

    public function __construct(
        private OperationStatusQuery $query,
        private OperationStatusJsonResponder $responder,
    ) {}

    public function matches(ServerRequestInterface $request): bool
    {
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        $path = $request->getUri()->getPath();

        return (
            str_starts_with($path, self::PATH_PREFIX)
            && !str_contains(substr($path, strlen(self::PATH_PREFIX)), self::PATH_SEPARATOR)
        );
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (trim((string) $request->getBody()) !== '') {
            return $this->responder->protocolError();
        }

        $operationId = $this->operationId($request);
        if ($operationId === null) {
            return $this->responder->unavailable();
        }

        try {
            /** @var mixed $attribute */
            $attribute = $request->getAttribute(ActorRef::class);

            return $this->responder->respond($this->query->find(
                $operationId,
                $attribute instanceof ActorRef ? $attribute : null,
            ));
        } catch (Throwable) {
            return $this->responder->internalError();
        }
    }

    private function operationId(ServerRequestInterface $request): ?OperationId
    {
        if (!$this->matches($request)) {
            return null;
        }

        $value = substr($request->getUri()->getPath(), strlen(self::PATH_PREFIX));
        if ($value === '') {
            return null;
        }

        try {
            $operationId = OperationId::fromString($value);
        } catch (Throwable) {
            return null;
        }

        return $operationId->toString() === $value ? $operationId : null;
    }
}
