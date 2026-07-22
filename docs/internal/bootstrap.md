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
| `build:compile` | `BlackOps\Internal\Console\ApplicationBuildCompileCommand` | Compile operation, HTTP, and frontend contract manifests plus the runtime container together. |
| `frontend:generate` | `BlackOps\Internal\Console\FrontendGenerateCommand` | Generate the deterministic TypeScript operation-object tree from the current frontend contract manifest. |
| `frontend:check` | `BlackOps\Internal\Console\FrontendCheckCommand` | Compare the expected and existing generated frontend trees without changing either one. |
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
| `database:seed` | `BlackOps\Internal\Console\DatabaseSeedCommand` | Run the application root seeder from a fresh compiled container. |
| `database:status` | `BlackOps\Internal\Console\DatabaseMigrationStatusCommand` | Show applied and pending framework migration versions without changing the database. |

For normal build pipelines, prefer the unified build command so operation metadata, HTTP route metadata, frontend contract metadata, and container definitions are generated from the same provider set.

TypeScript generation is a separate explicit step:

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
# Run the application-owned TypeScript compile and runtime tests next.
```

`frontend:generate` and `frontend:check` load only the accepted `app.build.frontend_manifest`; neither command rediscovers PHP source nor implicitly runs `build:compile` or the other frontend command. The artifact Application Build ID must match `app.build.application_build_id`. Missing, unsupported, corrupt, or stale artifacts fail before the output tree changes.

`config/frontend.php` is optional. Its only setting is an absolute output path beneath the Application Root:

```php
return [
    'output' => dirname(__DIR__) . '/resources/js/blackops',
];
```

When the file is absent, `resources/js/blackops` is used. The generator rejects the Application Root itself, external paths, files, symlinks, symlink ancestors, and non-empty directories without a valid BlackOps ownership marker. It writes and verifies a temporary tree beside the output, replaces an existing owned tree through a backup rename, restores the prior tree on replacement failure, and cleans successful temporary or backup state.

The generated tree contains `types.ts`, `client.ts`, `manifest.json`, and one module per routed Operation. `manifest.json` records generator schema version 4, the Application Build ID, and the canonical frontend-contract hash without timestamps or source paths. Ownership checks accept the known version 1, 2, and 3 markers so trees produced before typed fetch, status query, or finite wait can be replaced, while newly written temporary trees must validate as the current version.

Each module exports a frozen PascalCase Operation Object with readonly `type`, `method`, `path`, and `strategy`, plus `.url()`, `.toRequest()`, `.fetch()`, `.status()`, and `.wait()`. Request generation applies the same native scalar rules as HTTP binding, protects Operation-owned headers and `Content-Type`, and validates optional base URLs. An Operation with at least one body binding always serializes a JSON object and sets `Content-Type: application/json`; when every body field is optional and omitted, the body is `{}`.

`.fetch()` selects a per-call structural Fetch implementation before the Browser `globalThis.fetch` default and does not retain mutable global configuration. It returns an Operation-specific discriminated union for inline outcome, inline void, deferred acknowledgement, protocol, rejection, validation, internal, and transport results. Except for an empty 204 response, decoding requires an `application/json` media type, a JSON object, the exact status-specific keys, and runtime-valid scalar fields. Malformed response objects, unknown keys, invalid JSON, and mismatched fields become `unexpected_response`; fetch rejection, abort, and body-read failure expose stable transport codes without raw bodies or exception details.

`.status(operationId, options)` validates a canonical lowercase UUIDv7 before issuing one `GET /operations/{operationId}` request. It reuses only the per-call base URL, headers, credentials, signal, and injected Fetch options; it does not reuse the Operation route bindings. The Operation-specific result strictly narrows all seven lifecycle states, decodes completed outcomes with the generated scalar metadata, maps an empty outcome object to `undefined`, and distinguishes authentication, unavailable, expired, internal, and safe transport failures. Non-terminal states require a canonical positive integer `Retry-After`; terminal states reject that header. The method never polls, retries, or changes `.fetch()` behavior.

`.wait(operationId, options)` requires a subscribable structural abort signal and a positive safe-integer `maxWaitMilliseconds`. It queries status immediately, races every in-flight request against the fixed deadline and abort signal, and waits only the server-provided `Retry-After` interval after a strictly decoded non-terminal state. Its generated result type contains only the four terminal states and failure results; the three non-terminal states cannot escape the polling loop. Clock and timer implementations are optional per-call structural injections, so the generated source compiles without DOM or Node namespaces. Every request and sleep removes its listener and clears its timer on success, abort, timeout, or failure. Error responses, network failures, malformed responses, invalid options, and reversed clocks stop immediately without retry.

`frontend:check` resolves Build Configuration, the expected Build ID, the Frontend Contract Artifact, output configuration, and the expected TypeScript tree in that order. It then compares the complete regular-file path set and exact bytes. A missing output directory exits 1 as `missing`; a missing file, changed bytes, extra file, or nested symlink exits 1 as `drift`; a fresh tree exits 0. Invalid configuration, artifact, generated contract, or filesystem inspection exits 2 with a fixed safe stderr message. Empty directories do not affect freshness. The inspector never follows symlinks and verifies regular-file identity around reads. It does not create, write, rename, delete, or clean any path.

`tests/Frontend/` owns the independent TypeScript 6.0.3 fixture used by CI. The frontend job executes `build:compile -> frontend:generate -> frontend:check`, then a DOM-free strict ES2022 ESM type check, discriminated-union narrowing check, and Node injected-Fetch runtime tests. Permanent status/wait evidence covers all lifecycle states, typed and empty outcomes, required wait options, retry timing, in-flight deadline and abort races, immediate-stop failures, cleanup, and parallel invocation isolation. Generated TypeScript, PHP build artifacts, and CommonJS runtime emit are ignored, guarded against tracking, and removed by the fixture cleanup command. This fixture does not use the Documentation Website toolchain.

The installed Quickstart owns the same frontend boundary without making Node.js a backend runtime dependency. Its committed application files are `config/frontend.php`, `package.json`, `pnpm-lock.yaml`, `tsconfig*.json`, `resources/js/application/`, and `tests/Frontend/`. The generated `resources/js/blackops/` tree, `node_modules/`, and runtime emit remain ignored. `bin/setup` only prepares environment and writable runtime directories; it prints the explicit frontend dependency, build, generation, drift-check, and test commands but never runs them.

`tests/Consumer/quickstart-e2e.sh` copies that source to an isolated consumer and executes the canonical chain against the real Worker Mode HTTP runtime. The application-owned TypeScript test imports the generated Welcome, Report, Order, and Diagnostics objects, verifies `.url()`, `.toRequest()`, readonly metadata, and all supported result families, and keeps tokens, sensitive values, and raw diagnostic bodies out of generated artifacts, typed results, and observed logs. Skeleton creation, publication, and framework-update regressions compare the committed frontend files byte-for-byte so framework upgrades do not replace application-owned code.

The Quickstart binds an application-owned status authorizer that allows only matching authenticated current and persisted origin actors. Its real HTTP journey verifies deferred 202, one-shot accepted status, retry-scheduled state after the first worker attempt, typed completion after the second attempt, a finite poll timeout for an unprocessed operation, safe 401/404 handling, cache and retry headers, and non-disclosure of credentials, actors, sensitive inputs, and raw errors.

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
  var/cache/blackops/frontend.php \
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
- frontend contract manifest PHP file
- dumped runtime container PHP file

`--application-build-id` is required. Build pipelines should supply an immutable identifier for the application
release, such as a source revision or deployment build number. The command writes the exact same identifier to the
operation, HTTP, and frontend contract manifests. Changing the requested build ID also invalidates a matching development fingerprint,
so an earlier release's manifests are not reused.

Each manifest is a PHP array with a versioned envelope. `schemaVersion` is owned by each artifact type; the
operation manifest currently uses version 1, while the HTTP and frontend contract manifests use version 2:

```php
return [
    'schemaVersion' => 2, // HTTP/frontend example; the operation manifest currently uses version 1.
    'applicationBuildId' => 'release-2026-07-11.1',
    'payload' => [
        // Operation, HTTP, or frontend contract metadata.
    ],
];
```

The frontend contract contains only routed Operation metadata required by later TypeScript generation. Its scalar
kinds preserve PHP native intent as `string`, `integer`, `float`, or `boolean`; a later generator can map both
numeric kinds to TypeScript `number` without losing the distinction needed for request encoding and outcome
decoding. The artifact also records nullability, requiredness, binding source and transport name, validation rule
metadata, sensitive-input presence, outcome shape, execution strategy, and deterministic module/export names. It
excludes constructor default values, examples, runtime values, credentials, environment data, and absolute source
paths. Unsupported types, sensitive outcome properties, mismatched source manifests, and naming collisions fail the
build instead of falling back to `any` or source rediscovery.

The generated artifacts contain scalar values, arrays, class names, synthetic runtime service definitions, and Ray.Aop proxy classes. They must not contain credentials, resolved database connection parameters, tokens, environment secrets, closures, or live service instances. `DatabaseManager`, the default DBAL `Connection`, and the internal transaction runtime are synthetic definitions. HTTP and deferred worker composition set them before resolving application handlers, policies, or middleware. The transaction runtime receives the same execution-scope provider used by inline or deferred operation execution. Container compilation validates transactional connection names against the accepted configuration snapshot but does not connect to a database.

Application-aware `build:compile` inspects non-synthetic Symfony service definitions after providers, handlers, authorization policies, and HTTP middleware have been registered. Definitions with `#[Transactional]` or `#[AfterCommit]` are validated and replaced with Ray.Aop proxy classes generated under the container artifact directory's `aop/` child. The dumped container explicitly requires those proxy files and injects the private framework interceptor binding when it constructs each proxy. A production process therefore loads only the compiled container and adjacent proxy artifacts; it does not scan application source or invoke Ray.Aop temporary proxy generation APIs.

The build clears the framework-owned `aop/` directory before generation and clears partial proxy output when validation, container compilation, or dumping fails. Proxy names remain stable for the same source, bindings, and artifact directory. A direct `new` expression still creates the original class and bypasses interception; only instances constructed from the compiled Symfony container use the generated proxy. General service transaction bindings resolve to the internal required-transaction interceptor, and after-commit bindings resolve to the shared callback queue. Operation transaction bindings remain pass-through because the compiled operation metadata drives the fixed lifecycle after authorization; this prevents AOP from opening a second transaction around `handle()`.

The operation manifest stores only the build-resolved transaction connection name. Application-aware build compilation resolves an omitted name to the accepted default and rejects transactional operations when database configuration is absent or the resolved name is unknown. Production HTTP and worker composers create one operation transaction coordinator from the injected `DatabaseManager`, framework `Connection`, transaction runtime, and execution scope. Object identity between the selected application connection and framework connection selects the atomic terminal path; matching names alone are not sufficient.

Inline execution writes successful canonical terminal records inside a shared application transaction and sends their observed projections only after commit. Deferred execution performs claim fencing, result state, sequence, terminal journal, and outcome writes inside that same root transaction. Rejection and throwable paths leave the fixed lifecycle only after rollback, allowing the existing rejection or supervision transaction to record the result. Different-connection execution commits the application transaction before the existing framework terminal transaction and intentionally provides no cross-connection atomicity.

## Locking and Fingerprint

The `--lock` option guards a build output set with a local lock file. Use it when multiple build processes could write the same artifacts.

The `--fingerprint` option stores a lightweight hash for build inputs. When the fingerprint still matches and all output artifacts exist, the command skips regeneration.

Freshness requires the operation, HTTP, and frontend contract manifests to carry the requested Application Build ID and a supported schema. Production HTTP and Worker runtime deliberately do not load the frontend contract artifact; it is a build-time input for the later frontend generator only.

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

Application-aware containers also implement Symfony's internal mutable container contract so the composition root can set the two database synthetic services. This mutable type remains internal and is not exposed by the public Application API.

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

The current low-level production runtime composer remains internal and does not create a complete front controller, choose a transport adapter, create database connections, or load environment variables. The higher-level Application HTTP and Worker composers create a named `DatabaseManager`, inject its default Connection into the compiled container, and resolve the Framework Store Connection from the same manager.

The higher-level composers also install one application connection lifecycle around each HTTP request or deferred claim runtime. The manager exposes only its already-created DBAL objects to this internal lifecycle. Every created object receives a start-boundary health check, including an object closed after the previous invocation. Names that have never been requested from the manager remain uncreated and lazy. A failed check closes the stable DBAL object and retries once, allowing services that already hold that object to reconnect without reinjection.

The finish boundary inspects every object created by the end of the invocation. Success keeps healthy, transaction-free connections open for process reuse. A leaked transaction is closed and converted into a failure before an HTTP response or deferred acknowledgement escapes. A primary runtime or cleanup failure closes all generated application connections best-effort without replacing that primary Throwable. This boundary does not retry queries, transactions, or commits.

Deferred worker composition creates the main and default Application connections from one manager, then creates the signal heartbeat connection from a second manager. Only the first manager is attached to the claim lifecycle and synthetic application services; the heartbeat object cannot enter application service resolution or lifecycle cleanup.

Application HTTP composition may add the configured JSONL observation pipeline. Configuration validation and stream opening happen during HTTP composition; source discovery, build, migration, and directory creation do not. The open stream remains owned by the observer referenced from the composed handler graph.
