# Audit AnchorIntent state

The internal AnchorIntent implementation is the reviewed pure A5 state-machine
candidate for the unreleased BlackOps 1.3 work. After the assigned gates and
independent review pass, it will accept the exact closed 38-field
`audit_anchor_intent` object, compute the domain-separated state and
idempotency hashes, and advance each valid operation by one expected-version
CAS revision.

The frame keeps three separate values: the intent wire, an immutable binding
for the segment, predecessor receipt, checkpoint signing time and reserved
receipt ID, and the origin of the installed claim. The binding and origin are
internal carriers. A valid shape, hash chain, or constructor does not
authenticate durable history. The carriers are accepted only as supplied values
from an already authenticated association; constructing or restoring one is not
a capability or proof of ownership. The future F4 storage owner must
authenticate the complete association and its revision history when restoring
it. The state contract is defined in the [A5 specification](../../develop/spec/126-audit-anchor-intent-state.md)
and the [boundary decision](../../develop/decisions/176-audit-anchor-intent-state.md).

The implementation accepts trusted timestamps and caller-supplied fencing
tokens. It does not read a clock, generate UUIDs or tokens, access storage,
call an external service, sign a receipt, or activate a runtime path. Receipt
input derivation reuses the existing unsigned F1 receipt codec and records the
two distinct internal receipt hashes before a later caller persists the signed
receipt and acknowledges it.

The six states and fourteen A5 operations remain closed. Pre-call lookup,
explicit absence, the call-start transition, post-call reconciliation,
attempt-32 exhaustion, lease expiry, receipt persistence and acknowledgement
all reject wrong-purpose, stale, malformed, or out-of-order inputs without a
revision. F3 builds the call-start revision; F4 must commit it before the
external call. Retry schedules preserve the supplied six fractional timestamp
digits: a retry is due when `now >= nextAvailableAt`, a lease is active only
when `now < leaseExpiresAt`, and equality or later time is expired and enters
the matching-token reclaim rule.

Receipt input persistence also enforces
`checkpointSignedAt <= acceptedAnchoredAt <= receiptSignedAt`; the checkpoint
chronology and immutable binding remain part of the [checkpoint persistence
contract](audit-checkpoint-persistence.md).

F4 still owns durable intent and lease CAS, authenticated restore, restart and
response-loss recovery against real storage and external effects. F5 still
owns signing-release authorization, current trust and freshness, control
provenance, and seal integration. F6 still owns complete verification. This
pure internal prerequisite makes no Stable 1.2 or released 1.3 capability
claim.
