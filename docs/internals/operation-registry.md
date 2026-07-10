# Operation Registry

Operation providers are the public build-time extension boundary for package and application operations.

A provider returns operation definition class names only. It does not create handlers, values, outcomes, service instances, or runtime dependencies. Handler and infrastructure service construction remains a runtime container responsibility.

The internal provider compiler reads one or more providers, compiles each returned operation definition through the metadata compiler, and builds the read-only operation registry. Duplicate type IDs or definition classes are rejected by the registry.

Config loading, Composer package discovery, file scanning, token scanning, and manifest file orchestration are separate bootstrap/build concerns.

## Development Operation Discovery

Development discovery accepts one or more application-owned source roots. Every root is resolved with `realpath`, must be a readable directory, and is deduplicated before scanning. Source files are accepted only when their resolved path remains inside a configured root. A PHP file symlink that resolves outside every root fails discovery instead of widening the scan boundary.

Composer PSR-4 metadata supplies namespace prefixes and source directories. Conventional PSR-4 paths become initial class candidates. Composer classmap metadata supplies exact class-to-file candidates. Entries whose files are outside the configured discovery roots are ignored because one Composer autoloader normally contains application, framework, and vendor classes together. Invalid metadata shapes and unreadable referenced paths fail fast.

The Token Scan fallback parses every PHP file inside the roots without executing it. It obtains fully qualified named class declarations without requiring the file name to match the class name. Interface, trait, enum, anonymous class, and `::class` tokens are not class candidates.

After token parsing completes, discovery loads each source file that declared at least one named class exactly once through a controlled loader. Files with no named class candidates are not executed. This allows a class whose file name differs from its class name to be reflected while preventing a side-effect-only PHP file from being indiscriminately required. An already loaded class must resolve to the same candidate source file or discovery fails. The final class existence check does not invoke autoloaders, so a conventional PSR-4 candidate that is not declared cannot execute the source file a second time.

Reflection is the final filter. A result must be a concrete, instantiable class implementing `Operation`, and its reflected source file must still resolve inside the configured roots and equal the candidate file. Duplicate PSR-4, classmap, and Token Scan candidates collapse by class name; conflicting file mappings fail. The resulting definition class names are sorted deterministically.

This boundary is development/build tooling. It does not run during request handling. Production startup requires versioned generated manifests and fails when they are missing or invalid; it never invokes development discovery as a fallback.

The development console input boundary loads Composer-generated PSR-4 and classmap PHP files in isolated scope and requires each file to return an array. A complete discovery input consists of at least one explicit source root, a Composer base directory, and both metadata files. Partial input fails instead of assuming wider defaults.

`blackops:operation:list` compiles discovered definitions through the same operation metadata compiler used by manifests, sorts the resulting metadata by type ID, and displays the type ID, definition class, and execution strategy class.

The standalone operation and HTTP manifest compile commands accept the same optional discovery input. Explicit provider definitions retain their order and existing duplicate checks. An exact definition also found by discovery is omitted from the discovery contribution, while a different definition using the same type ID is rejected by the operation registry. HTTP compilation consumes the same merged definition set, so generated FastRoute data and operation metadata stay aligned.

The production unified build command does not accept source discovery input. Production runtime loading continues to require generated, versioned artifacts and never falls back to this development boundary.

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
