# Specification 132: Authenticated Audit Anchor Provenance

Status: Authority direction adopted — exact Product implementation remains unissued

[D179](../decisions/179-audit-signing-authority-and-production-provenance.md) adopts the finite provenance extension to
[130](130-audit-control-authority-and-production-provenance.md). [127](127-audit-anchor-durable-external.md)
and [128](128-audit-anchor-provider-protocol.md) already govern reservation/frame
and external protocol choices. Pure [126](126-audit-anchor-intent-state.md) remains
unchanged. The [F4](../orchestration/tasks/P23-003F4-audit-anchor-durable-external.md)
and [F5](../orchestration/tasks/P23-003F5-audit-signing-authority-production-and-seal.md) packets own exact implementation
joins and qualification. BlackOps1.3 remains unreleased.

## 1. Authority and narrow permission change

Use the already reviewed `control-lineage` envelope, signature and envelopeDigest
formulas, installed root/current-delegate acquisition, historical-policy rules,
finite failures and phase-acquisition protocol from [specification130](130-audit-control-authority-and-production-provenance.md).
Do not add another signing key, HMAC lifecycle, envelope purpose or F1 role.
Append exactly `anchor_reservation` and `anchor_revision` to the finite delegate
permissions, with the two closed payload kinds below. Installation must explicitly
publish the revised policy contract before using either permission. An older
seven-permission policy grants neither action; a parser must not add defaults.
This is an explicit version-1 contract amendment before first publication,
not permission to reinterpret an installed published policy silently.

The owner is the future closed F4 durable transition composition, constructed
only by the same reviewed F5 ordinary/control composition. It receives no public
sign(payload), caller origin/class, arbitrary operation string, selected key,
boolean authorization or general frame-writer callback. Actual named methods
build frames through F3; the private provenance recorder authenticates only those
owned results after the real admission/operation succeeds. Exact construction,
call and delegate custody maps must be frozen before implementation issuance.

Each new action uses the actual current phase permission and delegate. The
recorded policy is the policy that authorized that phase. This reuses the existing
policy-race semantics and does not hold external filesystem coordination across
a DB root or claim retroactive revocation of an already acquired phase.
Historical verification never grants permission to append a new revision.

## 2. One pre-genesis reservation

The exact signed payload is:

```text
{kind:"audit_anchor_reservation",installationRef,authorizingPolicyDigest,
 reservationId,reservedAt,productionClass,checkpointReservationDigest,
 checkpointPersistedOutcomeDigest,genesisFrameSha256,genesisFrameByteLength}
```

These are ten members, all scalar. Ref/ID/hash/time/counter grammar follows the
common authority contract. `productionClass` is exactly ordinary or control,
derived from the authenticated persisted checkpoint outcome and its reservation;
it is not inferred from partition, segment contents or a Framework-looking caller.
The exact checkpoint outcome must bind the F1 complete checkpoint bytes already
present through accepted F2. A complete row with no valid attributed outcome
cannot create this reservation. Current access/trust checks remain required at
later signing or external byte release; this reservation is not that permission.

`reservationId` is generated once using the existing UUIDv7 source inside new
work creation. The unique logical slot is the checkpoint under specification127
(`UNIQUE(checkpoint_id)`). Checkpoint-first authenticated recovery precedes new
destination selection. The selected exact C is immutable; current-v1 same-C
partition continuation and lock ordering remain specification127 obligations.
The reservation authenticates the entire exact genesis frame by SHA-256 and byte
length, thereby binding request10, binding4 and the null origin without copying
an additional partially overlapping identity map into this envelope.

One owned local root performs the complete atomic genesis:

1. Under the reviewed local fence, recover/reject any existing logical slot before
   selecting new IDs, idempotency material or predecessor values.
2. Select immutable request10 and binding4, including the single reserved future
   receipt ID and checkpointSignedAt from the verified complete checkpoint.
   Capture one trusted genesis time and create the F3 genesis with expectation
   version0/hash null/token null. The raw idempotency key is only a sensitive
   transient input; its derived A5 hash is the only persisted representation.
3. Canonically encode that exact frame. Construct/sign the reservation above;
   then construct/sign the genesis revision below with the reservation digest
   as its predecessor. No circular digest exists: the frame contains neither
   provenance-envelope digest.
4. Insert reservation, canonical genesis bytes, signed genesis revision and the
   revision1 head together. No reservation-only success is returned. The actual
   COMMIT must succeed or be reconciled before a ready result is exposed.

A lost COMMIT response reconciles the exact unique signed slot before generating
anything again. Existing signed genesis bytes suffice to recover; no raw key is
required and no digest-only F3 creation API is added. Missing or mismatched atomic
members fail closed. Do not reconstruct a reservation from an unsigned row flag.
The owner must not persist an exception containing the transient raw key.

## 3. Signed revision over separate exact frame bytes

The exact signed payload is:

```text
{kind:"audit_anchor_revision",installationRef,authorizingPolicyDigest,
 reservationDigest,intentId,operation,revision,previousRevisionDigest,
 frameSha256,frameByteLength,recordedAt}
```

These are eleven scalar members. `operation` is exactly `genesis` or one of the
fourteen current A5 operation names; it never becomes a member of the38-field
intent. Each named method supplies its own constant; no operation-string dispatch
or caller-provided lease origin is added. `revision` equals the frame's intent
version, and `recordedAt` equals its updatedAt. Genesis is exactly version1 with
null A5 previousStateHash and previousRevisionDigest equal to reservationDigest.
Every later record has version+1 and previousRevisionDigest equal to the complete
prior authenticated envelope digest; its A5 previousStateHash also matches the
prior frame. These two links are different domains and cannot substitute.

`frameSha256` is ordinary SHA-256 over the exact canonical no-LF frame bytes;
`frameByteLength` measures those same bytes. This is an authenticated internal
byte binding, not a replacement A5 stateHash/public receipt/checkpoint hash.
The entire frame is retained separately, never embedded into the common envelope.
Thus the unchanged common32-member object cap does not admit the A5 object's38
members by accident. The frame parser needs its own exact3/38/4/6-member contract,
closed scalar grammar, count-first admission, no duplicate keys/floats/unsafe
integers and F3 restore validation. Its whole canonical frame has the inclusive8192-byte cap adopted by
specification127. Exact parser implementation and native proof remain issuance
work; the shared envelope limits must not be raised for this frame.

Immutable rows are keyed by `(intentId,revision)` and store the canonical frame
bytes plus exact signed envelope/digest. A revision append and head CAS commit
atomically with all matching operation-specific native rows. CAS binds expected
version/stateHash/token and prior signed revision digest. No committed revision
is updated/deleted, and no mutable queue bit supplies authenticity. A concurrent
loser validates the committed winner, never replaces signature/time/ID or
re-signs different bytes as the same logical revision.

The exact local14 operation transactions, lock order, expected conflict behavior,
configuration capture and receipt storage are still a separate required local
contract. The adopted provider protocol in specification128 does not prove these local semantics.

## 4. Restore and the original-claim association

Before F3 restore, authenticate the reservation, the complete signed revision
association and the required revision history under externally rooted policies.
Check all SQL metadata against the signed decoded content, exact canonical bytes,
byte counts/digests, immutable genesis request/binding and both revision chains.
A separately signed intent and separately signed origin cannot be spliced.

Every non-null installed origin, including an expired lease, selects the actual
stored named claim revision by its positive claimVersion. Lease expiry never
waives authentication of the original claim association. Its signed operation must be the matching claim kind and its frame
must contain that actual claim's stateHash/token/attempt/intentId. The current
origin must match that record exactly. Delta0 is the claim itself; delta1 absent
has the signed claim as predecessor; delta2 call-start verifies the real absent
intermediate and both links. Reconciliation and internal claims remain delta0.
A valid but caller-invented origin, re-labeled operation or missing intermediate
cannot be authenticated by merely recomputing an A5 state hash.

Current permission checks must include the existing F5 current restrictions on
historical issuers; a valid old signature alone is insufficient for a new action.
The implementation packet must freeze bounded streaming/history lookup and
resource behavior without imposing a new lifetime revision cap or accepting an
unverified suffix. This specification does not decide a cache/summary shortcut.
An authenticated frame can still be old. A head pointing below extant later
signed revisions is invalid; deletion/restoration of an entire mutually
consistent signed history retains the existing stopped-recovery threat limit.
No independent per-revision external witness or stronger rollback promise is
introduced. Real CAS/restart/partial-corruption proofs remain mandatory.

## 5. Post-acceptance receipt reservation join

The existing F5 `audit_f_reservation` receipt path remains after actual external
acceptance and before A5 receipt_input_persist. Explicitly extend its receipt-only
intentBinding with `anchorReservationDigest` and `anchorReceiptId` (ten current
members plus two; still below32). They must match the already authenticated
anchor reservation/genesis binding and every later restored frame. No new receipt
ID is generated. Current trust selects the receipt signing key. Its ref and
fingerprint are already immutable in the earlier F5 receipt reservation and
must pass unchanged into A5 input-persist; current revocation leaves work pending.
The key becomes frozen in A5 at that real input-persist operation. No signing
fields are written into the initial A5 wire.

The checkpoint reservation/outcome and production class are exact shared joins.
The receipt preparation and complete-candidate/persisted steps retain their
existing F1 byte/identity/current-trust checks. Complete receipt durability and
applicable D-control completion precede the real acknowledgement. A signed A5
revision or a receipt reservation cannot manufacture either result. Ordinary
Required-before-release and the finite control exemption remain F5 obligations.

## 6. Required discriminating proof and remaining decisions

Before Product Acceptance, prove actual signed and native behavior for:

- missing old-policy permission, wrong kind/purpose/delegate/installation, altered
  production class and checkpoint provenance; ordinary cannot mint control work;
- validly shaped/rehashed frame splicing, altered binding/receipt ID/origin and
  operation substitution, missing/replaced delta1/2 history and stale head;
- actual rollback before genesis commit, response loss after genesis commit,
  existing-slot concurrency and no new raw key/ID on committed recovery;
- all fourteen named transitions, revision/hash/token CAS losers, partial native
  writes, restart and signature/metadata/bytes equality; no signature substitutes
  for marker COMMIT-before-provider-I/O or actual receipt-before-ack;
- current delegate restriction/rotation with authentic historical work, exact
  finite grants and no general signing surface; bounded decode/history behavior;
- post-acceptance receipt joins to the single pre-genesis ID, preserved ordinary
  Required access/release and control provenance, and unchanged F1/A5 wires.

The checkpoint slot,frame cap and provider protocol are already adopted in
specifications127/128. Remaining issuance work is exact SQL tables/APIs/ownership,
local14 transaction and history resource contracts,the real F5 prerequisite,
provider native qualification and integrated runtime. No F4/F5 implementation
Acceptance or runtime result follows from adopting these payload/atomic joins.
