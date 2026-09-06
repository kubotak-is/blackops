# D157: Tenant-scoped Status Source Prerequisite

Status: Authority reviewed Green — Product progress is recorded in the D2S1 Report/STATE; no activation.

## Context

The user approved a separate prerequisite after General and Transaction
research independently found that a PHP tenant guard cannot satisfy
Specification99's SQL isolation contract. The legacy Status source's subject
and detail helpers are unscoped; scoped subject/Journal/Outcome primitives
exist but do not yet form a complete Status path. D2S's initial spec107/D156
adapter candidate is therefore incomplete and not implementation authority.

## Decision Proposed

Adopt Specification108 for P23-003D2S1. Add an Internal scoped-source interface
and per-call tenant-bound view, plus additive PostgreSQL source/reader methods.
Reuse the existing classification algorithm through an explicit scope marker;
global-null and legacy-unscoped are different states. Recheck scoped subject
inside the source-owned read-only snapshot before any protected decode.

Foreign rows are absent in this view. Do not inspect them through an unscoped
probe to diagnose global corruption. Preserve eligible-row lifecycle and safe
error behavior; explicitly map subject disappearance/move to unavailable and
same-scope authorization-subject drift to Integrity before decode.

Keep existing public API and legacy unscoped methods/composition behavior.
Current Applications are not silently switched. This latent prerequisite
creates no Audit events and no trusted execution source; those belong to the
subsequent revised D2S adapter. Parent D remains incomplete.

## Rejected Alternatives

- Guard-only or subject-only fix: leaves unqualified detail queries.
- Nullable tenant as an optional unscoped sentinel: global queries could read
  every tenant when the tenant value is null.
- Per-query mutable source property: interleaved/reused sources can leak scope.
- Duplicating the complete lifecycle projection: creates divergent state/error
  behavior and a second long maintenance path.
- Discovering foreign rows with unscoped existence queries: breaks the same
  storage invariant and the unavailable indistinguishability boundary.
- Activating the new source in current HTTP composition: outside this
  prerequisite and before the audited adapter is Accepted.

## Consequences

The prerequisite requires real PostgreSQL SQL/parameter, snapshot/race,
before-decode and legacy regression evidence. It is not a claim of a live
exploit or a repair of every existing unscoped reader. D2S can be revised only
after the scoped source is independently reviewed and Accepted. Other reader
children and D-integration remain separately scoped work.
