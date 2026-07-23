<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime\FrankenPhp;

use Closure;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SapiResponseEmitter
{
    private Closure $statusEmitter;

    private Closure $headerEmitter;

    private Closure $bodyEmitter;

    public function __construct(
        ?Closure $statusEmitter = null,
        ?Closure $headerEmitter = null,
        ?Closure $bodyEmitter = null,
    ) {
        $this->statusEmitter = $statusEmitter ?? static function (int $status): void {
            $file = '';
            $line = 0;

            if (headers_sent($file, $line)) {
                throw new RuntimeException("HTTP response headers were already sent at {$file}:{$line}.");
            }

            if (http_response_code($status) === false) {
                throw new RuntimeException('Unable to emit the HTTP response status.');
            }
        };
        $this->headerEmitter = $headerEmitter ?? static function (string $header): void {
            header($header, replace: false);
        };
        $this->bodyEmitter = $bodyEmitter ?? static function (string $chunk): void {
            echo $chunk;
        };
    }

    public function emit(ResponseInterface $response, string $requestMethod): void
    {
        SapiResponseHeaders::validate($response);

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                ($this->headerEmitter)((string) $name . ': ' . $value);
            }
        }

        ($this->statusEmitter)($response->getStatusCode());

        if (strtoupper($requestMethod) === 'HEAD') {
            return;
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $chunk = $body->read(8_192);

            if ($chunk === '' && !$body->eof()) {
                throw new RuntimeException('HTTP response body stream made no progress.');
            }

            if ($chunk !== '') {
                ($this->bodyEmitter)($chunk);
            }
        }
    }
}
