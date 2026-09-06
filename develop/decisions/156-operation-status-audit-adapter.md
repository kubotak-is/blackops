# D156: Operation Status Audit Adapter

Status: Incomplete proposal — tenant-scoped Status-source prerequisite required; no Product work.

## Current Disposition

The user approved the separate prerequisite; its active packet is
P23-003D2S1 under spec108/D157. This adapter candidate waits for its Acceptance.

The initial three-type adapter proposal below is not accepted. Subsequent
source review found that its PHP authorizer guard cannot satisfy
Specification99's tenant-qualified SQL requirement. Both subject and detail
sources need a separately reviewed scoped read path. Specification107 carries
the blocking correction; its initial scope/count/API are not worker authority.
No existing reader, source, public API or runtime is changed by this Decision.

## Context

D2 is Accepted at `0cd699e5e1d031228bc4a05767164a8eb268a004`; its final
same-SHA package export is Green in the prior handoff. D154 requires a separate
most-specific authority for each reader child. D2S owns Status only.

Current source evidence:

- DefaultOperationStatusQuery reads restricted subject, authorizes, then reads
  detail. It already maps errors to four value-free public query codes.
- Its tenant matrix intentionally trusts a permissive Application authorizer,
  including cross-tenant input. An outer result-only decorator cannot enforce
  D147/D155's stronger audited cross-tenant boundary.
- Expired is returned only after authorized detail access and becomes410;
  unavailable becomes404. Mapping Expired to unavailable would hide a real
  authorized metadata disclosure from the audit semantics.
- The PostgreSQL detail source owns a read-only snapshot transaction. The new
  adapter must not keep an Audit write root open while that snapshot executes.
- The current Status HTTP path has no trusted execution ID; nullable Operation
  scope and caller headers cannot supply a safe substitute.

## Proposed Decision

The initial candidate proposed Specification107 as its contract. It is now
blocked pending the source prerequisite. The candidate adds a latent audited query which
constructs the unchanged Default Query with a mandatory tenant-guard authorizer.
No legacy reader or current runtime composition changes. Guard null/type/id
equality before Application authorization and detail access.

Allocate one fresh server-owned UUIDv7 per Status read with exact execution
type `operation_status_read`. This explicitly specializes D155's enumeration;
it identifies the new read execution itself, not an invented missing HTTP or
Operation scope. It is distinct from the request nonce and Store-owned UUIDs.

Keep requested before lookup, one selected terminal before release, and safe
storage failure precedence. Found and Expired use accessed; unavailable and
query failures use unavailable. Preserve result and contracted exception
identity after successful terminal delivery. Validate Connection state without
owning transaction repair; only a same-Connection graph is supported.

## Alternatives Rejected

- Decorating an already-built Default Query: cannot enforce tenant isolation
  before protected detail read.
- Copying Default Query logic: creates a second result/error implementation.
- Modifying the current Default or HTTP composer: activates behavior outside
  this bounded child and changes legacy policy behavior.
- Using target/actor/header/nullable scope as execution fallback: no trusted
  per-read provenance and potential disclosure or identity reuse.
- Wrapping the whole query in an Audit transaction: conflicts with the existing
  source-owned read-only snapshot and required separately committed events.

## Consequences

New audited calls can be tested without enabling Audit in existing Applications.
The supported graph still needs explicit same-Connection, trusted build/runtime
and UUID-source wiring at D-integration. D2S alone proves no HTTP activation,
cross-Connection enforcement, public Documentation delivery or release.
Parent D remains incomplete after D2S.
