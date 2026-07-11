# FrankenPHP Reference Runtime

The reference HTTP runtime uses the official `dunglas/frankenphp:1-php8.5-trixie` Debian image. It is a separate image from the CLI and test `app` service so FrankenPHP's thread-safe PHP runtime and Caddy process do not change the established test workflow.

The image installs PCNTL, PDO PostgreSQL, and Zip through the base image's `install-php-extensions` helper. Composer dependencies are installed without an autoloader first; framework source is then copied and an authoritative production autoloader is generated. Production startup never discovers operations or compiles artifacts.

The tag pattern, Debian recommendation, PHP 8.5 availability, and extension helper follow the [official FrankenPHP Docker guide](https://frankenphp.dev/docs/docker/). The reference starts in classic mode, the safe migration baseline described by the [official migration guide](https://frankenphp.dev/docs/migrate/). Worker mode optimization is intentionally not part of this runtime.

## Runtime flow

```text
FrankenPHP/Caddy :80
  -> runtime/frankenphp/public/index.php
  -> SuperglobalServerRequestFactory
  -> application bootstrap from BLACKOPS_APPLICATION_BOOTSTRAP
  -> PSR-15 RequestHandlerInterface
  -> PSR-7 ResponseInterface
  -> SapiResponseEmitter
```

`SuperglobalServerRequestFactory` depends only on PSR-17 factories. It creates a PSR-7 server request containing the method, URI, server parameters, query parameters, cookies, HTTP and content headers, protocol version, and raw body. HTTPS is derived from the direct SAPI HTTPS indicators, request scheme, or port 443. It does not trust forwarding headers by default.

`FrankenPhpFrontController` requires a readable application bootstrap path. The bootstrap file must return a PSR-15 `RequestHandlerInterface`; any other return value fails before request handling. The handler is application-composed and may be the `httpHandler` produced by `ProductionRuntimeComposer`. The framework container is not passed into handlers.

`SapiResponseEmitter` validates every response header name and value before emitting any status or header. It then emits all header values without collapsing duplicates, followed by the body stream. HEAD requests never emit a body. A stream that returns no bytes without reaching EOF fails instead of spinning forever. SAPI and stream failures propagate to the process boundary.

## Reference composition

The repository bootstrap at `runtime/frankenphp/bootstrap.php` is only a health-check application. Real applications replace `BLACKOPS_APPLICATION_BOOTSTRAP` with a file that loads immutable build artifacts, composes runtime dependencies and returns the PSR-15 handler:

```php
<?php

use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use Nyholm\Psr7\Factory\Psr17Factory;

$artifacts = new ProductionRuntimeArtifactLoader()->load(
    operationManifest: __DIR__ . '/var/cache/blackops/operations.php',
    httpManifest: __DIR__ . '/var/cache/blackops/http.php',
    container: __DIR__ . '/var/cache/blackops/container.php',
    containerClass: 'CompiledContainer',
    containerNamespace: 'App\\BlackOps\\Generated',
);
$psr17 = new Psr17Factory();

return new ProductionRuntimeComposer()->compose(
    artifacts: $artifacts,
    clock: $clock,
    journal: $journal,
    responses: $psr17,
    streams: $psr17,
)->httpHandler;
```

Database credentials are supplied through `POSTGRES_*` environment variables. The reference `http` service waits for the Compose PostgreSQL health check, but the health endpoint itself does not open a database connection.

## Process lifecycle

Classic mode rebuilds application state for each request and is the MVP baseline. If an application later enables FrankenPHP worker mode, it must explicitly close every operation scope and flush or reset scoped loggers, observer buffers, database connections, and other mutable process state after each request. A worker-mode switch requires its own runtime verification; do not assume request-process cleanup.
