# Specification 126: Audit AnchorIntent State

Status: Adopted — pure F3 implementation Accepted; BlackOps1.3 unreleased

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

The responsibility split remains pure F3 state;F4 durable intent/lease/receipt
and external-adapter primitives;F5 actual signing-release authorization,online
trust/key acquisition,authenticated control provenance and final seal integration;
F6 complete record/checkpoint/anchor/freshness verification and final F qualification.
The adopted most-specific129–132/D179 dependency order places real shared F5
authority and attributed checkpoint production before native F4,then completes
F5 release/seal after real F4. A hypothetical port cannot prove F4 origin.
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

All23 new Product files are under src/Internal/Audit/Anchor. They are internal,
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
| AuditAnchorIntent | Private construction; static fromArray(array):self; toArray():array, state():AuditAnchorState, version():int; closed shape/hash validation, detached projection and exact transition immutability policy under §10. |
| AuditAnchorIntentShape | Count-first38-key admission, normalized field order, scalar delegation and the two state-shape helpers. |
| AuditAnchorScalar | Stateless native scalar admission; the exact seven methods, fixed callers and bounds in §10. No coercion or new policy input. |
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
| AuditAnchorExternalOutcome | Closed8 external-result validation and existing reconciliation/lookup entry points delegating only their wire normalization to ReconciliationShape. No authentication or I/O claim. |
| AuditAnchorReconciliationShape | Stateless closed5 reconciliation and nested closed6 found-object normalization; only ExternalOutcome::reconciliation may call it. |
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
- Actual package required-path list/order and an omission control for each23 new
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

## 10. Adopted bounded responsibility decomposition

The2026-10-11 amendment adds exactly AuditAnchorScalar.php and
AuditAnchorReconciliationShape.php to §7. The two owners and existing-type moves
below replace the earlier duplicate validation without changing A5 semantics,
named transition signatures, the38-field intent or companion shapes. They do not
authorize another helper, outside Product caller, quality waiver or dependency.
All data parameters remain SensitiveParameter, including the Scalar allowed array.

Scalar is final and stateless, with exactly these public static methods:

```php
text(#[SensitiveParameter] mixed $value, int $minimum = 1, int $maximum = 256): string;
hash(#[SensitiveParameter] mixed $value, string $mode = 'required'): ?string;
integer(#[SensitiveParameter] mixed $value, int $minimum, int $maximum): int;
nullableInteger(#[SensitiveParameter] mixed $value, int $minimum, int $maximum): ?int;
nullableTimestamp(#[SensitiveParameter] mixed $value): ?string;
nullableAllowed(#[SensitiveParameter] mixed $value, #[SensitiveParameter] array $allowed): void;
nullableText(#[SensitiveParameter] mixed $value): ?string;
```

Text retains AuditStringValidator::assertSafe, UTF-8/NFC/no-Cc-or-Cf and byte
bounds. Hash retains required/nullable lowercase64-hex admission. Integer tests
native int identity before inclusive bounds; no string/float/bool coercion is
permitted. NullableAllowed accepts literal null or strict membership in the fixed
string vocabulary. Nullable methods reuse their non-null owner; nullable time
calls Anchor Time only. Existing enclosing admission catches retain fresh finite
failure remapping; this lower helper does not retain rejected values or causes.

| Scalar method | Complete Product callers |
| --- | --- |
| text | IntentShape::assertText; Scalar::nullableText |
| hash | IntentShape::assertHash |
| integer | IntentShape::normalize; Scalar::nullableInteger; LeaseOrigin::positiveInteger; ExternalOutcome::positiveInteger; ReconciliationShape::normalize |
| nullableInteger; nullableTimestamp; nullableAllowed; nullableText | IntentShape::normalize only |

Bounds/modes/vocabularies are fixed at those sites. Attempt0..32 remains distinct
from version1..MAX_SAFE_INTEGER. Origin/result positiveInteger wrappers retain
native int returns and fixed1..MAX bounds. Required updatedAt calls Time directly.
Shape's public assertText/assertHash wrappers remain; other callers gain no direct
Scalar edge. Shape retains normalize/assertClosed/assertText/assertHash/
assertPartition, count-first closure,38-key order and all cross-field admission.
Only its four obsolete private scalar helpers disappear.

ReconciliationShape is final and stateless with exactly public static
normalize(#[SensitiveParameter] array $value):array and private static
assertSegment(#[SensitiveParameter] mixed $value):void. Normalize's precise return is:

```text
{schemaVersion:int,kind:string,
 outcome:'external_object_found'|'external_object_absent'|'lookup_unavailable',
 foundObject:null|{anchorObjectRef:string,anchorObjectVersion:int,
  acceptedObjectHash:string,acceptedAnchoredAt:string,segmentId:string,
  previousAnchorReceiptHash:?string},
 reasonCode:?string}
```

ExternalOutcome::reconciliation is its sole outside-type Product caller; lookup
still forwards to reconciliation and PreCall/PostCall retain their Outcome entries.
Normalize admits exact closed5 and text, directly guards schema/kind, then uses
direct strict in_array outcome admission to establish the native finite union.
Found requires an array, null reason, closed6 and exact native/hash/time/UUID
validation. Absent requires null foundObject/null reason; unavailable requires
null foundObject/the exact lookup_unavailable reason. Return detached validated
scalars. No boolean-vector/index trick, cast or asserted docblock replaces proof.

Dependencies are only Shape::{assertClosed,assertText,assertHash}, Scalar::integer,
Time::assertTimestamp, the existing safe-int constant and finite failure types.
The private segment check retains the exact lowercase36-byte UUIDv7 regex and
Shape text admission. Normalize catches Throwable and throws fresh IntegrityInvalid
without cause. No Intent/Frame/Origin/Guard dependency or state/CAS/I/O enters here.
Remove the moved segment helper from ExternalOutcome. Its typed positiveInteger
wrapper uses Scalar; result and all public signatures remain unchanged.

Guard::advance reuses assertExpected and calls only
Intent::assertTransitionImmutable(#[SensitiveParameter] array $current,
#[SensitiveParameter] array $candidate):void for the existing complete13-branch
immutability policy. Guard is that method's sole caller. Preserve exact comparisons,
first receipt-input/public-hash installation exceptions and accepted-field rules.
Admission stays CAS, supplied time/rollback, capacity, immutability, one revision,
origin derivation/preservation/clearing and restore. No computed revision field or
general mutation becomes caller-controlled; named entry checks remain earlier.

Binding removes only its duplicate closed/text/hash methods and uses Shape wrappers.
UUID/time, immutable association, privacy and fresh exception handling remain;
Frame::restore stays the sole binding-association caller. LeaseOrigin changes only
positiveInteger reuse; the complete actual-claim/delta0/1/2 matrix stays in place.
No field-group or origin-association type is added.

ReceiptShape keeps all state/nullability/lease/chronology checks and its public
assertPublicReceiptHash forwarding wrapper. ReceiptBody owns the existing internal
hash/body and public semantic-hash checks, with exactly these four static methods:

```php
public build(#[SensitiveParameter] AuditAnchorIntent $intent,
    #[SensitiveParameter] string $signingKeyRef, #[SensitiveParameter] string $signedAt): array;
public assertPersistedHashes(#[SensitiveParameter] array $value,
    #[SensitiveParameter] string $signingKeyRef, #[SensitiveParameter] string $acceptedAnchoredAt,
    #[SensitiveParameter] string $receiptSignedAt): void;
public assertPublicReceiptHash(#[SensitiveParameter] AuditAnchorIntent $intent): void;
private unsignedBody(#[SensitiveParameter] array $value,
    #[SensitiveParameter] string $signingKeyRef, #[SensitiveParameter] string $acceptedAnchoredAt,
    #[SensitiveParameter] string $receiptSignedAt): array;
```

Exact callers: ReceiptShape::assert -> ReceiptBody::assertPersistedHashes;
ReceiptShape::assertPublicReceiptHash -> ReceiptBody::assertPublicReceiptHash;
ReceiptBody::{build,assertPersistedHashes} -> unsignedBody;
ReceiptBody::assertPublicReceiptHash and ReceiptTransition::{inputPersist,persist}
-> build. The public-hash check must itself catch Throwable and throw fresh
IntegrityInvalid with no cause: Intent::fromArray calls it outside normalize's
catch. Neither body check may call Intent::fromArray or Frame::restore recursively.

The assembler emits the same16 unsigned fields. Build remains the sole F1
signingInput site, once per derivation. Persisted hashes use the same assembler
and existing Canonical typed-ENC formulas without F1. Public hashes use build and
hash_equals. No signer, decoder, verifier or actual receipt durability is added.

These measured design budgets guide implementation; they are not native results.
Each actual class must pass unchanged Mago thresholds, at most10 own methods and
five ordinary arguments. No boolean packing or baseline exception is permitted.

| Type suffix | Branch budget | Own methods | Maximum ordinary arguments |
| --- | ---: | ---: | ---: |
| Binding | 7 | 7 | 2 |
| Canonical | 3 | 8 | 2 |
| Claims | 8 | 5 | 5 |
| Configuration | 4 | 2 | 1 |
| Exception | 0 | 3 | 0 |
| Expectation | 10 | 5 | 1 |
| ExternalOutcome | 12 | 5 | 1 |
| Failure | 0 | 0 | 0 |
| Frame | 10 | 10 | 5 |
| Intent | 13 | 9 | 2 |
| IntentShape | 11 | 5 | 3 |
| LeaseOrigin | 13 | 9 | 2 |
| PostCallTransition | 11 | 6 | 5 |
| PreCallTransition | 9 | 6 | 5 |
| PreparedShape | 13 | 4 | 1 |
| ReceiptBody | 10 | 4 | 4 |
| ReceiptShape | 14 | 2 | 1 |
| ReceiptTransition | 15 | 10 | 5 |
| ReconciliationShape | 10 | 2 | 1 |
| Scalar | 13 | 7 | 3 |
| State | 0 | 0 | 0 |
| Time | 11 | 4 | 2 |
| TransitionGuard | 8 | 2 | 5 |

Final guards enforce the exact new APIs/callers, moved text/immutability/body
validation and unchanged sole Time/Core and Body::build/F1 edges. Preserve all
earlier negative mutations and inert positives. Each new package path gains its
own omission/order control. Existing scalar/nullability/outcome/chronology/privacy/
CAS/wrong-purpose/receipt and complete transition proofs remain mandatory.
Add a valid persisted intent with a wrong public receipt hash and recomputed state
hash through Intent::fromArray; it must fail as fresh IntegrityInvalid with no
previous cause. The native union, all receiving classes, independent Product/guard/
internal-document agreement, full suite and committed export remain unqualified
until their actual assigned evidence passes. §9 and all later durable obligations
are unchanged.
