# D141 Release Architecture and Export Boundary

Status: Decided

## Context

P22-003A updated Deptrac from 4.6.2 to 4.7.1 and removed the PHP 8.5 parser stop. The complete 857-file graph then exposed 152 violations and 59 uncovered dependencies that had accumulated while the graph could not run. The violations are concentrated in eight directions: Transport to generic Internal／StorageProtection／Telemetry, Internal to Telemetry／Application, and Execution／HTTP／Journal to Telemetry. The uncovered dependencies belong to the already implemented public Identifier, Idempotency, and Outbox namespaces plus adopted Dotenv and Nyholm libraries.

The graph result is not a reason to weaken the Architecture gate. Some dependencies are required by the decided behavior: public TelemetryContext propagation crosses HTTP, execution, journal, transport, and internal runtime boundaries; protected PostgreSQL adapters use the framework-owned storage-protection runtime; Identifier, Idempotency, and Outbox are explicit public contracts. Other edges are accidental and must not be normalized, especially a blanket Transport to Internal permission and the Internal to Application cycle.

The tracked Mago baseline introduced a second boundary issue. `mago-lint-baseline.toml` is development-only root metadata, but it was not added to the synchronized Git／Composer archive exclusions, so Framework package export fails.

## Decision

Deptrac will model every current public namespace and adopted library explicitly. Add Identifier, Idempotency, and Outbox layers, and add Dotenv and Nyholm PSR-7 to the Library layer. Their allowed directions follow the existing public contracts: Identifier and Idempotency depend on Core; Outbox depends on Core; Core may depend on Idempotency for the optional execution-context hash; Execution and HTTP may depend on Idempotency; Internal may depend on all three.

TelemetryContext and TelemetryCorrelation are bounded cross-cutting public values. Execution, HTTP, Journal, Transport, and Internal may depend on the Telemetry public layer only for the propagation and safe-correlation contracts in Specification 100.

The catch-all Internal layer will not be granted to Transport. Instead, Deptrac will separate three bounded framework facilities from generic Internal using narrow collectors:

- Internal Telemetry runtime, depending only on Core, Telemetry, and adopted Library APIs.
- Internal Storage Protection runtime, depending only on Core, StorageProtection, and the Internal Telemetry runtime.
- Deferred transport integrity validation, depending only on Core.

Transport may depend on those bounded facilities, StorageProtection public values, and Telemetry public values. It may not depend on the catch-all Internal layer. The bounded collectors must be excluded from the catch-all Internal collector so an apparently narrow edge cannot inherit generic Internal access.

Internal will not depend on Application. Add a public Core `ConfigurationFailure` marker implemented by `ApplicationBootstrapException`; internal CLI adapters catch the Core marker while the concrete bootstrap exception remains a `RuntimeException`. This preserves safe exit classification without creating an Application／Internal layer cycle.

`mago-lint-baseline.toml` is development-only metadata. Add it to both `.gitattributes` `export-ignore` and Composer `archive.exclude`, and assert the exclusion in the Framework package export and version guards. The Mago baseline remains tracked in the repository and CI; only published package archives omit it.

No `skip_violations`, architecture baseline, blanket Internal permission, public signature exposure of Internal types, Mago rule disable, or severity downgrade is allowed.

## Consequences

- Deptrac can require 857-file analysis with zero violations and zero uncovered dependencies on PHP 8.5.
- Required cross-cutting dependencies are explicit and bounded; Transport does not gain access to arbitrary Internal implementation.
- Application bootstrap failures retain their concrete public type and RuntimeException ancestry while internal CLI classification depends on a stable Core category.
- Git and Composer Framework archives remain identical and exclude all release-quality development metadata.
- The public Core API inventory and reader documentation must include `ConfigurationFailure`; the documentation website must be rebuilt and checked.

## Traceability

- [Namespace Dependencies](../spec/16-namespace-dependencies.md)
- [Core API](../spec/17-core-api.md)
- [Stable 1.2 Release Plan](../spec/103-stable-1-2-release-plan.md)
- [P22-003A Tooling Blockers](../orchestration/tasks/P22-003A-release-quality-tooling-blockers.md)
- [P22-003B Architecture and Export Closure](../orchestration/tasks/P22-003B-release-architecture-and-export-closure.md)
