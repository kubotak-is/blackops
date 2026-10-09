# Specification 126: Audit AnchorIntent State

Status: Adopted — pure F3 implementation contract; BlackOps1.3 unreleased

This document governs the pure state prerequisite of
[P23-003F3](../orchestration/tasks/P23-003F3-audit-anchor-intent-state.md).
The current A5 blocks of specifications105/106 remain the state-machine authority;
historical corrections elsewhere in those files do not replace that closed A5.
The issued Task defines Product ownership; accepted F1/F2 are unchanged.

## 1. Goal and remaining-F dependency

Implement deterministic, side-effect-free validation and progression of the full
current AnchorIntent state machine, including its canonical hashes, exact retry
schedule, original claim purpose and immutable preparation binding. No subset of
the fourteen operations can qualify the state prerequisite.

The remaining F delivery order is pure F3 state; F4 durable intent/lease/receipt
and external-adapter primitives; F5 actual signing-release authorization, online
trust/key acquisition, authenticated control provenance and final seal integration;
F6 complete record/checkpoint/anchor/freshness verification and final F qualification.
The F5 current-context/freshness check must exist before its seal CAS and cannot
depend on a later F6 acceptance. F signing-release and trusted-control work remains
mandatory within that integration, not an optional child or release waiver.
Actual D-control consumption follows Accepted F; G follows both. None of these
internal prerequisite acceptances alone authorizes a live sign/anchor/seal path.

## 2. Adopted Root choices

The wire remains the exact38-member AnchorIntent v1 object in current A5. No
operation discriminator, control bit, lease-purpose field or chain sidecar is
inserted into that object, its stateHash domain or any public receipt.

The pure machine carries three distinct members: `intent`, `binding` and
`leaseOrigin`. This is a bounded internal carrier, not a public serialized wire
or a capability proving authentication. The later F4 trusted storage owner must
authenticate the complete association on restore. Self-consistent hashes and a
valid constructor do not prove origin.

`binding` is immutable and contains exactly `segmentId`,
`previousAnchorReceiptHash`, `checkpointSignedAt`, and `anchorReceiptId`.
The trusted producer supplies these together with the intent's immutable identity,
checkpoint and exact anchor triple. Root chooses to allocate the future receipt
ID once before intent genesis and persist it in this binding. The A5 intent still
has a null receipt ID and null segment/previous-receipt members until external
acceptance; that transition copies the previously bound values. No retry generates
replacement IDs or changes the predecessor. F3 performs no UUID or Clock access.

`leaseOrigin` is null exactly when the A5 lease is null. Otherwise it contains
exactly `operation`, `intentId`, `attempt`, `leaseFencingToken`, `claimVersion`
and `claimStateHash`. `operation` is one of the six claim-operation names from
A5. A named claim derives its own origin from the actual resulting intent; no
caller-selected origin is accepted by that claim. In-lease lookup-absent and
call-start retain the original origin; settlement/reclaim clears it with the
lease. Original external-submission/pre-call-retry ownership remains distinct
from later external-reconciliation ownership even when their A5 snapshots have
the same post-call phase. Token string formatting carries no hidden purpose.

Restore validates closed shape, scalar grammar, state/lease/binding consistency
and all locally provable relations. It cannot prove a forged origin record is
the real committed history. That proof is a mandatory authenticated F4 sidecar/
revision-history responsibility; F3 tests must not claim to establish it. The
pure transition rejects wrong-purpose operations using the original origin that
the caller supplied under this explicit precondition. A future caller may not
construct a new origin from the current phase merely to make a transition pass.

## 3. Mandatory current A5 semantics

All six states, fourteen operations,38 closed intent fields, closed external
result and reconciliation shapes, hash domains, one-step version/hash linkage,
attempt0/1/31/32 limits, pending-before-absent-before-call ordering, all six retry
scheduling branches, inclusive due/exclusive active-lease boundaries, immutable
receipt inputs and failure-without-revision rules remain exact current A5.
F3 uses supplied trusted timestamps/configuration; it never samples time itself.
The later caller captures one Clock value per attempted transition and one retry
configuration value when scheduling. No adapter supplies nextAvailableAt.

Canonical checkpoint/receipt validation and unsigned-body formulas reuse F1;
generic IDs are not silently restricted to UUID. Segment IDs retain their existing
UUIDv7 grammar. Receipt bytes never have a second public wire. Internal
receiptPreimageHash and receiptBytesHash retain their two distinct A5 domains
over the same complete unsigned public receipt body. Neither substitutes for
checkpointBytesHash or the public anchorReceiptHash.

The exact class/API inventory and meaningful pure proof matrix are in sections6–9;
the Task fixes file ownership and commands. No runtime,
native persistence, actual external authentication, commit-before-call or durable
acknowledgement result is claimed by this specification or by a pure unit test.

## 4. Acceptance boundary

All current A5 mathematical obligations must be demonstrated with immutable
inputs and deterministic outputs, including byte-identical Node/PHP fixtures.
F4 retains native CAS/transactions, authenticated restore, real external lookup/
call ordering, response loss, restart and no-N+1 proofs; F5 retains actual ordinary
Required-before-release and finite control exemptions, current trust and seal.
Those obligations cannot be satisfied by F3 call counts or fabricated tokens.

No new public API, configuration, command, capability, Stable documentation claim,
SQL schema, provider, seed access, signer call, network, filesystem/runtime hook,
canonical append or seal write belongs to F3. Exact helper/caller/guard/package
changes require the reviewed inventory rather than a namespace-wide exemption.

## 5. Issuance status

The source inventoryf3020cbd and its50 selected inputs supplied this candidate's
exact API/decomposition and verification proposal. Independent Transaction/Security
authority review r1 is Green for the pure contract; bounded r2 closes AR1-01 and
is Green/Ready. Root adopts that authority on2026-10-09 and issues the exact Task
with its owned runtime lease. This adoption is not implementation Acceptance.
Parent006 remains active through the entire1.3 release objective.

## 6. Exact admission and internal carrier

Root freezes these numeric/API choices: generic IDs,
refs, raw idempotency keys and opaque tokens are1..256 UTF-8 bytes, NFC, without
Cc/Cf, with no normalization or trimming. Lease tokens do not require UUID.
Partitions are exactly system:global or tenant: plus64 lowercase hex. Segment
IDs use the existing canonical lowercase UUIDv7 grammar. Hashes are64 lowercase
hex. Versions/positive counters are native integers through9007199254740991;
attempt is exactly0..32. No strings, booleans or floats substitute for integers.
Timestamp grammar is the existing AuditRecordTime grammar.

Array input admits the exact key set independent of PHP insertion order, then
copies validated scalar values into the A5 presentation order for toArray().
Count-first closed admission precedes iteration/canonicalization. No raw JSON
decoder is introduced. The only nested arrays are the exact internal carrier,
its three members and the closed reconciliation foundObject. No caller references,
objects, callbacks or recursive maps are retained. All byte hashes use hash_equals
when comparing an expected value.

The exact38 A5 intent fields are:

```text
schemaVersion,kind,intentId,state,partitionId,checkpointId,checkpointHash,
checkpointBytesHash,anchorStoreRef,anchorObjectRef,expectedPredecessorVersion,
expectedPredecessorHash,conditionalCreateToken,idempotencyKeyHash,
acceptedObjectVersion,acceptedObjectHash,acceptedAnchoredAt,anchorReceiptId,
segmentId,previousAnchorReceiptHash,receiptSigningKeyRef,receiptSignedAt,
receiptPreimageHash,receiptBytesHash,anchorReceiptHash,externalCallPhase,
preCallClaimMode,preCallLookupStatus,leaseFencingToken,leaseExpiresAt,attempt,
nextAvailableAt,terminalReasonCode,version,previousStateHash,stateHash,
updatedAt,acknowledgedAt
```

Intent genesis and external-chain genesis differ. Every new intent has version1;
its predecessor pair can refer to an existing external object. The predecessor
version/hash are both null or both non-null. The former requires a null binding
previousAnchorReceiptHash and eventual object version1; the latter requires a
non-null binding previous receipt hash and object version predecessor+1. The
external predecessor object hash and previous receipt hash are distinct.

The ten-field creation request contains exactly intentId, partitionId,
checkpointId, checkpointHash, checkpointBytesHash, anchorStoreRef, anchorObjectRef,
expectedPredecessorVersion, expectedPredecessorHash and conditionalCreateToken.
The raw idempotency key is a separate SensitiveParameter, hashed by the exact
A5 LP formula and never retained. The supplied binding joins the selected
checkpoint signing chronology and receipt identity to this exact request.
create requires expectation version0/hash null/token null. It constructs all
A5 genesis nulls/time/hash; it cannot determine whether a DB row already exists.

The three-member frame restores an already authenticated complete association,
not three independently authenticated fragments. Every intent ID/attempt/token
in leaseOrigin must equal the current intent. Origin claimVersion is positive,
no larger than current version; claimStateHash is a digest. At the claim result
they equal current version/hash. A pending original external claim has delta0;
its absent observation has delta1 and previousStateHash=claimStateHash; its
post-call marker has delta2. An external-reconciliation or internal claim has
delta0 until settlement. The original claim name must match the A5 mode/state:
external_submission_claim/new_attempt, pre_call_retry_claim/retry_same_attempt,
external_reconciliation_claim/post_call_uncertain, and each internal claim's
own receipt state. Original external owners remain valid at their later
post-call marker. In-lease intermediate hash-chain authentication across delta2
is F4's history responsibility; no missing intermediate hash is invented here.
Unleased frames require null origin, and accepted intent chain/receipt-ID members
must equal the immutable binding.

Restore admits an installed lease that has expired relative to a later caller's
now; it performs no wall-clock read. The current A5 matrix and supplied now decide
active versus expired. No additional provider-clock synchronization rule is added.
Input-persist freezes receiptSignedAt=transitionNow and requires it to be no
earlier than acceptedAnchoredAt. Acknowledgement-persist freezes
acknowledgedAt=transitionNow. All transitions still require now>=old updatedAt.
The authenticated checkpoint chronology requires checkpointSignedAt<=acceptedAnchoredAt;
receipt chronology requires acceptedAnchoredAt<=receiptSignedAt. F3 does not
reinterpret either timestamp as the physical later signing instant.

Repeated unavailable lookups remain scheduled at the same external attempt.
The32 bound limits external submissions, not the count of all unavailable
observations. Do not terminalize uncertainty without the explicit current A5
absence rule or invent an additional retry limit; safe version capacity remains
finite and overflow returns no revision.

## 7. Exact implementation family and callable boundary

All21 new Product files are under src/Internal/Audit/Anchor. They are internal,
with zero outside Production callers in F3. Non-enum classes are final; immutable
carriers/configuration are readonly. Every carrier hides values in
SensitiveParameterValue and exposes data only through its explicit projections.
__debugInfo and __serialize return empty arrays; __unserialize rejects with a
fresh value-free F3 exception. No public fields expose raw carrier data.

| Type | Frozen responsibility and entry API |
| --- | --- |
| AuditAnchorState | String enum: Prepared, ExternallyAccepted, ExternallyRejected, ReceiptInputsPersisted, ReceiptPersisted, Acknowledged with exact A5 values. |
| AuditAnchorFailure | String enum: ConfigurationInvalid, IntegrityInvalid, TransitionConflict, ResourceUnavailable with C1-style lowercase codes. |
| AuditAnchorException | constructor(AuditAnchorFailure), failure(), code(); fixed message Audit anchor state transition failed.; code0/null previous. |
| AuditAnchorConfiguration | constructor(mixed leaseDurationSeconds=300,mixed retryDelaySeconds=300); public readonly native ints after validation, each1..86400. Mixed admission makes wrong-type configuration a finite failure. No maxAttempts setting. |
| AuditAnchorIntent | Private construction; static fromArray(array):self; toArray():array, state():AuditAnchorState, version():int; closed shape/hash validation and detached projection. |
| AuditAnchorIntentShape | Count-first38-key/native scalar admission, normalized field order and delegation to the two state-shape helpers. |
| AuditAnchorPreparedShape | Exact prepared/rejected nullability and phase/mode/status/attempt/schedule/lease matrix. |
| AuditAnchorReceiptShape | Exact accepted/receipt/ack matrix, predecessor version/hash and receipt chronology/internal-hash consistency. |
| AuditAnchorCanonical | StateHash and raw-idempotency LP formulas; the two receipt internal typed-ENC formulas. No raw-key retention, no signer. |
| AuditAnchorTime | Sole Anchor owner calling AuditRecordTime::assertTimestamp; canonical supplied-time validation and checked integer-seconds addition. No Clock. |
| AuditAnchorBinding | Private construction; static fromArray(array):self; toArray():array for exact four immutable members. |
| AuditAnchorExpectation | constructor(int version,?string stateHash,?string token); toArray():array with those three members. CAS data only. Zero version requires null hash/token; positive requires digest hash. |
| AuditAnchorLeaseOrigin | Private construction; static fromArray(array):self, fromClaim(string operation,AuditAnchorIntent intent):self, toArray():array. Six closed claim names only; fromClaim is called solely by the named claim revision path using fixed literals. |
| AuditAnchorFrame | Private construction; static create(array request,AuditAnchorBinding binding,string rawIdempotencyKey,AuditAnchorExpectation expectation,string trustedNow):self; static restore(array):self; intent(), binding(), leaseOrigin(), toArray(). Includes carrier consistency, not origin authentication. |
| AuditAnchorTransitionGuard | Shared expectation/hash/token/time/capacity checks and one-step revision construction; exact known callers only, no arbitrary operation dispatch or callbacks. |
| AuditAnchorClaims | constructor(AuditAnchorConfiguration); externalSubmission(), preCallRetry(), externalReconciliation() using the common claim signature below. |
| AuditAnchorPreCallTransition | constructor(AuditAnchorConfiguration); lookup(), callStart(), reclaim() for original external owner/pre-call states. |
| AuditAnchorPostCallTransition | constructor(AuditAnchorConfiguration); result(), reconcile(), reclaim() for the exact original/reconciliation owner and post-call phase. |
| AuditAnchorReceiptTransition | constructor(AuditAnchorConfiguration); inputClaim(), persistClaim(), acknowledgementClaim(), inputPersist(), persist(), acknowledge(), reclaim(). |
| AuditAnchorExternalOutcome | Closed8 external-result and closed5 reconciliation/closed6 found-object validation; exact state/checkpoint/anchor binding. No authentication or I/O claim. |
| AuditAnchorReceiptBody | Builds16-field unsigned receipt and calls only F1 AuditAnchorReceiptCodec::signingInput for its canonical bytes/public semantic hash; validates/derives internal receipt hashes. No signer/verifier/private F1 helper access. |

Every named claim signature is
(frame:AuditAnchorFrame,expectation:AuditAnchorExpectation,newToken:string,
trustedNow:string):AuditAnchorFrame. A new token is supplied by the future
trusted caller; F3 neither generates it nor proves store-wide uniqueness.
The claim must not reuse a token already installed in its input.

lookup/result/reconcile take
(frame,expectation,outcome:array,trustedNow):AuditAnchorFrame.
The distinct entry determines which exact external wire is admitted. callStart,
each reclaim and acknowledge take (frame,expectation,trustedNow):AuditAnchorFrame.
inputPersist takes (frame,expectation,signingKeyRef:string,trustedNow):AuditAnchorFrame
and derives the unsigned receipt and both internal hashes itself; it accepts
neither caller-provided receipt hashes nor a replacement receipt ID/time.
persist takes (frame,expectation,anchorReceiptHash:string,trustedNow):AuditAnchorFrame
and compares that hash to the fixed unsigned receipt's F1 semantic hash. This
calculation assumes actual complete signed receipt durability under the later
caller; it does not perform or prove that persistence.

The common guard may build revisions only for these named entries. It rejects
changes to immutable identity/checkpoint/store/object/predecessor/key-hash/token
fields and owns version+1, previousStateHash, stateHash and updatedAt. A caller
cannot submit those computed fields as a change set. Lease origin issuance uses
the fixed six claim names from the named methods; intermediate transitions
preserve it and lease-clearing transitions clear it. Helper methods are bounded
implementation details, not a new outside-call surface. Final exact callable/
dependency maps must match the implemented candidate and be independently
reviewed. Each type has at most10 own methods and ordinary methods at most5
arguments; no new lint expectation, baseline or threshold exception is allowed.
Measured need for more helpers returns to Root before adding files.

Only AuditAnchorTime may call the existing Core time validator. Only
AuditAnchorReceiptBody may call F1 signingInput, once per body derivation.
No other new F1 helper/signer/verifier/decoder caller is granted. Existing
AuditStringValidator, AuditJcsCodec and AuditLengthPrefix supply text/JCS/LP;
they are not broadened or modified. Existing Outbox types remain sealed and
are not dependencies. No constructors/callers of F2 or canonical append are added.

## 8. Finite failure and data protection

Every failure crossing the F3 boundary is freshly constructed with the fixed
message and no previous cause. All parameters carrying frames, arrays, IDs,
tokens, key refs, times or bytes are SensitiveParameter, including intermediate
helper boundaries. A lower exception/trace or raw rejected value is not retained.

| Failure | Meaning and precedence |
| --- | --- |
| ConfigurationInvalid | Bad lease/retry configuration at construction. |
| IntegrityInvalid | Malformed closed input, scalar/hash/body/binding inconsistency, invalid external outcome or receipt semantic mismatch. |
| TransitionConflict | Valid input with wrong state/phase/mode/status/purpose, expected version/hash/token mismatch, not-due claim, active/expired lease mismatch, invalid transition timestamp or time before old updatedAt. |
| ResourceUnavailable | Checked version/date arithmetic capacity or an unexpected internal construction failure after valid admission. No state/result is returned. |

At each public transition admit the existing typed frame and operation-specific
closed input, then expected version/hash, matching token/purpose and eligible
state/phase, then supplied-time/due/expiry checks, then checked capacity and a
single revision. Independent bad inputs are rejected before constructing outputs;
a wrong-purpose owner cannot be rescued by a valid result body. Exceptions from
lower validation/math are remapped without cause. No storage-domain result code,
G verifier result code or default successful output is introduced.

## 9. Discriminating proof and remaining durable obligations

The PHP suite must exercise every current A5 row plus unlisted combinations,
using actual outputs of the named claims rather than hand-built origin claims
for positive paths. At least one complete genesis->pending->absent->call-start->
accepted->receipt-input->receipt-persist->ack sequence and a lookup-found
acceptance path must bind all request/chain/receipt fields and each revision hash.

Required families include:

- All six claim origins, cross-purpose original-result/reconciliation negatives,
  wrong internal settlement, expected version/hash/token and origin substitutions.
  Restore self-consistency positives do not prove authenticated origin; a paired
  valid but substituted whole carrier explicitly demonstrates that F4 owns origin.
- Attempt0 genesis and positive null/future schedules; attempt1,31,32 and33 rejection;
  31 post-call absent->32 new claim;32 pre-call absent permits call-start;32
  post-call absent alone terminalizes. Unavailable32 remains reconciliation-only.
- Pending/absent/mode substitution, repeated lookup/call-start/result/settlement;
  expired pending and expired absent reclaim require new pending/absent before call.
- All six scheduling branches with1/300/86400 seconds; invalid0/86401/non-native
  configuration; exact2030-01-02T03:04:05.123456Z+300s golden; due and expiry
  -1us/equality/+1us; clock rollback; safe-version and four-digit-year overflow.
  A failure preserves input bytes and produces no frame.
- All internal receipt-state expiry/reclaim/new claim paths retain immutable
  inputs; receipt-key/time/body/segment/predecessor/hash substitutions reject.
  Both internal unsigned receipt hashes and public hash match independent vectors.
- Closed38/8/5/6 and carrier4/6 field sets, unknown nextAvailableAt from adapters,
  unsafe numbers/text, wrong nullability, reordered PHP keys, detached reference
  mutation, meaningful privacy canaries across messages/causes/traces/representation.
- Independent Node/PHP JCS/hash/schedule fixtures. Node uses native crypto/BigInt
  and an independently computed expected byte result, never a PHP-produced oracle.
  Run the checked fixture in both languages and retain exact bytes/digests.
- Exact source inventory and actual semantic architecture controls: wrong outside
  caller/type/method or dynamic alias/class-string, signer/Clock/DB/network/provider/
  Container/runtime activation, origin-selector bypass, direct append, extra wire
  field, disabled expectation, public marker and privacy
  regression are rejected. Harmless comments/strings remain positive controls.
- Actual package required-path list/order and an omission control for each21 new
  Product path, preserving every old control. Final committed export uses the
  accepted committed source and is not inferred from an uncommitted archive.

Pure F3 tests cannot establish actual fresh-token generation, uniqueness, immutable
reservation, native CAS/transaction ownership, atomic companion writes, restart
authentication, external origin/signatures, committed marker-before-call,
receipt durability, no-N+1 I/O or applicable D-control ingress-before-ack. F4/F5
retain those proofs against real composed boundaries; they cannot use a boolean
or a reconstructed origin as evidence. Signing/current key/material/freshness,
ordinary Required gate, control exemptions and final seal remain mandatory F5.
F6 retains the complete graph/verification closure. No pure PASS marks those done.
