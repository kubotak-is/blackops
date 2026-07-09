# Operation Registry

Operation providers are the public build-time extension boundary for package and application operations.

A provider returns operation definition class names only. It does not create handlers, values, outcomes, service instances, or runtime dependencies. Handler and infrastructure service construction remains a runtime container responsibility.

The internal provider compiler reads one or more providers, compiles each returned operation definition through the metadata compiler, and builds the read-only operation registry. Duplicate type IDs or definition classes are rejected by the registry.

Config loading, Composer package discovery, file scanning, token scanning, and manifest file orchestration are separate bootstrap/build concerns.

Operation provider config loading is an internal bootstrap concern. A PHP config file may return a single `OperationProvider`, a list of provider instances, or a list of provider class names that can be instantiated without constructor arguments. The loader returns provider instances that can be passed to the internal provider compiler.
