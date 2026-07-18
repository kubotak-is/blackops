<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics\Viewer;

use BlackOps\Internal\Diagnostics\Viewer\OperationViewerTokens;
use PHPUnit\Framework\TestCase;

final class OperationViewerTokensTest extends TestCase
{
    public function testGeneratesIndependent256BitBootstrapAndSessionTokens(): void
    {
        $call = 0;
        $tokens = OperationViewerTokens::generate(static function (int $bytes) use (&$call): string {
            self::assertSame(32, $bytes);
            ++$call;

            return str_repeat(chr($call), $bytes);
        });

        self::assertTrue($tokens->acceptsBootstrap(str_repeat('01', 32)));
        self::assertFalse($tokens->acceptsBootstrap(str_repeat('02', 32)));
        self::assertTrue($tokens->acceptsSession(str_repeat('02', 32)));
        self::assertFalse($tokens->acceptsSession(str_repeat('01', 32)));
        self::assertSame(
            'blackops_viewer_session=' . str_repeat('02', 32) . '; Path=/; HttpOnly; SameSite=Strict',
            $tokens->sessionCookie(),
        );
    }

    public function testTokensChangeForEachViewerStart(): void
    {
        $first = OperationViewerTokens::generate()->bootstrapUrl('127.0.0.1:8082');
        $second = OperationViewerTokens::generate()->bootstrapUrl('127.0.0.1:8082');

        self::assertNotSame($first, $second);
    }
}
