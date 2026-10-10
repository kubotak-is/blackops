# Specification 127: Durable Audit Anchor and External Effect

Status: Bounded reservation/frame choices adopted; complete F4 issuance pending;1.3 unreleased

## Authority and scope

This specification refines the mandatory remaining F4 work in current
[105](105-blackops-1-3-capability-contract.md),
[106](106-phase-23-delivery-plan.md) and
[126](126-audit-anchor-intent-state.md). It adopts the two reviewed choices below.
It is not yet the complete local fourteen-operation SQL/provider contract or an
implementation allowlist. [D177](../decisions/177-audit-anchor-reservation-and-frame-bound.md)
records the decision; [F4 Task](../orchestration/tasks/P23-003F4-audit-anchor-durable-external.md)
retains the remaining issuance and Acceptance requirements.

## Reservation and recovery identity

Within one installed local store/schema, reserve at most one immutable AnchorIntent
per checkpoint, enforced by native `UNIQUE(checkpoint_id)`. The external chain
identity C is the exact `(partitionId,anchorStoreRef,anchorObjectRef)` triple and
is immutable associated data of that reservation.

Lookup existing work by checkpoint identity before selecting a destination, intent
or receipt ID, raw idempotency key, conditional token or predecessor. Authenticate
the whole existing reservation/frame association before using its selected C or
inputs. Changed configuration does not create a second reservation for the same
checkpoint. If the current authorization/trust contract disallows further work,
preserve the reservation and use the explicitly defined failure/recovery path;
never replace its immutable inputs or move to a fallback destination.

Retries and restart recovery retain the original intent, request, predecessor,
conditional token, idempotency association and reserved receipt ID. Attempt32 and
terminal rejection cannot be bypassed through delete/reinsert, a new intent or a
new C. Expected predecessor, revision, attempt, token, hash and receipt ID are not
additional recovery-key discriminators. This rule does not add mirrored output,
fan-out, backfill, target migration or history import.

Successive checkpoints in one current-v1 included or continued partition chain
retain the selected C, exactly as105's start-boundary rule requires. Object versions
advance by exactly one and receipt/checkpoint predecessor graphs must agree.
Changing store/object mid-chain fails v1; any future signed bridge/versioned
migration needs a separate contract. This is not a newly reduced verifier scope.

The separate chain coordination row is keyed by exact C. Historical reservations
are not `UNIQUE(C)`, because successive checkpoints use the same chain. All calls
that coordinate different checkpoints on that chain require the exact-C lock;
checkpoint uniqueness alone is insufficient.

## Native and authenticated associations

The complete future schema/API must preserve these joins:

- Reservation checkpoint ID references the existing complete
  `audit_checkpoints(checkpoint_id)` primary key. Join through the existing
  preparation to recover partition/segment and compare the complete signed F1
  checkpoint, reservation and frame binding. The existing schema has no fictional
  checkpoint/partition/segment composite key. A foreign key alone does not
  authenticate those bytes or ownership.
- The reservation retains exact C and its chain-row association. A non-genesis
  predecessor is selected by `(C,expectedPredecessorVersion)`, never version alone
  or the latest receipt. The predecessor's complete checkpoint-byte hash equals
  `expectedPredecessorHash`; its receipt hash equals binding
  `previousAnchorReceiptHash`. The distinct domains and null-genesis groups remain.
- One reserved receipt ID and at most one complete receipt belong to an intent.
  A composite `(intentId,anchorReceiptId)` association targets an explicitly unique
  reservation pair. Preserve unique reserved receipt identity and unique
  `(C,acceptedObjectVersion)` among complete receipts. Receipt insertion and its
  corresponding A5 persistence revision must commit atomically.
- Authenticate reservation, signed revision history, actual named-claim origin
  and current head as one association. CAS compares expected local revision,
  state hash, NULL-aware fencing token and prior signed revision digest. Uniqueness
  and shape validation cannot substitute for authenticated history or current trust.

Native lock order, isolation, conflict visibility and finite error mapping must be
frozen before implementation. A unique winner invisible in a stale transaction
does not prove changed immutable identity. Preserve the accepted F2 boundary:
discard the failed root/connection as required, then recover on a fresh root;
never retry inside an ambiguous transaction. This choice does not prove no-N+1
external I/O, committed marker-before-call, receipt-before-ack or seal authority.

## Bounded canonical frame admission

The complete canonical `{intent,binding,leaseOrigin}` frame is at most **8192 UTF-8
bytes inclusive**. Enforce the byte bound before a general recursive parser or
trust in any claimed length. A separate provenance envelope's declared frame byte
length must equal the actual bounded canonical frame bytes.

This cap includes the outer braces and escaped string bytes. It excludes the
separate signature/provenance envelope, raw idempotency material, complete
checkpoint/receipt artifacts and transport framing. It neither enlarges another
envelope's32-member limit nor imposes this cap on those other objects.

Current126 grammar gives a conservative8025-byte upper bound: intent6022,
binding731, non-null origin1235 and outer structure37. Null origin gives6794.
A256-byte generic admitted string occupies at most514 canonical JSON bytes:
quotes/backslashes double, while forbidden Cc characters cannot add control
escapes. These maxima need not coexist in a valid state;8192 is a safe inclusive
bound, not a claim that a valid state reaches8025.

Admission still requires exact outer3/intent38/binding4/origin6 member sets,
native scalar grammar, text/NFC, duplicate-key rejection, canonical bytes and
complete state/binding/origin validation and authentication. With non-null origin,
the frame has four objects, two object levels,51 member occurrences and48 scalar
leaves. These facts guide the required parser proof; arithmetic is not that proof.
No lifetime history/revision cap follows from a per-frame bound. Exact parser,
streaming history, pagination/deadlines and total resource contracts remain to be
specified and tested without an arbitrary history truncation.

## Remaining issuance and verification

F3 Acceptance is required before the F4 implementation uses its state family.
Before issuing Product work, freeze exact files/APIs, all fourteen local operations,
SQL/schema/migration rules, locks/roots, signed reservation/revision/receipt joins,
bounded parser/history resources, actual external provider and F5 trust/provenance
dependencies. Do not accept authenticated restoration on a fake signer, unsigned
row flag or not-yet-implemented trust port. The concrete prerequisite order and
atomic reservation/genesis crash boundary require their own adopted contracts.

Native concurrent winner/visibility, crash/ambiguous-commit/restart, lease/token,
origin/history tamper, exact-C predecessor/receipt joins, parser boundaries and
external lookup/conditional-write/no-N+1 behavior require meaningful qualification.
F5 signing/current trust/control/ordinary release/seal, F6 complete verification,
F -> D-control -> G and every parent1.3 release/delivery gate remain mandatory.
No runtime, migration, public capability, command or release authority changes by
adopting these bounded planning choices.
