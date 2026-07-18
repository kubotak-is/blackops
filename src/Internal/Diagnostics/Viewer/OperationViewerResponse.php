<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

final readonly class OperationViewerResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function toHttp(): string
    {
        return $this->render($this->body);
    }

    public function toHeadHttp(): string
    {
        return $this->render('');
    }

    private function render(string $body): string
    {
        $reason = match ($this->status) {
            200 => 'OK',
            303 => 'See Other',
            400 => 'Bad Request',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            default => 'Error',
        };
        $headers = [
            ...$this->headers,
            'Content-Length' => (string) strlen($this->body),
            'Connection' => 'close',
        ];
        $lines = [sprintf('HTTP/1.1 %d %s', $this->status, $reason)];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines) . "\r\n\r\n" . $body;
    }
}
