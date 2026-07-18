<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

use Closure;
use RuntimeException;

final readonly class OperationViewerTokens
{
    public const COOKIE = 'blackops_viewer_session';

    private function __construct(
        private string $bootstrap,
        private string $session,
    ) {}

    /** @param null|Closure(int): string $random */
    public static function generate(?Closure $random = null): self
    {
        $random ??= random_bytes(...);
        $bootstrap = $random(32);
        $session = $random(32);
        if (strlen($bootstrap) !== 32 || strlen($session) !== 32 || $bootstrap === $session) {
            throw new RuntimeException('Viewer token generation failed.');
        }

        return new self(bin2hex($bootstrap), bin2hex($session));
    }

    public function bootstrapUrl(string $authority): string
    {
        return sprintf('http://%s/?token=%s', $authority, $this->bootstrap);
    }

    public function acceptsBootstrap(string $candidate): bool
    {
        return hash_equals($this->bootstrap, $candidate);
    }

    public function acceptsSession(string $candidate): bool
    {
        return hash_equals($this->session, $candidate);
    }

    public function sessionCookie(): string
    {
        return sprintf('%s=%s; Path=/; HttpOnly; SameSite=Strict', self::COOKIE, $this->session);
    }
}
