# Runtime Bootstrap

This guide shows the current path for building and running an HTTP inline BlackOps application and a deferred worker.

Installed Application向けの公式導線は `examples/quickstart/` のPublic `Application` Bootstrapと薄い `public/index.php`／`bin/blackops` である。Repository内部Toolingの例はFramework実装者向けであり、QuickstartからInternal型を参照しない。

The current runtime supports:

- operation metadata compiled into generated PHP artifacts
- HTTP route metadata and FastRoute Dispatcher Data compiled into generated PHP artifacts
- a compiled PSR-11 runtime container
- inline dispatch with lifecycle journal records
- production startup from generated artifacts

`Application::http()` composes inline and deferred HTTP acceptance from the accepted application configuration. BlackOps provides a reference FrankenPHP front controller, but it does not generate application-specific bootstrap or process-supervisor configuration.

Applications own their bootstrap composition, database connection setup, environment loading, and deployment layout. The framework-owned reference entrypoint only adapts the SAPI request and response around the PSR-15 handler returned by that bootstrap.

BlackOpsのMVPおよび公式Reference EnvironmentはFrankenPHPを前提にする。Core APIはPSR境界を維持するため、FrankenPHP固有のServer設定やWorker設定はBootstrap / Adapter層へ閉じ込める。

## Start the reference HTTP runtime

Build and start the profile-scoped FrankenPHP service:

```bash
docker compose --profile runtime build http
docker compose --profile runtime up -d http
```

The health endpoint is available from the host at `http://localhost:8080/healthz`. Set `BLACKOPS_HTTP_PORT` to change the host port. Stop the reference process after use:

```bash
docker compose stop http
```

The `http` service uses the official FrankenPHP 1 / PHP 8.5 Debian image and runs Caddy on plain HTTP port 80 inside the development network. The host mapping defaults to port 8080. TLS certificates and production domain configuration belong to the deployment environment.

The repository's `runtime/frankenphp/bootstrap.php` returns a minimal PSR-15 health handler. In an application image, set `BLACKOPS_APPLICATION_BOOTSTRAP` to a readable PHP file that returns the application-composed `RequestHandlerInterface`, normally the `httpHandler` from `ProductionRuntimeComposer`. Returning any other value fails startup for that request; the front controller never falls back to discovery or compilation.

The reference starts in FrankenPHP classic mode. If an application later opts into worker mode, it must reset execution scopes, logger state, observer buffers, and database connection state at every request boundary. Worker-mode tuning is outside the MVP reference runtime.

## Define Providers

Create an operation provider config file:

```php
<?php

return [
    App\BlackOps\Operation\AppOperationProvider::class,
];
```

Create a service provider config file:

```php
<?php

return [
    App\BlackOps\DependencyInjection\AppServiceProvider::class,
];
```

Provider config files may return provider instances or provider class names. Provider classes must be instantiable without required constructor arguments.

Packages can also expose providers through Composer metadata:

```json
{
  "extra": {
    "blackops": {
      "operation-providers": [
        "App\\BlackOps\\Operation\\AppOperationProvider"
      ],
      "service-providers": [
        "App\\BlackOps\\DependencyInjection\\AppServiceProvider"
      ]
    }
  }
}
```

When installed package discovery is enabled, BlackOps reads the same package-level metadata from Composer installed package metadata.

## Inspect and Compile Development Operations

During development, source discovery can list operation metadata without adding every application operation to a provider first:

```bash
php bin/console blackops:operation:list \
  --discovery-root=src \
  --composer-base=. \
  --composer-psr4=vendor/composer/autoload_psr4.php \
  --composer-classmap=vendor/composer/autoload_classmap.php
```

The table is sorted by operation type ID and includes the definition and execution strategy classes. Repeat `--discovery-root` when the application owns more than one source root.

The standalone `blackops:operation-manifest:compile` and `blackops:http-manifest:compile` commands accept the same four discovery options in addition to their existing provider config, output, and build ID inputs. Definitions returned by the provider and found in source are merged. The same definition is emitted once; conflicting type IDs and invalid attributes fail compilation.

These options are for standalone development tooling. The Application-aware `blackops:build:compile` and `blackops:operation:list` read the absolute roots in `config/operations.php`, merge optional provider definitions first, and discover source only while the command runs. Production HTTP and Worker startup load generated artifacts and never fall back to discovery.

## Compile Build Artifacts

Run the unified build command from your application console:

```bash
php bin/console blackops:build:compile \
  config/blackops/operations.php \
  config/blackops/services.php \
  var/cache/blackops/operations.php \
  var/cache/blackops/http.php \
  var/cache/blackops/container.php \
  --application-build-id=release-2026-07-11.1 \
  --container-class=CompiledContainer \
  --container-namespace=App\\BlackOps\\Generated \
  --composer-metadata=composer.json \
  --installed-composer-metadata=vendor/composer/installed.json \
  --lock=var/cache/blackops/build.lock \
  --fingerprint=var/cache/blackops/build.fingerprint
```

The command writes:

- operation manifest
- HTTP manifest
- compiled runtime container

`--application-build-id` is required. Pass one immutable release identifier, such as the source revision or CI build
number. The command stores the same value in both manifests. Do not reuse a build ID for artifacts produced from
different application revisions.

Both manifest PHP files return an envelope containing a schema version, the application build ID, and the manifest
payload. The Operation Manifest currently uses Schema Version `1`; the HTTP Manifest uses Schema Version `2` because
its payload includes Compile済みFastRoute Dispatcher Data. Each manifest evolves independently while the shared build ID
proves that both artifacts belong to the same application build.

The generated artifacts are PHP files containing arrays, scalar metadata, class names, FastRoute Dispatcher arrays, and a compiled container class. They contain no FastRoute objects or closures. Do not store credentials, tokens, environment secrets, or live service instances in provider metadata.

Use `--lock` when concurrent build processes could write the same artifact files.

Use `--fingerprint` for faster local rebuilds. Production and CI can force a full rebuild by using a clean workspace or deleting the fingerprint file.

## Configure Production Artifacts

Production runtime loads generated artifacts from application configuration. It does not scan source files or rebuild artifacts during request handling.

```php
return [
    'build' => [
        'operation_manifest' => dirname(__DIR__) . '/var/build/operations.php',
        'http_manifest' => dirname(__DIR__) . '/var/build/http.php',
        'container' => dirname(__DIR__) . '/var/build/container.php',
        'container_class' => 'CompiledContainer',
        'container_namespace' => 'App\\Generated',
    ],
];
```

Startup fails if a manifest is missing, uses an unsupported or missing schema version, has a missing or empty build ID,
contains an invalid payload, has missing or malformed HTTP Dispatcher Data, or belongs to a different application build
than the other manifest. These checks happen before the generated container is loaded. Runtime startup never falls back
to scanning application classes, compiling routes, or rebuilding artifacts.

## Compose the HTTP Runtime

The public Application boundary builds inline dispatch and deferred acceptance from the same loaded artifacts and one shared DBAL connection.

```php
use BlackOps\Application\Application;

$application = Application::configure(dirname(__DIR__))
    ->withConfiguration()
    ->create();

$http = $application->http();
```

`config/database.php` provides resolved DBAL parameters and the framework schema:

- `connection`: DBAL connection parameter array
- `schema`: non-empty PostgreSQL identifier

The framework internally composes:

- the compiled HTTP route registry and container-backed inline dispatcher
- PostgreSQL canonical journal and deferred sender sharing one connection
- transactional deferred acceptance
- system clock, UUIDv7 identifiers, reflection JSON codec, and Nyholm PSR-17 factories

```php
$response = $http->handle($serverRequest);
```

For inline operations, the request handler matches against the Compile済みFastRoute Dispatcher Data, binds the HTTP request and decoded path parameters into an operation value, dispatches the operation, invokes the handler from the compiled container, and records the inline lifecycle journal. Unknown routes and requests using a disallowed method both return HTTP 404 in the current compatibility contract.

## Runtime Boundary

The generated container is used by the framework only at composition and handler resolution boundaries.

Do not pass the container into:

- operation handlers
- operation envelopes
- operation values
- domain services

Handlers should receive their dependencies through constructor injection from the compiled container.

## Run a deferred worker

Use the Public Console Kernel to run the lazily composed worker:

```bash
php bin/blackops blackops:worker:run --idle-sleep-milliseconds=1000
```

The worker recovers one expired attempt before each claim and processes at most one claim at a time. `--iterations=N` limits the loop for smoke tests; omit it for a supervised production process.

The PCNTL heartbeat is installed only around handler execution. Configure a positive heartbeat interval shorter than the claim lease. The `ClaimHeartbeat` adapter must have its own DBAL connection, separate from the connection used for claim, lifecycle, recovery, journal, and settlement work. Reusing the worker connection is unsafe because a signal may interrupt synchronous application code while that connection is already active.

Send `SIGTERM` or `SIGINT` for shutdown. The worker stops taking new claims and lets an active handler finish during its grace period. If heartbeat fails or the grace period expires, the process exits with failure without completing, acknowledging, or releasing that claim; lease expiry and crash recovery make it eligible for supervised recovery.

The reference Docker image includes PCNTL. An application image running this command must also enable PCNTL; otherwise worker construction fails immediately while HTTP and build paths remain unaffected. Detailed composition guidance is in the internal [Deferred Worker Runtime](../internals/worker-runtime.md) document.

## Next Capabilities

Applications can extend the current runtime with deployment-specific process supervision and operational monitoring. These remain application and platform concerns rather than generated framework artifacts.
