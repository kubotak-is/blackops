# Runtime Bootstrap

This guide shows the current Phase 1 path for building and running an HTTP inline BlackOps application.

The Phase 1 runtime supports:

- operation metadata compiled into generated PHP artifacts
- HTTP route metadata and FastRoute Dispatcher Data compiled into generated PHP artifacts
- a compiled PSR-11 runtime container
- inline dispatch with lifecycle journal records
- production startup from generated artifacts

The Phase 1 runtime does not yet provide:

- deferred operation acceptance
- worker execution
- retry, lease, heartbeat, or dead letter handling
- retention purge and hold workflows
- a generated front controller

Applications still own their HTTP server entrypoint, database connection setup, environment loading, and deployment layout.

BlackOpsのMVPおよび公式Reference EnvironmentはFrankenPHPを前提にする。Core APIはPSR境界を維持するため、FrankenPHP固有のServer設定やWorker設定はBootstrap / Adapter層へ閉じ込める。

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

These options are for development tooling. Production build pipelines should use `blackops:build:compile`, whose inputs remain provider-based and which does not dynamically scan source. Production startup loads generated artifacts and never falls back to discovery.

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

## Load Production Artifacts

Production runtime should load generated artifacts. It should not scan source files or rebuild artifacts during request handling.

```php
use BlackOps\Internal\Runtime\ProductionRuntimeArtifactLoader;

$artifacts = new ProductionRuntimeArtifactLoader()->load(
    operationManifest: __DIR__ . '/../var/cache/blackops/operations.php',
    httpManifest: __DIR__ . '/../var/cache/blackops/http.php',
    container: __DIR__ . '/../var/cache/blackops/container.php',
    containerClass: 'CompiledContainer',
    containerNamespace: 'App\\BlackOps\\Generated',
);
```

Startup fails if a manifest is missing, uses an unsupported or missing schema version, has a missing or empty build ID,
contains an invalid payload, has missing or malformed HTTP Dispatcher Data, or belongs to a different application build
than the other manifest. These checks happen before the generated container is loaded. Runtime startup never falls back
to scanning application classes, compiling routes, or rebuilding artifacts.

## Compose the HTTP Runtime

The current runtime composer builds the HTTP inline execution boundary from loaded artifacts and application-owned runtime resources.

```php
use BlackOps\Internal\Runtime\ProductionRuntimeComposer;
use Nyholm\Psr7\Factory\Psr17Factory;

$psr17 = new Psr17Factory();

$runtime = new ProductionRuntimeComposer()->compose(
    artifacts: $artifacts,
    clock: $clock,
    journal: $journal,
    responses: $psr17,
    streams: $psr17,
);
```

The application provides:

- a PSR-20 clock
- a canonical journal writer
- a PSR-17 response factory
- a PSR-17 stream factory

The runtime exposes:

- HTTP route registry
- inline dispatcher
- HTTP request handler

```php
$response = $runtime->httpHandler->handle($serverRequest);
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

## Next Capabilities

The next runtime capabilities after Phase 1 are deferred operation acceptance, worker execution, retry and recovery behavior, and retention workflows. Those are not available from the Phase 1 HTTP inline runtime.
