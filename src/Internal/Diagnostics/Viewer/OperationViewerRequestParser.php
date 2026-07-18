<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

/**
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class OperationViewerRequestParser
{
    public const HEADER_COUNT_LIMIT = 32;
    public const HEADER_SIZE_LIMIT = 8192;
    public const REQUEST_LINE_LIMIT = 2048;
    public const READ_TIMEOUT_SECONDS = 2;

    /**
     * @param resource $stream
     * @mago-expect lint:halstead
     */
    public function parse(mixed $stream): OperationViewerRequest
    {
        stream_set_timeout($stream, self::READ_TIMEOUT_SECONDS);
        $line = fgets($stream, self::REQUEST_LINE_LIMIT + 2);
        if (!is_string($line) || strlen($line) > self::REQUEST_LINE_LIMIT || !str_ends_with($line, "\r\n")) {
            throw new OperationViewerRequestException('Malformed request line.');
        }
        $parts = explode(' ', substr(string: $line, offset: 0, length: -2));
        if (count($parts) !== 3 || !in_array($parts[2], ['HTTP/1.0', 'HTTP/1.1'], strict: true)) {
            throw new OperationViewerRequestException('Malformed request line.');
        }
        [$method, $target, $protocol] = $parts;
        if ($method === '' || !str_starts_with($target, '/') || str_contains($target, '#')) {
            throw new OperationViewerRequestException('Malformed request target.');
        }

        $headers = [];
        $size = strlen($line);
        while (true) {
            $header = fgets($stream, self::HEADER_SIZE_LIMIT + 2);
            if (!is_string($header) || !str_ends_with($header, "\r\n")) {
                throw new OperationViewerRequestException('Malformed request header.');
            }
            $size += strlen($header);
            if ($size > self::HEADER_SIZE_LIMIT) {
                throw new OperationViewerRequestException('Request headers are too large.');
            }
            if ($header === "\r\n") {
                break;
            }
            if (
                count($headers) >= self::HEADER_COUNT_LIMIT
                || str_starts_with($header, ' ')
                || str_starts_with($header, "\t")
            ) {
                throw new OperationViewerRequestException('Invalid request headers.');
            }
            $separator = strpos(haystack: $header, needle: ':');
            if ($separator === false) {
                throw new OperationViewerRequestException('Invalid request header.');
            }
            $name = strtolower(substr(string: $header, offset: 0, length: $separator));
            $value = trim(substr($header, $separator + 1));
            if ($name === '' || preg_match('/^[a-z0-9-]+$/', $name) !== 1 || array_key_exists($name, $headers)) {
                throw new OperationViewerRequestException('Invalid request header.');
            }
            $headers[$name] = $value;
        }

        if (
            ($headers['content-length'] ?? '0') !== '0'
            || array_key_exists('transfer-encoding', $headers)
            || array_key_exists('upgrade', $headers)
        ) {
            throw new OperationViewerRequestException('Request bodies and protocol upgrades are not supported.');
        }
        if ($protocol === 'HTTP/1.1' && !array_key_exists('host', $headers)) {
            throw new OperationViewerRequestException('Host header is required.');
        }

        return new OperationViewerRequest($method, $target, $protocol, $headers);
    }
}
