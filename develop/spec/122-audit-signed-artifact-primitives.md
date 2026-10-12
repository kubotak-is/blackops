# Specification 122: Audit Signed Artifact Primitives

Status: Decided — bounded implementation Accepted (local); BlackOps1.3 unreleased

This is the most-specific contract for [P23-003F1-audit-signed-artifact-primitives](../orchestration/tasks/P23-003F1-audit-signed-artifact-primitives.md)
and [D172](../decisions/172-audit-signed-artifact-primitives.md).
Accepted A/B/C are semantic prerequisites. Ordinary D/E are not prerequisites; Accepted O1 is the chosen common verification baseline only. Follow spec105/106/109 and D152.

## 1. Goal, dependency and three Root decisions

Deliver one coherent pure F1 prerequisite: checkpoint/receipt canonical codecs,
fixed-domain Ed25519 signing, and verification against an already trusted role-
separated snapshot. Accepted A/B/C are parent prerequisites; ordinary D and E
are not new F1 dependencies. F1 has no outside Production caller and cannot
activate signing, sealing, export or Audit control events.

| Root decision | Exact choice and implication |
| --- | --- |
| Private signing input | Accept only a raw 32-byte Ed25519 seed in a SensitiveParameter argument. Derive the native sodium keypair locally. No 64-byte secret-key alternative, automatic format detection, StorageKey adaptation, key provider, key selection or seed generation. This is an internal input format, not a new stored/public wire. |
| Canonical byte limit | Public codec/signing methods exchange complete canonical JCS without LF. Reserve one byte for the later export-file LF: `1 <= strlen(completeBytes) <= 1048575`. Reject excess before JSON decode. The full future file is at most 1048576 bytes inclusive. Unsigned construction must also fit its completed representation; no LF enters any artifact hash. |
| Trusted snapshot precondition | `trustedSnapshot` and the trusted time are supplied by a later accepted trusted caller. F1 validates shape, semantic hash, role, material and time; it does not authenticate provenance. Later export/hosted/offline invocation authenticates the complete manifest-bound VerificationContext against external expectedContextHash before trusting its snapshot. The separately adopted online F route in [129](129-audit-online-authority-and-seed-custody.md) authenticates the whole signed acquisition through its independently installed key and durable floor; [D179](../decisions/179-audit-signing-authority-and-production-provenance.md) records authority adoption; exact caller issuance still precedes its implementation. F1 itself authenticates neither route. No independent expectedTrustHash, self-authenticating bundle key, provider or public verification result is added. |

Root adopts all three choices. Signing remains a pure internal mathematical
primitive; this grants no provider access, runtime activation or signature-release
authorization.

## 2. Scalar and canonical rules

| Value | Exact rule |
| --- | --- |
| Generic ID/ref | String, UTF-8, NFC, no Unicode Cc/Cf, 1..256 UTF-8 bytes. No trimming, normalization or fallback. |
| partitionId | Exactly system:global or tenant: followed by 64 lowercase hex digits; never a masked placeholder. |
| segmentId | Existing B1/B2 canonical lowercase UUIDv7 grammar: 8-4-4-4-12 hex, version nibble 7 and variant 8/9/a/b. Do not impose UUID on checkpointId, anchorReceiptId or other refs. |
| Positive integer | Native integer in 1..9007199254740991. Reject strings, booleans, floats and out-of-range values. Arithmetic must remain within this range. |
| Digest | Exactly 64 lowercase hex characters, representing SHA-256. Compare derived/expected digests with hash_equals. |
| Timestamp | Valid calendar timestamp in exact `YYYY-MM-DDTHH:mm:ss.ffffffZ`, UTC and six fractional digits. No other offset/precision, invalid calendar date, leap normalization or implicit clock read. |
| Public key/signature | Canonical RFC4648 URL-safe base64 without padding, decoded lengths 32/64 bytes respectively. Decode then re-encode must equal the supplied bytes, including unused-bit canonicality. |
| JSON | Existing Audit RFC8785 JCS behavior; closed objects, duplicate keys, invalid text, floats, unsafe integers and noncanonical representations reject. Object key order is JCS UTF-16 order, distinct from the trust-entry list order below. Complete input is exactly the JCS object, without BOM, whitespace suffix, CR or LF. |
| Mutable inputs | No reference parameters/returns, callbacks or retained arrays. Validate bounded closed member shapes before traversal/canonicalization. Returned arrays contain detached scalar snapshots; they are not authenticated capability tokens. |

All complete artifacts are independent immutable bytes. F1 never alters an
AuditRecord or a persisted artifact. Codec success proves only grammar, closed
shape, local invariants and self-hash; callers must use the verifier for signature
and supplied-trust validity. An unsigned body is not a canonical signed artifact.

Before the reused recursive AuditJcsCodec decoder sees complete checkpoint or
receipt bytes, each F1 codec performs a bounded raw preflight after the byte cap:
require the single flat object, reject any object/array nesting outside quoted
strings, and stop on more than18 top-level members. A quote/escape-aware single
pass must distinguish structural commas/braces/brackets from their string data.
This prevents sub-cap deep recursion and quadratic duplicate-key work on a wide
object before the closed-scalar check. It is not a replacement JSON/JCS parser:
normal syntax, duplicate names, scalar types, exact member identity and canonical
bytes are still checked by existing JCS and the codec. Both codecs delegate this
preflight only to the listed AuditArtifactWirePreflight helper. Existing JCS is
unchanged; no type or scope beyond the corrected nineteen-type inventory is added.

## 3. Checkpoint v1

The complete object has exactly these 18 fields. The unsigned body removes only
checkpointHash and signature; it therefore has exactly 16 fields.

| Fields | Types and local invariants |
| --- | --- |
| schemaVersion, kind | Integer 1; string audit_checkpoint. |
| checkpointId, partitionId, segmentId | Generic ID, canonical partition, canonical UUIDv7. |
| firstSequence, lastSequence, recordCount | Positive safe integers; lastSequence>=firstSequence; recordCount=lastSequence-firstSequence+1. |
| firstRecordHash, chainHeadHash | Digests. If recordCount=1 they must equal, because the first and last covered record coincide. For larger ranges actual record linkage is later F graph verification. |
| checkpointHash | Derived digest defined below, never trusted from input. |
| previousCheckpointHash | Null iff firstSequence=1; otherwise a digest. Actual immediate-predecessor equality and global no-gap/no-fork/uniqueness are outside F1. |
| sealedAt, signedAt | Exact timestamps, sealedAt<=signedAt. F1 does not generate or choose them. |
| hashSuite, signatureAlgorithm | Exactly audit-sha256-jcs-v1 and ed25519-v1. |
| signingKeyRef, signature | Generic ref and canonical 64-byte signature encoding. |

Store-wide ID uniqueness, complete-segment coverage, range continuity and closed
segment provenance remain required parent F responsibilities. Signing an input
with shape-valid endpoint hashes does not establish these facts.

## 4. Anchor receipt v1

The complete object has exactly these 18 fields. The unsigned body removes only
anchorReceiptHash and signature, retaining checkpointHash and checkpointBytesHash.

| Fields | Types and local invariants |
| --- | --- |
| schemaVersion, kind | Integer 1; string audit_anchor_receipt. |
| anchorReceiptId, partitionId, segmentId, checkpointId | Generic ID, canonical partition, canonical UUIDv7, generic ID. |
| checkpointHash, checkpointBytesHash, anchorReceiptHash | Digests; anchorReceiptHash is recomputed. |
| anchorStoreRef, anchorObjectRef, anchorObjectVersion | Two generic refs and a positive safe integer. |
| previousAnchorReceiptHash | Null iff anchorObjectVersion=1; otherwise a digest. Actual predecessor equality/+1 continuity uses the exact partition/store/object triple in later F. |
| anchoredAt, signedAt | Exact timestamps, anchoredAt<=signedAt. |
| signatureAlgorithm, signingKeyRef, signature | Exactly ed25519-v1, generic ref and canonical 64-byte signature encoding. |

The pair-binding operation compares receipt partitionId, segmentId, checkpointId
and checkpointHash to the decoded complete checkpoint, recomputes its complete-
byte hash, and enforces checkpoint.signedAt<=anchoredAt<=receipt.signedAt.
It does not accept a URL, ETag or WORM assertion in place of a signed receipt.
It does not assert external persistence, receipt uniqueness, a full anchor chain,
or freshness of the end receipt.

## 5. Exact encoding, hash and signature formulas

For a domain string d and byte string b:

```text
S(d) = ASCII("s:" + decimal(UTF8-byte-length(d)) + ":") || UTF8(d)
B(b) = ASCII("b:" + decimal(byte-length(b)) + ":") || b
F(d,b) = S(d) || B(b)
H(b) = lowercase-hex(SHA-256(b))
J(o) = exact RFC8785 JCS UTF-8 bytes, without LF
```

| Result | Exact formula |
| --- | --- |
| checkpointHash | H(F(blackops.audit.checkpoint.hash.v1, J(unsignedCheckpoint))) |
| checkpoint signature preimage | F(blackops.audit.checkpoint.signature.v1, decoded32(checkpointHash)) |
| checkpointBytesHash | H(F(blackops.audit.anchor.checkpoint-bytes.v1, J(completeCheckpointIncludingSignature))) |
| anchorReceiptHash | H(F(blackops.audit.anchor.hash.v1, J(unsignedReceipt))) |
| receipt signature preimage | F(blackops.audit.anchor.signature.v1, decoded32(anchorReceiptHash)) |
| trust snapshot hash | H(F(blackops.audit.trust-store-snapshot.v1, J(snapshot excluding only hash))) |

Sign the exact preimage bytes with pure RFC8032 Ed25519. No Ed25519ph/context
variant, pre-hash signing API, HMAC, custom algorithm, hex-string digest payload,
U32BE LP substitution or newline is valid. Complete artifact self-hashes exclude
only their own named hash field and signature; signedAt is included.

## 6. Existing nested trust snapshot

This is not a new artifact or authority source. Its exact five fields are
ref, hash, observedAt, validUntil and entries. ref is a generic ref; hash uses
the formula above; times use the timestamp grammar and observedAt<validUntil.
For use, observedAt<=verifiedAt<validUntil is required.

entries is a list of 1..256 exact eight-field objects:

| Field | Contract |
| --- | --- |
| keyRef | Generic ref, unique across the snapshot. |
| role | Exactly checkpoint, anchor or export. F1 has no export-signing operation. |
| publicKey | Canonical unpadded base64url of exactly 32 bytes. |
| publicKeyFingerprint | H(decoded publicKey), unique across the entire snapshot, including different roles/key refs. |
| notBefore, notAfter | Exact timestamps; notBefore<notAfter. |
| revokedAt | Timestamp or null, with the status consistency below. |
| status | Exactly active, revoked or expired. |

Entries must already be sorted strictly by unsigned UTF-8 keyRef bytes, without
locale sorting; F1 rejects unsorted input rather than rewriting it. Same-material
aliases fail even when their key refs or roles differ. Snapshot structure/hash
validation covers all entries before resolving a selected key. Its canonical
size must fit within the existing 1-MiB context budget, and closed bounded shapes
must be checked before JCS allocation. Check the exact five fields, list/entry
count and exact eight scalar fields/types/byte bounds before canonicalizing or
walking untrusted nested values. No arbitrary recursive metadata is legal.

| Status or check | Exact outcome |
| --- | --- |
| active entry | revokedAt must be null. A future/expired active entry may exist structurally, but cannot be selected outside its current window. |
| revoked entry | revokedAt must be non-null and <=snapshot.observedAt; never usable. |
| expired entry | revokedAt must be null and notAfter<=verifiedAt; never usable. |
| selected key | Exact keyRef and expected role, active status, null revokedAt, and notBefore<=verifiedAt<notAfter. No fallback to another key. |
| authenticated signedAt | After signature verification, require notBefore<=signedAt<=verifiedAt<notAfter. Backdating cannot revive a currently expired/revoked key. |

The role used for an artifact is fixed by the checkpoint/receipt entry method.
F1's trust lookup accepts only expectedRole checkpoint or anchor, while validating
export entries as part of the whole supplied snapshot. A later export primitive
requires its own scoped adoption. These checks provide no current/suffix claim.

## 7. Exact internal callable and state inventory

Namespace: `BlackOps\Internal\Audit\Artifact`. The corrected inventory has
nineteen types: the original seven boundaries plus twelve explicit static helpers,
one final declaration per PSR-4 file under src/Internal/Audit/Artifact/.
No PublicApi attributes or new public namespace. The five original service
classes and twelve helpers are final, stateless static utilities: no declared
constructor, properties, inherited base class, public constants, magic method,
callback, service registration or injected collaborator. They instantiate the
existing pure JCS helper privately as needed. This removes the previous brief's
unnecessary constructor graph without introducing a replacement dependency layer.

All service/helper methods listed below are public static. No additional public
or protected method is allowed. Exact array shapes are documented in PHPDoc.
Private helpers may split validation stages within the specified method ceiling;
they may not introduce alternate algorithms or callable extension seams.

| Type | Exact declared public surface | Own method ceiling, including private |
| --- | --- | --- |
| AuditArtifactFailure | String enum: ArtifactInvalid=artifact_invalid, TrustInvalid=trust_invalid, SignatureInvalid=signature_invalid, SigningUnavailable=signing_unavailable. No declared methods. | 0 |
| AuditArtifactException | Final RuntimeException; __construct(AuditArtifactFailure failure); failure(): AuditArtifactFailure. Only private readonly failure property. Fixed message `Audit artifact operation failed.`, numeric code 0, null previous; no message/code/cause inputs. | 2 |
| AuditCheckpointCodec | signingInput(array unsigned): array{bytes:string,hash:string,preimage:string}; complete(array unsigned,string rawSignature): string; decode(string completeBytes): array; bytesHash(string completeBytes): string | 10 |
| AuditAnchorReceiptCodec | signingInput(array unsigned): array{bytes:string,hash:string,preimage:string}; complete(array unsigned,string rawSignature): string; decode(string completeBytes): array; assertCheckpointBinding(array unsigned,string checkpointBytes): void | 10 |
| AuditTrustSnapshotCodec | validate(array snapshot,string verifiedAt): void; key(array snapshot,string keyRef,string expectedRole,string verifiedAt): array; assertSignedAt(array entry,string signedAt,string verifiedAt): void | 10 |
| AuditArtifactVerifier | checkpoint(string checkpointBytes,array trustedSnapshot,string verifiedAt): void; anchorReceipt(string receiptBytes,string checkpointBytes,array trustedSnapshot,string verifiedAt): void | 10 |
| AuditArtifactSigner | checkpoint(array unsigned,string privateSeed,array trustedSnapshot,string trustedNow): string; anchorReceipt(array unsigned,string checkpointBytes,string privateSeed,array trustedSnapshot,string trustedNow): string | 10 |

The twelve additional helper surfaces are closed as follows. Return arrays have
exact PHPDoc shapes, including detached scalar values. Private helper inventories
are frozen with source Review and remain under the same ten-method ceiling.

| Helper | Exact public static surface |
| --- | --- |
| AuditArtifactWirePreflight | assertFlatObject(string bytes): void |
| AuditArtifactScalar | closed(array value,array keys): array; string(mixed value): string; nullableString(mixed value): ?string; boundedInteger(mixed value): int |
| AuditArtifactCanonical | artifact(array value): string; trustSnapshot(array value): string; decode(string bytes): array |
| AuditArtifactShape | checkpointUnsigned(array value): array; checkpointComplete(array value): array; anchorUnsigned(array value): array; anchorComplete(array value): array |
| AuditArtifactRelation | checkpoint(array value): void; anchor(array value): void |
| AuditArtifactIntegrity | checkpoint(array value,string bytes): void; anchor(array value,string bytes): void |
| AuditAnchorBinding | assert(array unsigned,array checkpoint,string checkpointBytes): void |
| AuditTrustScalar | closed(array value,array keys): array; string(mixed value,int maximum): string; publicKey(mixed value): string |
| AuditTrustSnapshotShape | snapshot(array value,string verifiedAt): array |
| AuditTrustEntryShape | entry(mixed value,string observedAt,string verifiedAt): array |
| AuditTrustEntryRelation | assert(array entry,string observedAt,string verifiedAt): void |
| AuditTrustLookup | key(array snapshot,string keyRef,string expectedRole,string verifiedAt): array; assertSignedAt(array entry,string signedAt,string verifiedAt): void |

These helpers separate raw scanning, bounded scalar/shape validation, relations,
canonical encoding, integrity and trust lookup; they are not a generic validation
framework. Fixed field tables are permitted, dynamic validators/callbacks or
caller-selected domains are not. Complete shape/decode paths revalidate every
unsigned field's type, grammar, chronology and sequence/version/nullability
relations before complete integrity. They may not rely on signingInput being
called separately. Receipt binding receives exactly its sixteen unsigned fields;
complete verification removes only anchorReceiptHash/signature for that check.

AuditTrustLookup may use one shared private assertCurrent(array entry,string
verifiedAt): void. BOTH key and standalone assertSignedAt require active status,
null revokedAt and notBefore <= verifiedAt < notAfter after whole snapshot/entry
validation. assertSignedAt also validates its supplied timestamp/window and rejects
future signing independently; prior key() success is never a precondition.
Valid unused revoked/expired/future entries may exist in the whole snapshot but
cannot pass a selected/standalone current-key check. Preserve direct revoked,
expired, future-notBefore, notAfter equality/microsecond negatives and valid
active-entry positives. The shared private check counts in actual metrics.

All input arrays/mixed scalars/bytes/refs/timestamps are SensitiveParameter,
including new helper frames. Before array_keys/diff/traversal/canonicalization,
check closed top-level/member cardinality and scalar byte caps. A public key must
have exactly43 encoded bytes before sodium decode; still require canonical
32-byte material and reject unused-bit aliases. Return a newly constructed exact
eight-field scalar key, with bidirectional PHP reference-canary proof. Use
hash_equals for derived digests/fingerprints and exact binding digests.

The quote/escape-aware scanner preserves escaped => inString; an ending inString
state rejects either unfinished condition. No optional thirteenth wire helper is
authorized. Existing unchanged method/CCN/Kan/size/static gates decide whether
this nineteen-type design qualifies; estimates in preparation are not results.

signingInput returns only the canonical unsigned bytes, derived digest and exact
signature preimage; it is an internal calculation tuple, never a persisted wire
or proof of authorization. complete revalidates the unsigned body and requires a
raw 64-byte signature, then encodes the complete artifact. decode verifies closed
complete shape, local invariants, JCS, self-hash and signature encoding.
bytesHash first validates the complete checkpoint and hashes its exact no-LF
bytes. key validates the full snapshot before returning the selected exact eight-
field entry. assertSignedAt validates its entry's shape/material/status/window
again; an arbitrary array does not bypass validation.

All artifact, entry/snapshot, byte, seed, ref and time parameters on owned public
and private service methods carry SensitiveParameter. Exception's finite enum
parameter is not sensitive. No service retains or returns private material.
Only AuditArtifactSigner calls native keypair derivation/signing functions;
only AuditArtifactVerifier performs detached signature verification. Both codecs
and the trust codec may delegate public base64 conversion to the listed integrity
and trust-scalar helpers. Freeze the exact native call owners; no signing or
secret-key derivation moves to those helpers. No private seed,
keypair or secret-key material passes through JCS. PublicKey and its fingerprint
remain in the exact trust snapshot JCS and its hash. Derived keypair/secret-key buffers are cleared in finally
when owned; no claim is made that the caller's seed copy is erased.

Reuse existing AuditJcsCodec, AuditStringValidator, AuditRecordTime and
MAX_SAFE_INTEGER; native hash/hash_equals/sodium supply the rest. AuditLengthPrefix
is U32BE LP and must not be used for these formulas. No change to RecordCodec,
Catalog lookup, key providers, StorageKey, B2, JCS, Composer or dependencies.

The 10-method ceiling does not authorize a monolithic validator. Keep closed
field tables and short private shape/string/scalar/relation stages. Existing
Mago complexity/size rules and baseline remain unchanged; no new suppression or
threshold relaxation is authorized. The initial seven-type implementation failed
these gates; Root adopts only the twelve explicitly listed helpers as the bounded
correction. If this inventory fails actual gates, return concrete evidence before
any further path/type expansion. Do not move logic into unlisted helper files.

## 8. Failure precedence and privacy

| Entry | Ordered checks and result |
| --- | --- |
| Codec | Raw cap -> quote/escape-aware flat-object and at-most18-member preflight before recursive JCS decode -> closed scalar shape -> text/grammar -> local relation -> derived hash -> exact complete JCS/signature encoding as applicable. Array-based construction validates closed bounded scalars before canonicalization. Every rejection is fresh ArtifactInvalid; no lower cause or partial bytes escape. |
| Trust operations | Supplied time and whole bounded snapshot/entry shape -> semantic hash/material aliases/order/status consistency -> selected exact role/current key -> authenticated signedAt window when called. Every rejection is fresh TrustInvalid. |
| Checkpoint verifier | Complete codec validation -> full snapshot and checkpoint-role key at verifiedAt -> detached Ed25519 -> authenticated signedAt window. Mathematical signature failure is SignatureInvalid; trust failures are TrustInvalid. |
| Receipt verifier | Decode both artifacts and validate their binding -> checkpoint verifier -> anchor-role key at verifiedAt -> receipt detached Ed25519 -> authenticated receipt signedAt window. No result is returned before both signatures and binding pass. |
| Checkpoint signer | Unsigned codec validation -> full snapshot/checkpoint-role key/current and supplied signedAt window -> seed length/material match -> native sign -> complete canonical bytes. Seed/material/native-sign failures are SigningUnavailable; earlier failures preserve their category. |
| Receipt signer | Unsigned validation and checkpoint-byte binding -> verify checkpoint at trustedNow -> anchor-role key/current and supplied signedAt window -> seed/material -> sign -> complete bytes. No seed derivation/sign operation occurs before checkpoint/binding/trust validation. |

For signing, derive the seed's public key and compare its raw bytes with the
selected trust entry before signing. Never search for another matching key,
accept a caller-selected role/domain/algorithm, or catch an invalid signature
and retry another algorithm. Unexpected owned native signing errors collapse
to SigningUnavailable; unexpected owned verification errors collapse to
SignatureInvalid. F1 errors are internal categories; later G maps its own stages
to the existing finite numeric result codes. F1 does not emit those results.

Required simultaneous-failure precedence includes malformed artifact + bad trust
=>ArtifactInvalid; bad trust + bad mathematical signature =>TrustInvalid; valid
trust + bad signature + future self-declared signedAt =>SignatureInvalid; valid
signature + future signedAt =>TrustInvalid; receipt binding mismatch + bad
checkpoint signature =>ArtifactInvalid; bad trust + invalid seed length
=>TrustInvalid. These rules do not redefine G's global Stage0-7 ordering.

## 9. Exact file ownership and architecture gate

Luna Max implements these exact 29 Product/test/package files; Root owns the
permanent authority documents, Task/Report integration, STATE and Acceptance.
No worker stages/commits or runs shared gates. Reviewers are read-only.

```text
src/Internal/Audit/Artifact/AuditArtifactFailure.php
src/Internal/Audit/Artifact/AuditArtifactException.php
src/Internal/Audit/Artifact/AuditCheckpointCodec.php
src/Internal/Audit/Artifact/AuditAnchorReceiptCodec.php
src/Internal/Audit/Artifact/AuditTrustSnapshotCodec.php
src/Internal/Audit/Artifact/AuditArtifactSigner.php
src/Internal/Audit/Artifact/AuditArtifactVerifier.php
src/Internal/Audit/Artifact/AuditAnchorBinding.php
src/Internal/Audit/Artifact/AuditArtifactCanonical.php
src/Internal/Audit/Artifact/AuditArtifactIntegrity.php
src/Internal/Audit/Artifact/AuditArtifactRelation.php
src/Internal/Audit/Artifact/AuditArtifactScalar.php
src/Internal/Audit/Artifact/AuditArtifactShape.php
src/Internal/Audit/Artifact/AuditArtifactWirePreflight.php
src/Internal/Audit/Artifact/AuditTrustEntryRelation.php
src/Internal/Audit/Artifact/AuditTrustEntryShape.php
src/Internal/Audit/Artifact/AuditTrustLookup.php
src/Internal/Audit/Artifact/AuditTrustScalar.php
src/Internal/Audit/Artifact/AuditTrustSnapshotShape.php
tests/Internal/Audit/Artifact/AuditCheckpointCodecTest.php
tests/Internal/Audit/Artifact/AuditAnchorReceiptCodecTest.php
tests/Internal/Audit/Artifact/AuditTrustSnapshotCodecTest.php
tests/Internal/Audit/Artifact/AuditArtifactSignerTest.php
tests/Internal/Audit/Artifact/AuditArtifactVerifierTest.php
tests/Architecture/AuditArtifactArchitectureTest.php
tests/Fixtures/Audit/audit-signed-artifacts-v1.json
tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

The first27 are new. The original seven-path group and its controls are retained.
The correction adds twelve Product helper paths and each missing-path plus one
ordered-group negative through the Task's explicit serialized O2 -> F1 handoff.
Keep both actual intermediate payloads/modes/hashes, every previous control and
the exact export-script pin. Initial115 ->118 ->125 counts are historical; the
correction starts from current125/77 and follows the adopted actual handoff.

The new architecture guard recursively checks exactly nineteen Product declarations,
the public/static/property/attribute inventory above, absence of PublicApi and
outside Production references, and bounded resolved-name call/import graph.
Inside F1, dependencies are the nineteen listed types, approved scalar/JCS helpers,
RuntimeException/Throwable and the exact native operations described above.
There are no DB/Connection/Clock/provider/Application/Container/HTTP/CLI/runtime,
filesystem/network/process/environment/randomness or audit-append dependencies.
No extension via closures, dynamic invocation, callable arrays, reflection or
class-string loading is allowed. Fixed signer/verifier entry methods own their
role and domains. Outside source references and registrations remain exactly zero.

Mutation controls must reject a nested/unlisted twentieth type, extra method/property or
PublicApi marker, missing SensitiveParameter, changed role/domain, secret
return/log/retention, new signer call from verifier, key-provider/Clock/append
dependency, and an outside direct/alias/group-import/class-string/dynamic caller.
Unrelated strings and comments are positive controls. Guards inspect the actual
source and meaningful boundaries, not just copied expected literal snippets.
Existing A/B/C Product and guard semantics are unchanged. O2 alone edits the
existing A2 guard for this explicit sibling repair: AuditRecordTime::assertTimestamp
is allowed only in AuditArtifactShape::{checkpointUnsigned,anchorUnsigned},
AuditTrustSnapshotShape::snapshot, AuditTrustEntryRelation::assert and
AuditTrustLookup::assertSignedAt. Other A2 types and other Artifact paths/methods
are rejected, including the original three codecs which no longer call time
directly. Include resolved-reference negatives and inert string/comment positives;
no directory exemption. F1 owns no existing A2/C3 guard file.

For the adopted O2/F1 shared final plan, O2 owns its explicit25-path scope
including twelve helpers, native roots, fixtures and exact A2/C3 guard additions.
F1 owns the29 paths above. Their only shared editable paths are the two package
scripts. Root serializes each twelve-helper extension over the preserved initial
F1 endpoint; neither may edit the other child's source or proof. Both permanent
Packets copy the exact sibling manifests and current correction handoff, with
one common input/case freeze and all original child-specific proof retained.

## 10. Literal golden and negative matrix

The fixture contains immutable literal unsigned/complete JCS bytes, hashes,
preimages, public keys and signatures. Node uses its own JCS/crypto calculation
and imports no Product PHP; PHP reproduces those literals using the F1 code.
Node never rewrites the expected fixture during verification. No goldens are
claimed to exist until this assigned fixture work has been performed and frozen.

Use public test seeds: 32 bytes of 0x11 for checkpoint, 0x22 for anchor, 0x33 for
the unused export trust role. Pin the derived public keys/signatures as literals.
Trust observedAt=2030-01-02T03:00:00.000000Z,
validUntil=2030-01-02T04:00:00.000000Z,
verifiedAt=2030-01-02T03:04:05.123456Z;
notBefore=2029-01-01T00:00:00.000000Z and
notAfter=2031-01-01T00:00:00.000000Z for the base active keys.
Artifact timestamps must be fixed inside these windows with valid chronology.

| Family | Required positive | Required independent negatives |
| --- | --- | --- |
| W1 checkpoint | Exact complete/unsigned bytes; system and actual derived tenant; ranges 1-42 and 43-50 with real B1 record-derived first/head hashes and predecessor checkpoint hash; one-record range | Every missing/extra field/type; changed fixed token; wrong count/bounds/nullability; one-record endpoint mismatch; malformed partition/segment; unchecked stored hash |
| W2 receipt | Exact complete/unsigned bytes; receipt versions 1/2 with predecessor hashes and exact checkpoint binding | Wrong checkpoint/partition/segment/ID/hash/complete-byte digest; LF in checkpoint digest; genesis-null mismatch; reversed timestamp pairs |
| W3 text/raw | NFC UTF-8 refs at 256 bytes; JCS canonical body; upper local integer boundaries where valid; quoted commas/braces/brackets and escaped quotes/backslashes do not count as structure | Byte257, Cc/Cf, invalid UTF-8/NFC, duplicate raw keys/escaped duplicate names, BOM/LF/CR/whitespace/noncanonical order, float/unsafe integer; raw cap+1, sub-cap deeply nested object/array and >18-member wide flat input reject before recursive JCS decode; nested/type-invalid trust arrays reject before canonicalization |
| H1 formulas | Literal domain/preimage/hash/signature bytes in PHP and independent Node | Wrong domain, string-vs-bytes length framing, hex digest signed instead of raw32, U32BE LP substitution, hash-field/signature inclusion or signedAt omission |
| S1 signature encoding | Native deterministic Ed25519; 64-byte signature and 32-byte public key; repeated identical input produces identical bytes | Padding, alphabet/size/unused-bit alias; altered body with recalculated self-hash but invalid signature; opposite-role signature; Ed25519ph or alternative-domain bytes |
| T1 snapshot | Exact five/eight-field shapes and semantic hash; 1 and 256 entries; three roles; unsigned UTF-8 list order distinct from JCS object ordering | Missing/extra fields; empty/257 entries; duplicate/unsorted refs; wrong semantic hash/fingerprint; same key material under another ref or role, even if unused |
| T2 time/trust | notBefore equality and valid current windows; valid unused future/revoked/expired entries with consistent statuses | Snapshot or key validUntil/notAfter equality fails; one-microsecond outside edges; revoked/expired/wrong-role/missing key; backdated/future signedAt; inconsistent status/revokedAt |
| K1 seed/privacy | Matching selected raw32 seed; distinct role material; no private state | Wrong length/wrong matching public material; no fallback key; fresh fixed exception/null cause, SensitiveParameter in all owned frames; no seed/keypair serialization/log/return |
| O1 ordering | Each entry follows Section8; no I/O/append/registration and deterministic output | All six simultaneous-failure pairs in Section8; sign operation unreachable before invalid checkpoint/binding/trust/seed checks |
| A1 architecture/package | Exact nineteen internal types and ordered original/helper package groups | Mutation controls from Section9; every new required path removal and a reordered pair reject; prior controls remain |

Recompute unrelated self-hashes/signatures in each negative where necessary to
reach its intended layer. Do not pass a wrong signature to every failure test
and accidentally prove only signature rejection. The fixture's linked ranges
are valid test inputs, not proof that F1 implements full record or global graph
verification. Global gap/fork/overlap, truncation and freshness remain later F.

Valid closed fields are far smaller than 1 MiB. Cap tests must distinguish raw
pre-decode rejection from later shape/JCS rejection at the inclusive boundary;
do not fabricate a supposedly valid 1-MiB checkpoint by adding padding/fields.
Likewise a mathematically verified F1 artifact is not a persisted/sealed or
authorized release result.

The Node file uses node:test with six named tests: checkpoint literals; receipt
literals; trust hash/material; system/tenant record-derived vectors; signature
domain/material substitutions; encoding/digest boundaries. Each test checks all
its fixture rows. PHP uses the five listed test classes and named/data-provider
cases for the matrix. Before the final freeze, record the exact PHP case inventory
and fixture row IDs/counts; full JUnit must retain them all with no skip/error.
Do not use a single file-level PASS as evidence that all rows executed.

## 11. Exact verification and evidence plan

Root owns every command and shared cache/DB resource, runs the gates serially,
and records command, inputs, environment, timestamps, elapsed monotonic seconds,
exit/case counts and log/JUnit paths. The implementation worker does not run
shared gates. This draft preparation ran none of the commands below.

Preflight: freeze the actual29 paths, predecessor full case inventory, static
source/config/dependency inputs, installed runtime/tool/image metadata, public
Source/artifact maps and unrelated mixed-work baseline. Confirm ext-sodium is
available in the same app environment without changing Composer. Root obtains
independent authority Review before Product work. Freeze literal expected
fixture values before claiming PHP/Node parity, then Review source and failure
paths before the final full run.

The selected final qualification is shared with O2 only after both permanent
Packets adopt the same scope and serialized package handoff. Freeze one common
candidate/runtime/repository manifest and the exact union of every Accepted O1
predecessor case plus all new O2 and F1 cases, with separate child subsets.
The shared full/static/package physical receipts may satisfy both children only
for identical actual inputs; all F1 Node/math/privacy and O2 real native+C3/phase
checks remain required. A joint dirty-tree result does not qualify an isolated
intermediate commit. If the other child is unavailable, Root must explicitly
select a standalone freeze before work rather than silently dropping its cases.

Iteration feedback, as needed after meaningful changes:

```bash
docker compose run --rm --no-deps -T app mago format --check src/Internal/Audit/Artifact tests/Internal/Audit/Artifact tests/Architecture/AuditArtifactArchitectureTest.php
docker compose run --rm -T app php vendor/bin/phpunit --display-all-issues tests/Internal/Audit/Artifact tests/Architecture/AuditArtifactArchitectureTest.php --log-junit .phpunit.cache/f1-focused-junit.xml
node --test --test-isolation=none tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
```

Final acceptance command set on the frozen inputs (input-matching successful
Node/package/syntax commands above may be marked Reused, not repeated):

```bash
docker compose run --rm --no-deps -T app mago format --check src tests
docker compose run --rm --no-deps -T app composer validate --strict
docker compose run --rm --no-deps -T app mago lint
docker compose run --rm --no-deps -T app mago lint --verify-baseline
docker compose run --rm --no-deps -T app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm --no-deps -T app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
docker compose run --rm -T app php vendor/bin/phpunit --display-all-issues --log-junit .phpunit.cache/o2-f1-full-junit.xml
node --test --test-isolation=none tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs
bash -n tests/Consumer/framework-package-export.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-required-inventory.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO[.]md:[0-9]+' src tests --glob '*.php'
git diff --check
node develop/orchestration/state.mjs --check
```

Security/crypto makes the frozen full suite required under spec109; use the
existing phpunit.xml 512M budget unchanged. The full replaces same-condition
focused PHP tests in Final. Do not add another full run for a new reviewer or
management-only change. Re-run affected checks only for a changed relevant
input, failure, environment difference or insufficient evidence. Static reuse
requires equality of actual selected source/config/lock inputs, not just source
filenames. No new baseline, metric waiver or memory override is authorized.

The full suite uses the existing shared DB for its inherited cases, but F1 adds
no PG fixture, migration or targeted PG/installation Consumer. Root confirms
owned temporary resources are cleaned and unrelated resources/work remain.
Source/test hashes before/after Final must equal the frozen candidate. Report
retains the common O1 predecessor plus exact O2/F1 union and each child's own
addition, suite issues separately
from failures, new guard controls, Node six-test counts and package counts.

Independent final Review checks exact scope, mathematical oracles, trust/time/
failure ordering, privacy, no activation and evidence, without repeating valid
gates. Root accepts locally only after Green and updates STATE via the required
CAS tool. Report records no public API/route/Source/Search/LLM change, the current
release-authority tuple and preserved public artifact hashes. Same-SHA export,
remote CI and release delivery remain parent006 qualification; inventory proof
is not a claim of committed-SHA package export.

## 12. Acceptance and remaining F dependencies

F1 Acceptance requires: the three Root choices adopted; exact nineteen types and
29-file scope; all closed wire/hash/trust contracts and matrix cases proven;
private data protected; no unlisted caller/activation; unchanged required quality
thresholds; input-matched green gates and independent Review; preserved mixed
work/public artifacts and completed Root Report/STATE. It is a pure local
prerequisite, not parent F or BlackOps1.3 completion.

Remaining F: trusted closed-segment full reader and fenced seal CAS; crash-stable
checkpoint IDs/timestamps/selected keys and immutable persistence; exact sign
authorization/access-before-release and sole derived control exemption; accepted
external-anchor provider and AnchorIntent pure state/persistence/recovery;
receipt inputs/sign/persist-before-ack; seal-versus-anchor-failure ordering;
authenticated exported-Catalog provenance; complete record/checkpoint/anchor
graphs, trust-context and freshness integration. Only Accepted F feeds D-control;
G still owns manifest pinning, file confinement, query/export/hosted orchestration
and the single full verification result/stage contract. Optional physical deletion
H remains outside1.3. No ordinary D, E, B2 or G implementation is added here.

## 13. Exact source anchors

| Authority/source | Relevant anchor |
| --- | --- |
| develop/spec/105-blackops-1-3-capability-contract.md | :3999 chain/closed/sealed ownership; :4080 and :4110 checkpoint shape/invariants; :4136 hash/signature; :4151-4167 roles and material/time trust; :4170-4183 access exemption and immutable persistence; :4185-4228 receipt wire/hash/binding/chronology; :4362-4396 external context and nested snapshot; :4413-4456 snapshot/status/window/caps; :4855-4859 raw file limits. |
| develop/spec/106-phase-23-delivery-plan.md | :2098 parent acyclic prerequisites; :2941-2978 complete F acceptance boundary; :3046-3052 signature release/control exemption; :3904-3908 artifact/string caps; :3936-3946 reusable verification versus G orchestration. |
| develop/decisions/148-audit-canonical-record-and-protected-store-boundary.md | :103-161 typed encoding, canonical partition/hash and UUIDv7 segment boundary. |
| develop/decisions/149-audit-postgresql-canonical-store-boundary.md | :258-269 existing segment grammar/lifecycle; :457-496 B2 closed state versus F-only sealed CAS. |
| develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md | :61-92 ordinary D/F independence and post-F D-control. |
| src/Internal/Audit/AuditJcsCodec.php | :23 safe integer; :44 canonicalJson; :65 toNative; :174 decode. |
| src/Internal/Audit/AuditStringValidator.php; src/Core/Audit/AuditRecordTime.php | :22 bounded safe text; :24 strict UTC timestamp. |
| src/Internal/Audit/Catalog/FrameworkAuditEventCatalog.php | :121-151 sign requested/unavailable are Durable, sign.accessed is Required. F1 does not emit or alter them. |
| composer.json; mago.toml | :10 ext-sodium already required; source/lint/baseline rules remain unchanged. |
| develop/spec/109-ai-development-workflow.md | :32-63 verification selection, full security gate and 512M; :65-90 evidence/reuse; :92 team ownership. |

The linked Task owns formal issuance and execution evidence. These commands
are a verification plan, not a claim that Product or gates have passed.
