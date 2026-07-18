<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Diagnostics\Viewer;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsException;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsFound;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsResult;
use BlackOps\Internal\Diagnostics\OperationDiagnosticsUnavailable;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRequest;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerResponse;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerRouter;
use BlackOps\Internal\Diagnostics\Viewer\OperationViewerTokens;
use BlackOps\Tests\Internal\Console\OperationInspectFixture;
use PHPUnit\Framework\TestCase;

final class OperationViewerRouterTest extends TestCase
{
    public function testBootstrapCreatesSessionWithoutReflectingToken(): void
    {
        [$router, $bootstrap] = $this->router();
        $response = $router->route($this->request('/?token=' . $bootstrap));

        self::assertSame(303, $response->status);
        self::assertSame('/', $response->headers['Location']);
        self::assertStringContainsString('HttpOnly; SameSite=Strict', $response->headers['Set-Cookie']);
        self::assertStringNotContainsString($bootstrap, $response->body . $response->headers['Location']);
    }

    public function testSessionCanOpenFormRedirectAndFoundPageWithSafeHtml(): void
    {
        [$router, $bootstrap] = $this->router(found: true);
        $bootstrapResponse = $router->route($this->request('/?token=' . $bootstrap));
        $cookie = explode(';', $bootstrapResponse->headers['Set-Cookie'])[0];
        $form = $router->route($this->request('/', cookie: $cookie));
        self::assertSame(200, $form->status);
        self::assertStringContainsString('name="operationId"', $form->body);
        self::assertStringNotContainsString($bootstrap, $form->body);

        $redirect = $router->route($this->request(
            '/?operationId=' . OperationInspectFixture::OPERATION_ID,
            cookie: $cookie,
        ));
        self::assertSame('/operations/' . OperationInspectFixture::OPERATION_ID, $redirect->headers['Location']);

        $found = $router->route($this->request($redirect->headers['Location'], cookie: $cookie));
        self::assertSame(200, $found->status);
        foreach (['Summary', 'Availability', 'Actors', 'Timeline', 'Attempts', 'Outcome'] as $section) {
            self::assertStringContainsString($section, $found->body);
        }
        self::assertStringContainsString('[masked]', $found->body);
        self::assertStringNotContainsString('console-reports@example.com', $found->body);
        self::assertStringNotContainsString("\x1b", $found->body);
        self::assertStringContainsString('\\u001b', $found->body);
        $this->assertSecurityHeaders($found);
    }

    public function testUnauthorizedUnknownInvalidAndUnavailableShareNotFoundShape(): void
    {
        [$router, $bootstrap] = $this->router();
        $bootstrapResponse = $router->route($this->request('/?token=' . $bootstrap));
        $cookie = explode(';', $bootstrapResponse->headers['Set-Cookie'])[0];
        $responses = [
            $router->route($this->request('/')),
            $router->route($this->request('/?token=wrong')),
            $router->route($this->request('/?token=wrong&token=' . $bootstrap)),
            $router->route($this->request('/unknown', cookie: $cookie)),
            $router->route($this->request('/', cookie: $cookie . '; ' . $cookie)),
            $router->route($this->request('/operations/invalid', cookie: $cookie)),
            $router->route($this->request('/operations/' . OperationInspectFixture::OPERATION_ID, cookie: $cookie)),
            $router->route($this->request('/', host: 'localhost:8082', cookie: $cookie)),
        ];
        foreach ($responses as $response) {
            self::assertSame(404, $response->status);
            self::assertSame($responses[0]->body, $response->body);
            $this->assertSecurityHeaders($response);
        }
    }

    public function testMethodHeadAndDiagnosticsFailureContracts(): void
    {
        [$router, $bootstrap] = $this->router(failure: true);
        $bootstrapResponse = $router->route($this->request('/?token=' . $bootstrap));
        $cookie = explode(';', $bootstrapResponse->headers['Set-Cookie'])[0];
        $method = $router->route($this->request('/', method: 'POST', cookie: $cookie));
        self::assertSame(405, $method->status);
        self::assertSame('GET, HEAD', $method->headers['Allow']);

        $get = $router->route($this->request('/', cookie: $cookie));
        $head = $router->route($this->request('/', method: 'HEAD', cookie: $cookie));
        self::assertSame($get->status, $head->status);
        self::assertSame($get->headers, $head->headers);
        $headHttp = $head->toHeadHttp();
        $separator = strpos($headHttp, "\r\n\r\n");
        self::assertIsInt($separator);
        self::assertSame('', substr($headHttp, $separator + 4));

        $failure = $router->route($this->request(
            '/operations/' . OperationInspectFixture::OPERATION_ID,
            cookie: $cookie,
        ));
        self::assertSame(500, $failure->status);
        self::assertStringNotContainsString('SQL secret', $failure->body);
    }

    /** @return array{OperationViewerRouter, string} */
    private function router(bool $found = false, bool $failure = false): array
    {
        $call = 0;
        $tokens = OperationViewerTokens::generate(static function (int $bytes) use (&$call): string {
            ++$call;

            return str_repeat(chr($call), $bytes);
        });
        $bootstrap = str_repeat('01', 32);
        $finder = static function (OperationId $id) use ($found, $failure): OperationDiagnosticsResult {
            if ($failure) {
                throw OperationDiagnosticsException::storageFailed();
            }

            return $found
                ? new OperationDiagnosticsFound(OperationInspectFixture::diagnosticsWithControlCharacters())
                : new OperationDiagnosticsUnavailable();
        };

        return [new OperationViewerRouter('127.0.0.1:8082', $tokens, $finder), $bootstrap];
    }

    private function request(
        string $target,
        string $method = 'GET',
        string $host = '127.0.0.1:8082',
        ?string $cookie = null,
    ): OperationViewerRequest {
        return new OperationViewerRequest($method, $target, 'HTTP/1.1', [
            'host' => $host,
            ...($cookie === null ? [] : ['cookie' => $cookie]),
        ]);
    }

    private function assertSecurityHeaders(OperationViewerResponse $response): void
    {
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertSame('no-referrer', $response->headers['Referrer-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('DENY', $response->headers['X-Frame-Options']);
        self::assertStringContainsString("default-src 'none'", $response->headers['Content-Security-Policy']);
    }
}
