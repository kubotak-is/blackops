# Operation Registry

Operation providers are the public build-time extension boundary for package and application operations.

A provider returns operation definition class names only. It does not create handlers, values, outcomes, service instances, or runtime dependencies. Handler and infrastructure service construction remains a runtime container responsibility.

The internal provider compiler reads one or more providers, compiles each returned operation definition through the metadata compiler, and builds the read-only operation registry. Duplicate type IDs or definition classes are rejected by the registry.

Config loading, Composer package discovery, file scanning, token scanning, and manifest file orchestration are separate bootstrap/build concerns.

Operation provider config loading is an internal bootstrap concern. A PHP config file may return a single `OperationProvider`, a list of provider instances, or a list of provider class names that can be instantiated without constructor arguments. The loader returns provider instances that can be passed to the internal provider compiler.

The operation manifest file boundary writes registry metadata to a PHP array file and loads it back into an operation registry. The manifest contains scalar values and class names only.

The operation manifest compile command ties the internal provider config loader, provider compiler, and manifest file writer together for build-time verification. It reads an operation provider config file and writes a PHP operation manifest file. HTTP route manifest generation and runtime container compilation remain separate build steps.

The internal operation definition factory can instantiate no-argument operation definitions returned by providers for build steps that need attributes from definition instances, such as HTTP route manifest compilation. Definitions that need constructor arguments are rejected at this boundary.

The internal build artifacts command coordinates operation provider config loading, operation manifest generation, HTTP route manifest generation, and runtime container dumping in one build step. It can merge explicit operation provider config with provider class names discovered from Composer metadata.

The build artifacts command can run inside an internal build lock. The lock uses a local lock file and fails fast if another process already holds it.

The build artifacts command can also store a lightweight fingerprint for explicit input files. When the fingerprint matches and all output artifacts exist, the command skips regeneration.

Composer provider discovery reads explicit provider class names from Composer metadata under `extra.blackops.operation-providers` and `extra.blackops.service-providers`. The discovery boundary returns provider class names only. The build artifacts command can accept a Composer metadata file and pass discovered operation provider class names through the same provider instantiation boundary used by explicit config files.

Installed Composer provider discovery reads the same package metadata from `vendor/composer/installed.json`. It supports the Composer package list wrapped in a `packages` key and the older root package-list shape. The build artifacts command can accept installed package metadata and merge discovered operation providers with explicit config and root Composer metadata.

Production runtime bootstrap reads the generated operation manifest file through the internal manifest file boundary. If the artifact is missing or invalid, startup fails rather than falling back to dynamic discovery.
