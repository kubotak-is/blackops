# FrankenPHP Reference Runtime

The reference HTTP runtime uses the official `dunglas/frankenphp:1-php8.5-trixie` Debian image. It is a separate image from the CLI and test `app` service so FrankenPHP's thread-safe PHP runtime and Caddy process do not change the established test workflow.

The image installs PCNTL, PDO PostgreSQL, and Zip through the base image's `install-php-extensions` helper. Composer dependencies are installed without an autoloader first; framework source is then copied and an authoritative production autoloader is generated. Production startup never discovers operations or compiles artifacts.

The tag pattern, Debian recommendation, PHP 8.5 availability, and extension helper follow the [official FrankenPHP Docker guide](https://frankenphp.dev/docs/docker/). The standalone framework reference starts in classic mode, the safe migration baseline described by the [official migration guide](https://frankenphp.dev/docs/migrate/). The installed Quickstart uses Worker Mode by default and keeps Classic Mode as an explicit fallback profile.

## Runtime flow

```text
FrankenPHP/Caddy :80
  -> application public/index.php or worker.php
  -> BlackOps\Http\SapiRuntime
  -> SuperglobalServerRequestFactory
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

## Quickstart Worker Mode

`BlackOps\Http\SapiRuntime` owns request creation, response emission, the fixed safe 500 boundary, and the FrankenPHP callback loop. Public entrypoints load the application and call `SapiRuntime::run()` (Classic) or `SapiRuntime::runWorker()` (Worker); they do not import PSR-17, Laminas, or FrankenPHP APIs. The request callback catches `Throwable` inside the callback so one failed request does not terminate the worker. This follows the [official Worker Mode lifecycle](https://frankenphp.dev/docs/worker/).

`ApplicationHttpRequestHandler` owns the reusable runtime's request boundary:

1. execute `SELECT 1` before handling; on failure close the DBAL connection and retry once
2. invoke the operation HTTP handler
3. close a connection after `Throwable`
4. close and reject a nominally successful response if a transaction remains active
5. verify that the operation scope is empty
6. flush the application journal observers

The execution scope itself uses `finally`, so completed, rejected, and thrown operation paths all pop their envelope and operation type. Application observers share an aggregator with the execution pipeline; the request boundary calls that aggregator's flush operation rather than maintaining a second buffer.

FrankenPHP resets request superglobals after the callback, but its documentation explicitly excludes `$_ENV`. The entrypoint snapshots `$_ENV` after process bootstrap and restores it after every callback. Application services must still be stateless with respect to request values: request bodies, actors, tenants, and PSR-7 objects belong in local variables, operation values, or execution context, not singleton or static properties.

The default `Caddyfile` sets one worker thread for deterministic local behavior and delegates restart to FrankenPHP's global `max_requests` option. `FRANKENPHP_MAX_REQUESTS` defaults to 1000. Reaching that limit restarts the worker thread and therefore repeats process bootstrap; it is a bounded lifecycle, not a substitute for request cleanup. `Caddyfile.classic` contains the request-per-bootstrap fallback. See the [official configuration reference](https://frankenphp.dev/docs/config/).

The default `http` service runs Worker Mode on port 8080. The `classic-mode` profile exposes `http-classic` on port 8081 as an explicit fallback and is absent from the default service set. The Consumer E2E runs all boot, flush, rejection, reconnection, isolation, memory, and `max_requests` evidence against the default service, then starts Classic Mode and verifies a Welcome response.
