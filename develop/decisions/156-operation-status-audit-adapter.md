# D156: Operation Status Audit Adapter

Status: Decided after independent authority review; Product implementation pending.

## Context

D2 session foundation is Accepted at `0cd699e5e1d031228bc4a05767164a8eb268a004`.
The original adapter candidate could not satisfy tenant-qualified SQL with a
PHP authorizer guard alone. D2S1 resolved that prerequisite at
`70f9c4733bed8bb0960165cddf8c451b78b96184`, including clean final-SHA export.
Specification108 now provides the scoped source and immutable tenant-bound view.
The previous candidate in this Decision is superseded by the following contract.

## Decision

Specification107 is the most-specific Status adapter contract. Add an Internal,
unactivated audited query using the Accepted scoped source. Every read creates
its own tenant-bound view and unchanged Default Query, after requested commits.
The same nullable tenant is passed to Audit, the source and authorization; null
is global scope. No mutable current-tenant property or unscoped fallback exists.

The only existing Product edit is SensitiveParameter on all14 Default Query
parameters, preserving its algorithm/signatures/results/exception identity.
A preflight PHP8.5.7 probe with exception arguments enabled confirmed that its
old inner frames otherwise expose Operation ID/actor/tenant despite safe codes
and a null previous cause. The adapter cannot erase a trace from an identical
exception afterward. Explicit annotation-only scope and enabled-argument
canaries close that inherited path without weakening the safety contract.
Application-owned unannotated outer frames remain the application's boundary.

Retain a mandatory tenant authorizer guard. The scoped PostgreSQL source filters
foreign subjects first; the guard independently rejects a foreign subject from
any scoped-source implementation before the Application authorizer or detail.
It compares null presence and both type/id strings strictly. It supplements,
and does not substitute for, SQL predicates and before-decode validation.

Allocate one server-owned UUIDv7 per read, with execution type
`operation_status_read`. This explicitly specializes D155's trusted-source
enumeration: it identifies this newly started Status read, not a replacement
for a missing HTTP request ID or Operation scope. Caller headers, target IDs,
principal/tenant values, config and ambient scope never supply that identity.
D2 still owns the independent 16-byte request nonce and single Clock capture;
the canonical Store owns its own record/segment UUIDs.

Preserve three distinct transaction roots for a detailed read: requested write
commit, source-owned repeatable-read/read-only snapshot, terminal write commit.
Subject lookup and authorization happen between the first two roots. Source
recheck/decode completes before terminal; the result is released only after
terminal commit and idle-state validation. All three use the same Connection
under trusted composition, which the adapter cannot introspect across private
dependencies. Actual Application composition remains a later integration gate.

Found and authorized Expired select accessed. Unavailable and query failures
select unavailable. Preserve Default's result and contracted exception after
successful terminal delivery. A terminal failure overrides either with safe
Storage, never retries or changes terminal kind, and never repairs another
owner's transaction. Requested-only records after interruption are permitted.

Permit only the new query's exact per-call bound-view construction and exact
D2 session/execution references. Preserve the Accepted D2/D2S1 source implementations,
SQL, codecs, goldens and mutation controls. Package inventory becomes 90 paths
from 87; four new omission/reversal controls raise 35 controls to 39.

## Alternatives Rejected

- Guard-only or unscoped delegate: cannot prove the required SQL boundary.
- A tenant-bound Default Query stored in the constructor: cannot safely reuse
  one query for multiple request tenants.
- Copying Default's classification/error algorithm: creates a second behavior
  implementation and diverges from existing public Status semantics.
- Caller/target/ambient-derived execution IDs: cannot establish per-read server
  provenance and may disclose or reuse identity.
- One outer Audit transaction: conflicts with the independent read snapshot
  and separately committed requested/terminal events.
- Enabling the adapter in current HTTP composition: precedes the remaining
  parent integration work and changes released behavior outside this Task.

## Consequences

This unactivated adapter is unreleased 1.3 work; current Stable remains 1.2.1.
It proves neither runtime activation nor an enforced connection identity for
arbitrary manual compositions. D2J/D2X and other adapters remain separate.
D-integration must supply trusted runtime/build/UUID dependencies and prove
same-Connection wiring. Parent D and the 1.3 release remain incomplete.
