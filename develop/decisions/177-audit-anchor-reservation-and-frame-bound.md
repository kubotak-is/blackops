# D177: Audit Anchor Reservation and Frame Bound

Status: Decided — bounded F4 choices adopted; complete implementation contract pending

## Context

F2 permits one complete checkpoint per segment, while pure F3 fixes one intent's
request, chain and receipt identity. Neither by itself selects the local reservation
cardinality across destinations. Recovery must find existing work after a config
change before generating any replacement identity or request. Current v1 already
requires a continued partition chain to retain its start-boundary store/object.
The separate signed provenance design also needs an explicit frame byte bound.

## Decision

Root adopts [specification127](../spec/127-audit-anchor-durable-external.md)'s native
one-reservation-per-checkpoint rule, immutable exact chain identity, checkpoint-first
authenticated recovery and inclusive8192-byte canonical frame cap on2026-10-11.
The independently reviewed conservative bound is8025 bytes. Envelope/artifact
bounds and complete parser/history/resource qualification remain separate.

The broader `(checkpointId,C)` creation key is not selected for the ordinary1.3
producer. One checkpoint reservation provides an unambiguous recovery/receipt
association across configuration changes without introducing fan-out or a new
destination selector. Exact-C chain coordination and unique accepted object
versions are still necessary for different checkpoints. Terminal/attempt limits
cannot be reset by replacing a reservation or changing its destination.

The choice preserves current v1's same-C continued-chain rule. A future signed
bridge/versioned migration remains a separate future capability, not implicit
permission in a broader SQL key. No existing A5 wire, F1/F2 Acceptance, Stable
authority, quality budget or full1.3 obligation changes.

## Review and boundary

Independent slot/frame review729a27c60ff6702ff8d0c7fedf20a95b592dcbe3728788e97798428a7a29a120
and inputs6460f785cf94626fd946c9c4eb2642a2fa9e79f91fb6ee8f1f93639a41c234cb
recommend these exact choices. SF-01 required carrying the current-v1 chain
restriction into the adoption; specification127 includes it explicitly.
The review does not qualify a parser, SQL, provider or F5 implementation.

[F4 Task](../orchestration/tasks/P23-003F4-audit-anchor-durable-external.md) remains
Draft for Product issuance. Root must still freeze the complete local14 contract,
finite authenticated provenance/provider dependencies and verification/ownership.
F3 remains the active implementation task. Full F4/F5/F6, D-control/G, ordinary
integration, Documentation and release/delivery gates remain required.
