# D160: Journal and Outcome Read Audit Adapters

Status: Decided after independent authority review; Product progress in the D2J Report; unreleased 1.3

## Context

D2's requested/one-shot terminal session and D2S's Status adapter are accepted.
Journal and Outcome already have public default-deny queries over tenant-scoped
subject and protected readers. Their current composition emits no access Audit.
Journal fully materializes its iterable, so both queries have a result boundary
where terminal persistence can be required without exposing partial data.

Alternate subject readers can return a foreign-tenant subject and both Defaults
trust its tenant after checking the Operation ID. The audited graph therefore
needs a mandatory tenant equality guard before the application authorizer. An
injected prebuilt Default hides that authorizer and cannot enforce the guard.

The Defaults also preserve resource exception identity from their protected
readers. Current unannotated inner frames can expose raw arguments when PHP
exception arguments are enabled. Fixing only the new outer adapters cannot
remove those already-captured frames.

## Decision

Specification111 is the most-specific child contract. Add two Internal public-
query adapters, one shared tenant authorizer guard and two fixed per-read UUIDv7
execution sources. Each adapter constructs its own concrete stateless Default
with the guard, reuses accepted D2 sessions and commits requested before any
lookup. It commits accessed for Found and unavailable for all other result/failure
paths before releasing the exact existing result or safe exception. Terminal
failure overrides everything as the matching storage_failed.

Keep the application OperationDataPurpose as the unchanged policy input. The
Audit purpose and execution type are fixed by the reader resource. Do not copy
application purpose into the existing canonical drafts or add a Catalog version.
Server UUIDs identify separate read executions; D2 still owns its nonce and Clock.

Preserve the existing auto-commit read consistency model and tenant SQL. This
child adds two required Audit write roots; it does not add the Status reader's
snapshot/identity-drift guarantee. A snapshot redesign is not necessary to bind
these existing protected queries to Audit and requires its own source contract.
The trusted parent must later prove same-Connection wiring and actual activation.

Permit only the exact annotation-only correction in the inherited Framework
exception frames established by spec111. Preserve existing error classification,
method bodies and signatures. Application-owned unannotated frames remain outside
the Framework guarantee. Prove safe inherited traces with argument capture enabled.

## Alternatives Rejected

- Instrument raw Journal/Outcome infrastructure ports: would also capture
  internal lifecycle/worker/status/replay work and risk duplicate access records.
- Accept an arbitrary query/callback: cannot prove its tenant guard, completion
  boundary or error classification.
- Duplicate existing query/codec algorithms: creates a second behavior source.
- Add one outer Audit transaction: hides requested persistence and changes the
  existing read ownership; failed terminals must not repair foreign roots.
- Activate current application bindings in this child: precedes parent D's
  remaining adapters and trusted composition gates.

## Consequences

This is latent unreleased 1.3 implementation. Stable 1.2.1 behavior and website
content remain unchanged. Local source verification is distinct from future
same-SHA package/CI delivery. D2X, other D adapters, composition and the parent
release remain incomplete. Product progress belongs to the D2J Report and STATE.
