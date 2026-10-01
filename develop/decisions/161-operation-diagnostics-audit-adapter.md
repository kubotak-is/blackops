# D161: Global Diagnostics Read Audit and Safe Failure Reconstruction

Status: Decided — Product progress in D2X Report; unreleased 1.3

## Context

Accepted D2 provides a Diagnostics authorization request/port and required read
session. Accepted D2J completes the Journal/Outcome child locally. The user
authorized continuation to the reserved D2X child. Spec112 is its most-specific
contract; Framework/Skeleton remain Experimental Stable1.2.1 and1.3 is unreleased.

OperationDiagnosticsQuery is an Internal aggregate shared by local CLI/Viewer.
It reads global source metadata, a complete canonical Journal and, for Deferred,
Outcome data before forming a safe projection. Its source reader has no tenant
scope, and its result identity has no target TenantRef. Existing subject/tenant
readers cannot tenant-scope the whole aggregate without new source contracts.
An extra subject-not-found filter would also hide the existing dangling Dead
Letter integrity error. Current surfaces use local OS or enable/session gates,
not Application User/Tenant authorization.

Its fixed Diagnostics messages do not make original exception objects safe:
readerFailure retains an original Throwable argument, and raw-record/projector
frames can retain protected data. The existing result/type/code contract does
not promise exception object identity. Copying D2J's same-object release would
either expose those frames or require a broader inherited annotation change.

## Decision

Add exactly one latent audited global aggregate adapter and one server UUIDv7
execution source. Construct the existing concrete query from its three reader
interfaces; keep its algorithms, safe projection and runtime wiring unchanged.
Use the existing typed Diagnostics authorizer before every Diagnostics Store
read. Non-null requester tenants are unavailable without invoking that policy
or reader. Null denotes this privileged global Diagnostics scope only; it grants
no permission by itself. The unchanged default policy remains unavailable.
Parent activation must bind an explicitly trusted local policy behind the real
CLI/Viewer gates; no permissive production policy or new tenant surface is added.

Commit requested first, inspect idle state after authorization before the query,
and commit one selected terminal before releasing the result or error. Preserve
the safe result object. Preserve a query failure's enum only, then reconstruct a
fresh existing Diagnostics exception after unavailable succeeds. Authorizer
throwables are storage failures; query Diagnostics codes keep their three-way
mapping. This is the explicit Diagnostics-only exception to D155's reader-child
same-exception rule; other reader contracts are unchanged.

Both Audit writes retain their separate Store-owned roots. No transaction repair,
terminal retry or result release after failed delivery is permitted. Trusted
execution uses operation_diagnostics_read and one validated UUIDv7 generation
per call, separate from request nonce and Store identities.

## Alternatives Rejected

- Inferring tenant isolation from null or a safe result: the source aggregate
  has no such contract and deliberately serves privileged global diagnostics.
- Adding a Status subject filter: changes existing availability/integrity and
  leaves global Diagnostics source reads outside that supposed tenant boundary.
- Returning original Diagnostics exceptions: preserves unsafe inherited frames.
- Annotating every legacy aggregate/projector/reader frame in this child: broader
  Product scope than needed to preserve the actual Diagnostics type/code contract.
- Automatically allowing CLI/viewer-shaped references: request values or a token
  are not proof that the real local infrastructure gate has authorized access.

## Consequences

The adapter is ready for reviewed parent composition but does not activate Audit
on current CLI/viewer routes. Tenant-scoped Diagnostics would require a separate
contract. Existing D2J Product, current default query and public documentation
remain unchanged. Exact9-path implementation, actual trace canaries, transaction
visibility, scoped architecture/package controls and frozen full-suite evidence
are required before local acceptance. Broader1.3 and delivery gates remain.
