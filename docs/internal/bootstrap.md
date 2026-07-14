# Bootstrap

This document describes the current internal bootstrap shape for build-time commands and production artifact loading.

BlackOps keeps build-time discovery and production runtime startup as separate steps:

1. Build commands read explicit provider configuration and Composer metadata.
2. Build commands write generated PHP artifacts.
3. Production runtime code loads generated artifacts and fails if they are missing or invalid.

Production startup does not scan application source files or rebuild artifacts.

Build-time metadata compilation validates Typed Self-handled `handle` signatures and records whether invocation uses only the declared Value or the Value plus `ExecutionContext`. Production manifest loading revalidates that recorded contract; HTTP and Worker Runtime use it directly without source discovery.

`examples/quickstart/` は独立 `blackops/skeleton` Composer Metadataと `App\` PSR-4を所有する。Root Framework ComposerのDev AutoloadへApplication Namespaceを追加せず、Repository Integration TestはQuickstart Sourceを明示的に読み込む。

The reference HTTP entrypoint is documented in [FrankenPHP Reference Runtime](frankenphp-runtime.md). It loads an application-owned bootstrap that returns a PSR-15 handler; it does not compile or discover application code at request time.

## Command Registration

Installed Applications obtain the framework-owned command set through `$application->console()`. The project entrypoint owns only autoloading, bootstrap loading, and returning the kernel exit code.

The main build command is:

```php
exit($application->console()->run());
```

Command classes remain internal. Lazy descriptors keep names and help available without constructing Runtime dependencies.

The current command set is:

| Command | Class | Responsibility |
| --- | --- | --- |
| `build:compile` | `BlackOps\Internal\Console\ApplicationBuildCompileCommand` | Compile operation manifest, HTTP manifest, and runtime container together. |
| `operation:list` | `BlackOps\Internal\Console\ApplicationOperationListCommand` | Discover application operations and list their type ID, definition, and execution strategy. |
| `blackops:operation-manifest:compile` | `BlackOps\Internal\Console\CompileOperationManifestCommand` | Compile the operation manifest from explicit providers and optional development discovery. |
| `blackops:http-manifest:compile` | `BlackOps\Internal\Console\CompileHttpManifestCommand` | Compile the HTTP manifest from explicit providers and optional development discovery. |
| `blackops:container:compile` | `BlackOps\Internal\Console\CompileRuntimeContainerCommand` | Compile only the runtime container from explicit service provider config. |
| `blackops:http-manifest:dump` | `BlackOps\Http\Console\DumpHttpManifestCommand` | Dump HTTP route metadata from an already-built operation registry and operation definitions. |
| `retention:plan` | `BlackOps\Internal\Console\RetentionPlanCommand` | Print a retention purge plan without applying it. |
| `retention:purge` | `BlackOps\Internal\Console\RetentionPurgeCommand` | Dry-run or apply retention purge through injected services. |
| `scheduler:run` | `BlackOps\Internal\Console\SchedulerRunCommand` | Run registered maintenance tasks once and exit. |
| `scheduler:daemon` | `BlackOps\Internal\Console\SchedulerDaemonCommand` | Run registered maintenance tasks repeatedly with an explicit interval. |
| `worker:run` | `BlackOps\Internal\Console\WorkerRunCommand` | Run the single-claim deferred worker with recovery, heartbeat, and graceful shutdown. |
| `database:migrate` | `BlackOps\Internal\Console\DatabaseMigrationMigrateCommand` | Apply or dry-run the versioned PostgreSQL framework baseline. |
| `database:status` | `BlackOps\Internal\Console\DatabaseMigrationStatusCommand` | Show applied and pending framework migration versions without changing the database. |

For normal build pipelines, prefer the unified build command so operation metadata, HTTP route metadata, and container definitions are generated from the same provider set.

The worker command requires an application-composed `WorkerLoop`. Its signal heartbeat must be shared with the handler guard and must use a dedicated DBAL connection. See [Deferred Worker Runtime](worker-runtime.md) for the composition and shutdown contract.

Database migration commands require an application-owned DBAL connection and an explicitly constructed `DatabaseMigrationRunner`. They are deployment tools and are not registered or executed implicitly by HTTP or Worker startup. See [Database Migrations](database-migrations.md).

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

The public Application-aware `build:compile` and `operation:list` read `operations.discovery` and scan only while those commands execute. The legacy standalone unified command retains its explicit provider inputs. Production HTTP and Worker runtime continue to use generated artifacts only and never fall back to source scanning.

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

Application HTTP composition may add the configured JSONL observation pipeline. Configuration validation and stream opening happen during HTTP composition; source discovery, build, migration, and directory creation do not. The open stream remains owned by the observer referenced from the composed handler graph.
