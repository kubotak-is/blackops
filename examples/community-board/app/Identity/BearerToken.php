<?php

declare(strict_types=1);

namespace App\Identity;

use Psr\Http\Message\ServerRequestInterface;

final readonly class BearerToken
{
    private function __construct(
        public bool $present,
        public bool $valid,
        public ?string $rawToken,
    ) {}

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $headers = $request->getHeader('Authorization');
        if ($headers === []) {
            return new self(false, true, null);
        }

        if (count($headers) !== 1 || preg_match('/\\ABearer ([A-Za-z0-9_-]{43})\\z/D', $headers[0], $matches) !== 1) {
            return new self(true, false, null);
        }

        return new self(true, true, $matches[1]);
    }
}
