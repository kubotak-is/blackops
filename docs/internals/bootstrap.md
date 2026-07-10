# Bootstrap

This document describes the current internal bootstrap shape for build-time commands and production artifact loading.

BlackOps keeps build-time discovery and production runtime startup as separate steps:

1. Build commands read explicit provider configuration and Composer metadata.
2. Build commands write generated PHP artifacts.
3. Production runtime code loads generated artifacts and fails if they are missing or invalid.

Production startup does not scan application source files or rebuild artifacts.

## Command Registration

Applications register BlackOps commands in their own console entrypoint. The framework supplies command classes, but it does not own the application console process.

The main build command is:

```php
use BlackOps\Internal\Console\CompileBuildArtifactsCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new CompileBuildArtifactsCommand());
$application->run();
```

The command class is internal. It is intended for framework-managed bootstrap and project build scripts, not as a long-term public extension contract.

The current command set is:

| Command | Class | Responsibility |
| --- | --- | --- |
| `blackops:build:compile` | `BlackOps\Internal\Console\CompileBuildArtifactsCommand` | Compile operation manifest, HTTP manifest, and runtime container together. |
| `blackops:operation:list` | `BlackOps\Internal\Console\ListOperationsCommand` | Discover development operations and list their type ID, definition, and execution strategy. |
| `blackops:operation-manifest:compile` | `BlackOps\Internal\Console\CompileOperationManifestCommand` | Compile the operation manifest from explicit providers and optional development discovery. |
| `blackops:http-manifest:compile` | `BlackOps\Internal\Console\CompileHttpManifestCommand` | Compile the HTTP manifest from explicit providers and optional development discovery. |
| `blackops:container:compile` | `BlackOps\Internal\Console\CompileRuntimeContainerCommand` | Compile only the runtime container from explicit service provider config. |
| `blackops:http-manifest:dump` | `BlackOps\Http\Console\DumpHttpManifestCommand` | Dump HTTP route metadata from an already-built operation registry and operation definitions. |
| `blackops:retention:plan` | `BlackOps\Internal\Console\RetentionPlanCommand` | Print a retention purge plan without applying it. |
| `blackops:retention:purge` | `BlackOps\Internal\Console\RetentionPurgeCommand` | Dry-run or apply retention purge through injected services. |
| `blackops:scheduler:run` | `BlackOps\Internal\Console\SchedulerRunCommand` | Run registered maintenance tasks once and exit. |
| `blackops:scheduler:daemon` | `BlackOps\Internal\Console\SchedulerDaemonCommand` | Run registered maintenance tasks repeatedly with an explicit interval. |

For normal build pipelines, prefer the unified build command so operation metadata, HTTP route metadata, and container definitions are generated from the same provider set.

## Development Discovery Commands

The operation list and standalone operation/HTTP manifest commands can discover application operations directly from source. Register the commands in the application console entrypoint:

```php
use BlackOps\Internal\Console\CompileHttpManifestCommand;
use BlackOps\Internal\Console\CompileOperationManifestCommand;
use BlackOps\Internal\Console\ListOperationsCommand;

$application->add(new ListOperationsCommand());
$application->add(new CompileOperationManifestCommand());
$application->add(new CompileHttpManifestCommand());
```

All development discovery inputs are explicit:

- `--discovery-root` is repeatable and limits source scanning to application-owned roots.
- `--composer-base` resolves relative paths returned by Composer metadata.
- `--composer-psr4` points to a generated PHP file returning the Composer PSR-4 array.
- `--composer-classmap` points to a generated PHP file returning the Composer classmap array.

The PSR-4 and classmap files must return arrays. When any discovery option is supplied, all metadata options and at least one root are required. The list command always requires the complete discovery input:

```bash
php bin/console blackops:operation:list \
  --discovery-root=src \
  --composer-base=. \
  --composer-psr4=vendor/composer/autoload_psr4.php \
  --composer-classmap=vendor/composer/autoload_classmap.php
```

The standalone compile commands retain their provider-only invocation. Supplying the same four discovery options merges source definitions with the explicit providers:

```bash
php bin/console blackops:operation-manifest:compile \
  config/blackops/operations.php \
  var/cache/blackops/operations.php \
  --application-build-id=development-local \
  --discovery-root=src \
  --composer-base=. \
  --composer-psr4=vendor/composer/autoload_psr4.php \
  --composer-classmap=vendor/composer/autoload_classmap.php

php bin/console blackops:http-manifest:compile \
  config/blackops/operations.php \
  var/cache/blackops/http.php \
  --application-build-id=development-local \
  --discovery-root=src \
  --composer-base=. \
  --composer-psr4=vendor/composer/autoload_psr4.php \
  --composer-classmap=vendor/composer/autoload_classmap.php
```

An exact definition returned by both a provider and discovery is compiled once. Different definitions with the same type ID remain an error, as do invalid operation attributes. Operation list output is sorted by type ID.

Dynamic discovery is intentionally absent from `blackops:build:compile`. The unified build command and production runtime continue to use explicit provider inputs and generated artifacts only; neither falls back to source scanning.

## Provider Inputs

The unified build command requires two explicit config files:

- operation provider config
- service provider config

Each config file can return a provider instance, a list of provider instances, or a list of provider class names that can be instantiated without constructor arguments.

The command can also read provider class names from Composer metadata:

- root Composer metadata via `--composer-metadata`
- installed package metadata via `--installed-composer-metadata`

Composer provider metadata uses:

```json
{
  "extra": {
    "blackops": {
      "operation-providers": [
        "Vendor\\Package\\ExampleOperationProvider"
      ],
      "service-providers": [
        "Vendor\\Package\\ExampleServiceProvider"
      ]
    }
  }
}
```

Installed package metadata is read from the same package-level `extra.blackops` shape. Both the Composer package list wrapped in a `packages` key and the older root package-list shape are supported.

Composer discovery returns class names only. Provider instantiation stays in the existing provider config loading boundary.

## Build Artifact Compilation

The unified build command has this shape:

```bash
blackops:build:compile \
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

The positional outputs are:

- operation manifest PHP file
- HTTP manifest PHP file
- dumped runtime container PHP file

`--application-build-id` is required. Build pipelines should supply an immutable identifier for the application
release, such as a source revision or deployment build number. The command writes the exact same identifier to the
operation and HTTP manifests. Changing the requested build ID also invalidates a matching development fingerprint,
so an earlier release's manifests are not reused.

Each manifest is a PHP array with a versioned envelope:

```php
return [
    'schemaVersion' => 1,
    'applicationBuildId' => 'release-2026-07-11.1',
    'payload' => [
        // Operation or HTTP metadata.
    ],
];
```

The generated artifacts contain scalar values, arrays, and class names. They must not contain credentials, tokens, environment secrets, closures, or live service instances.

## Locking and Fingerprint

The `--lock` option guards a build output set with a local lock file. Use it when multiple build processes could write the same artifacts.

The `--fingerprint` option stores a lightweight hash for build inputs. When the fingerprint still matches and all output artifacts exist, the command skips regeneration.

The default fingerprint input set includes:

- operation provider config file
- service provider config file
- root Composer metadata file, when provided
- installed Composer metadata file, when provided

Additional files can be included through `--fingerprint-input`. Multiple paths are separated by `PATH_SEPARATOR`.

Use fingerprinting for development speed. CI and production build pipelines should still be able to force a full rebuild by deleting the fingerprint file or using a clean workspace.

## Production Artifact Loading

Production runtime code loads generated artifacts through the internal production runtime artifact loader:

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

The loader returns:

- operation registry
- HTTP operation manifest
- PSR-11 container

Startup fails before the generated container is loaded when either manifest is missing, does not use the supported
schema version, has an empty application build ID, has an invalid payload, or the operation and HTTP application build
IDs differ. Production startup does not fall back to Composer discovery, operation scanning, token scanning, or
container compilation.

## Boundary Notes

The bootstrap layer is a composition root. It may work with the container, generated manifests, provider configs, and build artifacts.

Handlers, operation envelopes, operation values, and domain services must not receive the container as a dependency. Handlers should receive their own constructor dependencies from the compiled container.

The production runtime composer can take loaded artifacts plus runtime dependencies and build the current HTTP execution boundary:

- HTTP route registry
- inline dispatcher
- HTTP request handler

The application must still provide runtime resources such as the clock, canonical journal writer, response factory, and stream factory. The composer uses the generated container to resolve operation handlers, but it does not pass the container into handlers, envelopes, values, or domain services.

The current composition wrapper is still internal. It does not create a complete front controller, choose a transport adapter, create database connections, or load environment variables.
