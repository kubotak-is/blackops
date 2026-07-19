<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final readonly class JsonResponse
{
    /** @param array<string, mixed> $payload */
    public static function create(int $status, array $payload = [], array $headers = []): ResponseInterface
    {
        $headers = array_merge([
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
        ], $headers);

        if ($status === 204) {
            return new Response($status, $headers);
        }

        $headers['Content-Type'] = 'application/json';

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $status = 500;
            $body = '{"status":"error","code":"identity.internal_error"}';
        }

        return new Response($status, $headers, $body);
    }

    public static function error(int $status, string $code, array $extra = [], array $headers = []): ResponseInterface
    {
        return self::create($status, array_merge(['status' => 'error', 'code' => $code], $extra), $headers);
    }
}
