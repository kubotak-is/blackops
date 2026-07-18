<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

use BlackOps\Core\Exception\InvalidIdentifierException;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use Closure;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class OperationViewerRouter
{
    /** @var Closure(OperationId): OperationDiagnosticsResult */
    private Closure $finder;

    /** @param Closure(OperationId): OperationDiagnosticsResult $finder */
    public function __construct(
        private string $authority,
        private OperationViewerTokens $tokens,
        Closure $finder,
        private OperationViewerRenderer $renderer = new OperationViewerRenderer(),
    ) {
        $this->finder = $finder;
    }

    /** @mago-expect lint:halstead */
    public function route(OperationViewerRequest $request): OperationViewerResponse
    {
        if (($request->headers['host'] ?? '') !== $this->authority) {
            return $this->response(404, $this->renderer->notFound());
        }
        if (!in_array($request->method, ['GET', 'HEAD'], strict: true)) {
            return $this->response(405, $this->renderer->methodNotAllowed(), ['Allow' => 'GET, HEAD']);
        }

        $target = parse_url($request->target);
        if ($target === false || !array_key_exists('path', $target)) {
            return $this->response(404, $this->renderer->notFound());
        }
        $path = $target['path'];
        $query = $target['query'] ?? null;
        if ($path === '/' && $query !== null && $this->bootstrap($query)) {
            return $this->response(303, '', [
                'Location' => '/',
                'Set-Cookie' => $this->tokens->sessionCookie(),
            ]);
        }
        if (!$this->hasSession($request)) {
            return $this->response(404, $this->renderer->notFound());
        }
        if ($path === '/' && $query === null) {
            return $this->response(200, $this->renderer->form());
        }
        if ($path === '/') {
            $operationId = $this->parameter($query, 'operationId');
            if ($operationId === null) {
                return $this->response(404, $this->renderer->notFound());
            }
            try {
                $id = OperationId::fromString($operationId);
            } catch (InvalidIdentifierException) {
                return $this->response(404, $this->renderer->notFound());
            }

            return $this->response(303, '', ['Location' => '/operations/' . rawurlencode($id->toString())]);
        }
        $matches = [];
        if (preg_match('#^/operations/([^/]+)$#', $path, $matches) !== 1) {
            return $this->response(404, $this->renderer->notFound());
        }
        try {
            $id = OperationId::fromString($matches[1]);
            $result = ($this->finder)($id);
            if (!$result instanceof OperationDiagnosticsFound) {
                return $this->response(404, $this->renderer->notFound());
            }

            return $this->response(200, $this->renderer->found($result->diagnostics));
        } catch (InvalidIdentifierException) {
            return $this->response(404, $this->renderer->notFound());
        } catch (OperationDiagnosticsException) {
            return $this->response(500, $this->renderer->internalError());
        } catch (Throwable) {
            return $this->response(500, $this->renderer->internalError());
        }
    }

    private function bootstrap(string $query): bool
    {
        $token = $this->parameter($query, 'token');

        return $token !== null && $this->tokens->acceptsBootstrap($token);
    }

    private function hasSession(OperationViewerRequest $request): bool
    {
        $cookie = $request->headers['cookie'] ?? '';
        $candidate = null;
        foreach (explode(';', $cookie) as $entry) {
            $parts = explode(separator: '=', string: trim($entry), limit: 2);
            if (count($parts) === 2 && $parts[0] === OperationViewerTokens::COOKIE) {
                if ($candidate !== null) {
                    return false;
                }
                $candidate = $parts[1];
            }
        }

        return $candidate !== null && $this->tokens->acceptsSession($candidate);
    }

    private function parameter(string $query, string $name): ?string
    {
        $parts = explode(separator: '=', string: $query, limit: 2);
        if (count($parts) !== 2 || $parts[0] !== $name || $parts[1] === '' || str_contains($parts[1], '&')) {
            return null;
        }

        return $parts[1];
    }

    /** @param array<string, string> $headers */
    private function response(int $status, string $body, array $headers = []): OperationViewerResponse
    {
        return new OperationViewerResponse(
            $status,
            [
                'Cache-Control' => 'no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
                'Content-Type' => 'text/html; charset=UTF-8',
                ...$headers,
            ],
            $body,
        );
    }
}
