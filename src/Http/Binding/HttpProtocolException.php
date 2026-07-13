<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

use RuntimeException;
use Throwable;

final class HttpProtocolException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct('HTTP request body is invalid.', previous: $previous);
    }

    public static function malformedJson(Throwable $previous): self
    {
        return new self('http.malformed_json', $previous);
    }

    public static function nonObjectBody(): self
    {
        return new self('http.body_not_object');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
