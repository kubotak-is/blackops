# Specification 101: Framework-owned Transaction Proxy

Status: Decided

## Purpose and boundaries

BlackOps owns a build-time proxy generator for exactly `#[Transactional]` and `#[AfterCommit]`. The proxy is a PHP subclass selected by the compiled Symfony DI container. It is not a general-purpose AOP engine, does not accept arbitrary interceptors, and never scans source or generates a proxy at Runtime.

The contract preserves D096, D108, Specification 09, and Specification 11:

- Operation Definition／self-handled `handle()` Transactional metadata is executed once by the Framework-owned Inline／Deferred Operation Lifecycle. A generated Operation proxy is pass-through for Transactional ownership.
- A non-Operation DI service may use class-level or method-level Transactional. Method-level connection metadata overrides class-level metadata.
- AfterCommit remains a `void` invocation queued in the current Transaction Scope, executed in registration order after the outermost successful commit, or immediately outside a transaction.
- Unsupported targets fail at build time. There is no silent unproxied fallback, Runtime Source Scan, temporary Runtime proxy, or dual proxy chain.

## Normative terms

MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY have their usual RFC 2119 meaning. A “source class” is the Application class before replacement; a “proxy class” is the generated subclass; a “Definition” is the Symfony DI Definition selected by service ID.

## PHP Signature Matrix

The initial generator MUST implement this matrix. `support` requires exact compatibility tests; `reject` emits a Build Error when either BlackOps Attribute is present; `N/A` is not a proxy target.

| Declaration | Classification | Required behavior |
| --- | --- | --- |
| Instantiable, non-final concrete class | support | Generate a subclass only for a DI-managed Definition. |
| `readonly` concrete class | support | Proxy class remains `readonly`; binding state is immutable. Compile and invoke fixtures MUST pass. |
| Inherited public, non-final instance method | support | Preserve declaring signature; method-level Transactional overrides class-level connection. |
| Variadic parameter | support | Preserve `...`, argument order, and named/positional calls. |
| Union, intersection, DNF, nullable, `never`, `mixed`, `static`, `self`, `parent` types | support | Preserve PHP 8.5 LSP-compatible spelling and parentheses. |
| Scalar, array, and constant-expression default | support | Preserve exact default value in the generated declaration. |
| Unrelated PHP method/class/parameter attributes | support | Preserve attributes where PHP permits; BlackOps Attributes remain metadata, not user callback code. |
| Final class | reject | Diagnostic `BO_PROXY_SIGNATURE_FINAL_CLASS`. |
| Public final method | reject | Diagnostic `BO_PROXY_SIGNATURE_FINAL_METHOD`, including class-level Attribute cases that would otherwise be skipped. |
| Protected/private method, constructor, destructor | reject | Diagnostic `BO_PROXY_SIGNATURE_VISIBILITY`. |
| Static method | reject | Diagnostic `BO_PROXY_SIGNATURE_STATIC`. |
| Abstract class, interface, trait | N/A | No concrete service instance; an attributed DI target is a Build Error `BO_PROXY_TARGET_NOT_CONCRETE`. |
| Generator (`yield`) | reject | Diagnostic `BO_PROXY_SIGNATURE_GENERATOR` for both Transactional and AfterCommit. |
| By-reference return | reject | Diagnostic `BO_PROXY_SIGNATURE_REFERENCE_RETURN` for both Attributes. |
| By-reference parameter | reject | Diagnostic `BO_PROXY_SIGNATURE_REFERENCE_PARAMETER` for both Attributes. |
| Transactional and AfterCommit on one method | reject | Diagnostic `BO_PROXY_ATTRIBUTE_CONFLICT`; use a separate method. |
| Attribute on property or parameter, or repeated Attribute | reject | Diagnostics `BO_PROXY_ATTRIBUTE_TARGET` or `BO_PROXY_ATTRIBUTE_DUPLICATE`. |
| Direct `new`, static invocation, or non-DI object | N/A | No implicit interception and no Runtime fallback. |
| Synthetic Definition | N/A | Runtime-injected object; no build-time source is available. |

The generator MUST reject a whole class if any attributed method is rejected. It MUST NOT partially proxy only the methods that happen to be representable.

Framework validation is owned by the contract seam; generator defenses consume
its metadata without introducing a second validator or runtime fallback.

## Attribute precedence and ownership

For a non-Operation service, a class-level Transactional applies to eligible public instance methods. A method-level Transactional overrides the connection for that method. An unattributed method is unchanged. AfterCommit is method-level and cannot be placed on a class, property, or parameter.

Operation ownership is determined from the source class and Operation metadata before Definition class replacement. The Operation Lifecycle starts the Transaction after authorization, invokes the handler, persists shared-connection Terminal／Outcome, and commits before AfterCommit callbacks. The proxy MUST bind an Operation Transactional method as pass-through (or omit the Transactional binding) so this path executes exactly once. A service proxy MUST NOT be applied to an Operation through an alias or generated subclass marker.

The active build profile is `framework`. A Definition, alias target, and
generated service MUST NOT contain multiple proxy identities. A marker or
manifest mismatch emits `BO_PROXY_MODE_CONFLICT`; Runtime never “tries both”.

## Symfony DI Definition preservation

The generator MUST mutate only the source class reference and Framework binding metadata. It MUST retain the original service ID and alias graph.

| Definition feature | Classification | Contract |
| --- | --- | --- |
| Constructor arguments, autowiring, named bindings, properties | support | Preserve values and References exactly; generated constructor remains LSP-compatible. |
| Public/private visibility | support | Preserve `Definition::isPublic()` and alias visibility/deprecation. |
| Shared scope | support | Preserve `isShared()` and instance identity. |
| Tags, autoconfigured state, `instanceof` conditionals | support | Preserve complete tag attributes and compiler-applied conditionals. |
| Method calls and configurator | support | Preserve original order; append binding call at a defined point; do not replace configurator. |
| Alias | support | Alias still targets the original service ID and resolves the same generated instance. |
| File and deprecation metadata | support | Preserve values; diagnostics contain no arguments or secrets. |
| Factory | reject | `BO_PROXY_DEFINITION_FACTORY`; a factory can bypass the replaced class. |
| Lazy | reject | `BO_PROXY_DEFINITION_LAZY`; Symfony lazy proxy plus Framework subclass is not an initial supported composition. |
| Synthetic | N/A | `BO_PROXY_DEFINITION_SYNTHETIC` only if an Attribute attempts to target it. |
| Abstract Definition | reject | `BO_PROXY_DEFINITION_ABSTRACT`; child Definitions are audited independently. |
| Decoration | reject | `BO_PROXY_DEFINITION_DECORATION`; decoration ordering must be redesigned in a future Decision. |

Definition preservation tests MUST compare constructor arguments, bindings, properties, visibility, shared flag, tags, method calls, configurator, alias target, and service identity before and after replacement. Unsupported features MUST fail before selecting an unproxied class.

## Safe Build Diagnostics

Every Build Error MUST include only:

- stable diagnostic code;
- service ID and source class FQCN;
- method name when applicable;
- Attribute name when applicable;
- application Build ID and source file path only when the path is already a configured non-secret build input.

Diagnostics MUST NOT include DSN, credentials, resolved connection parameters, payloads, generated source text, stack traces, or complete Throwable messages. Unknown connection names use `BO_PROXY_CONNECTION_UNKNOWN` without echoing secret configuration.

## Generated artifact and drift contract

The active artifact directory is adjacent to the compiled Container. A build MUST generate into a unique staging directory and publish a complete manifest plus proxy files atomically. The active manifest MUST contain:

- application Build ID (`app.build.application_build_id`);
- proxy contract/generator version and PHP major/minor target;
- content hash of every attributed source file;
- normalized Attribute, connection, and signature metadata hash;
- source class, proxy FQCN, relative path, and file content hash;
- selected profile (`framework` only).

Input identity MUST use content hashes, not only mtime/size. It MUST include attributed source files, relevant service-provider/config inputs, generator version, PHP target, and normalized connection names. Any source drift invalidates the artifact even when mtime and size are unchanged.

Generation MUST parse and validate each file, class declaration, manifest reference, Definition map, and file hash before publication. Publication MUST use a same-filesystem atomic rename of the completed staging unit. Stale files are removed only after successful publication by comparing the active manifest. A failed build leaves the previous complete artifact available or selects no new container; it MUST NOT delete the last known-good artifact first.

The compiled Container MUST require only files listed by its matching manifest. Runtime loading verifies Build ID, profile, manifest hash, class map, and file hash. Runtime MUST NOT scan source, regenerate a proxy, or select a proxy from a different Build ID. Artifact paths include the Build ID or an equivalent immutable directory identity so OPcache cannot satisfy a new manifest with an old class path; OPcache reset remains a deployment concern.

## Migration and rollback

The Application-aware compile uses the Framework profile as its sole active profile. The standalone legacy `blackops:build:compile` command is not part of this surface because it does not invoke AOP. The selected Framework profile is recorded in the immutable manifest, and unsupported signatures fail with a diagnostic; they are never silently unproxied. Central command/profile wiring and manifest-aware RuntimeContainerDumper integration were delivered through P21-006 and closed by P21-007.

The Framework profile MUST compile and invoke `never`, MUST preserve named
variadic keys and values, and MUST fail unsupported signatures rather than
silently leaving an attributed method unproxied.

Rollback selects a previous complete Container plus matching manifest and artifact directory. It never enables a second interceptor at Runtime. P21-007 accepted the Framework-only release gate; future rollback retains the complete Framework artifact unit.

## Framework profile closeout gate

The Framework profile closeout is accepted only after all of the following are independently verified:

1. Every support and reject Matrix row has a compile/runtime fixture with safe diagnostics.
2. Generator manifest, content drift, atomic publication, post-success cleanup, Runtime no-scan, and OPcache-safe identity tests pass.
3. DI preservation tests pass for supported features and reject factory/lazy/synthetic/abstract/decoration boundaries.
4. Inline, Deferred, self-handled, and general Service tests prove one Transaction owner and no double intercept.
5. Framework signature/DI/lifecycle, migration, previous-build rollback, and consumer package-export tests pass.
6. Namespace/artifact scans and clean-install/export verification pass.
7. P21-007 separately accepted the Framework-only closeout and retained no fallback path.

P21-007 closeout: Framework is the sole active build profile. The immutable
Profile Unit, no-fallback rule, complete-release rollback, and global generated
prefix guard remain normative.

## Traceability

- [D096 Phase 13 Database and Transaction Runtime](../decisions/096-phase-13-database-and-transaction-runtime.md)
- [D108 Ray.Aop Upstream and Phase Order](../decisions/108-ray-aop-upstream-and-phase-order.md)
- [D137 Framework-owned Transaction Proxy Contract](../decisions/137-framework-owned-transaction-proxy.md)
- [Runtime and Dependency Injection](09-runtime-and-di.md)
- [Durable Journal and Transactions](11-durable-journal-and-transactions.md)
- [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
