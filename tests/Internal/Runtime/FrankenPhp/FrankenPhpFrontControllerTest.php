<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Runtime\FrankenPhp;

use BlackOps\Internal\Runtime\FrankenPhp\FrankenPhpFrontController;
use BlackOps\Internal\Runtime\FrankenPhp\SapiResponseEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class FrankenPhpFrontControllerTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testRunsApplicationBootstrapHandlerAndEmitsResponse(): void
    {
        $path = $this->bootstrap('<?php return new \\' . ReferenceRequestHandler::class . '();');
        $status = null;
        $body = '';
        $emitter = new SapiResponseEmitter(
            static function (int $value) use (&$status): void {
                $status = $value;
            },
            static function (string $header): void {},
            static function (string $chunk) use (&$body): void {
                $body .= $chunk;
            },
        );
        $factory = new Psr17Factory();

        new FrankenPhpFrontController($emitter)->run($path, $factory->createServerRequest('GET', '/healthz'));

        self::assertSame(200, $status);
        self::assertSame('{"status":"ok"}', $body);
    }

    public function testRejectsUnreadableBootstrapPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not readable');

        new FrankenPhpFrontController(new SapiResponseEmitter())->run(
            sys_get_temp_dir() . '/blackops-missing-bootstrap-' . bin2hex(random_bytes(8)),
            new Psr17Factory()->createServerRequest('GET', '/'),
        );
    }

    public function testRejectsBootstrapThatDoesNotReturnRequestHandler(): void
    {
        $path = $this->bootstrap('<?php return new \\stdClass();');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PSR-15 request handler');

        new FrankenPhpFrontController(new SapiResponseEmitter())->run($path, new Psr17Factory()->createServerRequest(
            'GET',
            '/',
        ));
    }

    private function bootstrap(string $contents): string
    {
        $path = sys_get_temp_dir() . '/blackops-frankenphp-bootstrap-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($path, $contents);
        $this->paths[] = $path;

        return $path;
    }
}

final readonly class ReferenceRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}');
    }
}
