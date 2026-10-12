# Specification 130: Audit Control Authority and Production Provenance

Status: Authority direction adopted — exact Product implementation remains unissued

[D179](../decisions/179-audit-signing-authority-and-production-provenance.md) adopts this most-specific control authority
and attributed-production contract together with [129](129-audit-online-authority-and-seed-custody.md),
[131](131-audit-ordinary-sign-service.md) and [132](132-audit-anchor-authenticated-provenance.md).
The [F5 Task](../orchestration/tasks/P23-003F5-audit-signing-authority-production-and-seal.md) must freeze exact Product
ownership and native qualification before issuance. BlackOps1.3 remains unreleased.

## 1. Decision

Use one installation-provisioned external control authority document containing
the current root-signed policy and one delegated, signed current lineage head.
Keep prior signed policies in an immutable external archive reached through
authenticated digest links, with no lifetime limit on retained revision count.
The root verification key is independently installed, as in the [online contract](129-audit-online-authority-and-seed-custody.md).
Use one distinct, narrowly authorized Ed25519 provenance delegate to authenticate
segment bindings and immutable F work reservations, extensions and outcomes.
The delegate uses the existing native sodium and JCS facilities. It does not sign
checkpoint/anchor/export artifacts, issue online trust, or change root policy.

Keep per-work signed provenance in F-owned PostgreSQL rows. Do not add an
external per-work journal or hold a filesystem lock across F2. A short owned
reservation transaction takes the existing B2 partition fence, checks the actual
F2 slot, and commits one authenticated production classification before F2's
separate prepare transaction. Every later extension inherits that reservation.
Unsigned SQL columns and retry indexes are lookup aids, never authority.

The two callers are deliberately asymmetric. The exact ordinary F owner can
reserve only ordinary production; the closed Framework control-maintenance
issuer can reserve control production for policy-authenticated system segments.
Neither caller supplies a production-class enum to a common signing method.
An ordinary invocation of the same segment or already completed control artifact
still requires its ordinary evaluator and Required sign-access release barrier.
Production classification is not a credential for later invocations.

This is smaller than an external work journal because signatures authenticate
the durable F binding, while PostgreSQL supplies transaction, uniqueness and
recovery. Its limit is explicit: it does not independently detect wholesale
rollback/deletion of both the signed work history and the associated F2 state.
That stronger property would require an external per-work monotonic witness.
It is not implied by a current signed policy, and is not proposed for v1.

## 2. Existing boundaries retained

The accepted B2,F1,F2 and A5 boundaries remain. The source anchors below identify
those retained contracts; the F5 Report holds the reviewed proposal evidence.

| Authority/evidence | Consequence for this contract |
| --- | --- |
| spec105:4001-4049; spec125:125-165 | B2 keeps one open segment per partition. A full protected-record/neighbor proof establishes a closed boundary. No second control partition, chain, or record-homogeneity assumption is introduced. |
| spec105:4170-4183,4980-4987; spec125:395-403,461-482 | The control family requires external signed policy and real invocation provenance. System tenant/partition, owner labels, complete F1 bytes and scheduler membership are insufficient. |
| spec125:185-215,223-253 | F2 prepare commits immutable unsigned inputs before returning the target; persist owns a separate root. Replay is storage idempotence, not current trust or release permission. |
| spec105:4616-4629; spec125:487-507 | F owns typed immutable outcomes. Later D-control performs applicable ingress before final acknowledgement; G follows both. F never directly appends canonical control events. |
| spec105:6568-6587 | AnchorIntent remains its exact closed A5 object. Provenance is a separate linked collection, not new fields or states in that object. |
| Accepted `ApplicationRetentionRuntime`, `MaintenanceScheduler`, and the control inventory | A real construction path exists, but it currently constructs no F control task or capability. The new exact construction/caller edges require adoption and guards. |

The current source has no preparation getter or provenance-aware admission API.
The later F packet must explicitly authorize its narrow F2-row read projection
and caller, rather than pretend that such an API already exists. F2's seven
preparation columns, public prepare/persist signatures, standalone roots, Clock,
UUID and immutable replay behavior remain unchanged by this contract.

## 3. Common authenticated-document primitive

Use the common envelope and domains in [specification129](129-audit-online-authority-and-seed-custody.md).
Every root/delegate signed object is the exact closed envelope:

```text
{schemaVersion:1,purpose,issuerKeyRef,payload,signature}
```

`purpose` is one of the finite strings `online-acquisition`, `control-policy`,
or `control-lineage`. Control-policy requires an installation-trusted root;
control-lineage requires the exact delegate grant selected from an authenticated
root policy. No public key, root trust, key-selection callback or alternative
algorithm is taken from an envelope. Ed25519 is pure RFC 8032; signatures are
canonical base64url without padding, decoding to 64 bytes. Public keys decode
to 32 bytes. Use existing `AuditLengthPrefix::encode`:

```text
LP(x) = uint32-big-endian(byte_length(x)) || x
signatureInput = LP("blackops.audit.authority-document.signature.v1")
              || LP(purpose) || LP(issuerKeyRef) || LP(JCS(payload))
envelopeDigest = lowerhex SHA256(
    LP("blackops.audit.authority-document.hash.v1")
    || LP(JCS(completeEnvelope)))
```

Version 1 is fixed before signature verification. The purpose and issuer ref
are signed, so a policy signature cannot be replayed as online acquisition or
work lineage. Root/delegate key material must differ from every F1 key and from
each other. The same installation root may authenticate online and control
documents because their purposes differ; this does not add a fourth F1 role.

Candidate shared implementation locations,not an issued API/file allowlist:
`Internal/Audit/Authority/AuditAuthorityDocumentCodec.php` and
`AuditAuthorityDocumentVerifier.php`. Keep these two helpers finite: bounded
canonical envelope handling and verification against an already resolved exact
key/expected purpose. Purpose-specific semantic validation and trusted internal
result construction belong to their owners. Decoding a valid signed payload
does not itself mint an invocation capability.

Common limits: canonical UTF-8 JCS, no BOM/CR/LF/trailing bytes, closed shapes,
no duplicate keys/floats/unsafe integers, exact UTC microseconds, and existing
NFC/no-control text rules. The complete external document is at most 1 MiB;
an individual control envelope is at most 64 KiB. Raw preflight precedes the
recursive decoder: depth at most 8, at most 32 members in any object, 256 entries
in any list, and 8192 value tokens across the complete document. Smaller
purpose-specific limits below also apply. Identifiers are at most 256 UTF-8
bytes; configuration key refs are at most 128 bytes. Digest fields are 64
lowercase hex. Counters are positive JSON-safe integers and never wrap.


### 3.1. Exact purpose,kind and delegate-permission map

The root verifies/signs online acquisition and control policy only through their
independent installation channels; a delegate permission never grants either.
Every delegated record below uses purpose control-lineage. These are finite
closed record/phase cases,not a caller-selectable sign(payload) interface:

| Signed payload kind / phase | Required permission and origin |
| --- | --- |
| audit_control_lineage_head | control_lineage_head; owned publication |
| audit_control_policy_transition | control_lineage_head; the publication's exact root_revision or segment_advance |
| audit_control_segment | segment_binding; owned full B2 close proof |
| audit_f_reservation / ordinary | ordinary_reservation; distinct ordinary-only registration |
| audit_f_reservation / control | control_reservation; closed control-maintenance issuer |
| audit_f_work_step / preparation_bound or complete_candidate | work_extension; authenticated reservation and actual owned phase |
| audit_f_work_step / artifact_persisted | production_outcome; actual committed or identically reconciled artifact |
| audit_f_work_step / ingress_acknowledged | ingress_ack; actual typed D-control completion or its validated exact exemption |
| audit_control_transition_ack | ingress_ack; actual ordered policy ingress completion |
| audit_anchor_reservation | anchor_reservation;132's atomic genesis owner |
| audit_anchor_revision | anchor_revision;132's actual named transition owner |

No permission implies another. Validation selects a closed semantic shape from
an authenticated record; only its fixed production owner may construct/sign it.
The common verification helper neither resolves a key nor grants construction.
The external authority wrapper,floors,historical-root records and compromise
markers retain their separate installed-custody shapes; they are not extra
delegate payload kinds or reusable invocation credentials.

## 4. Installation authority and exact policy shapes

Use a closed `audit.control` installation configuration with exactly:

```text
{installation_ref,authority_ref,authority_key_ref,authority_public_key,
 policy_archive_directory,policy_file,minimum_policy_version,minimum_lineage_revision,
 lineage_high_water_file,delegate_key_ref,delegate_public_key_fingerprint,
 delegate_seed_file}
```

The root ref/public key must agree with the online installation authority.
`policy_archive_directory` is one independently installed absolute local
directory with the fixed layout below. Its policy objects, historical root trust
records and compromise markers are distinct namespaces with distinct authority.
There is no in-memory list or lifetime count cap on historical root keys.
The installer precreates only the fixed `policies`, `roots` and `revocations`
subdirectories. Runtime F readers have no write permission there; the selected
root-policy publisher writes policy objects, while independent installation
administration alone provisions root trust and compromise records. A delegate
that may publish a signed lineage head cannot install a new root or remove a
restriction. No directory name or additional namespace comes from a document.
File paths are explicitly installed absolute local regular-file paths, and the
archive path is an explicitly installed local directory, all outside the Audit
database and repository. Reject symlinks, special files, substituted parent
paths, identity changes while reading, missing files and insecure ownership/modes.
Do not accept file paths, seed bytes, root keys or delegate selection from a
request, signed document, Audit row, environment fallback, or container alias.
The delegate seed file contains exactly 32 raw bytes and is provisioned separately
from F1 seeds; verify its derived public fingerprint against configuration and
the authenticated current grant before use. Secret handling is sensitive,
nonserializable and excluded from diagnostics. This contract reads no real keys.

The external file is one exact canonical document:

```text
{schemaVersion:1,kind:"audit_control_authority",
 policy:PolicyEnvelope,lineageHead:LineageHeadEnvelope}
```

The head's rootPolicyDigest must equal the current policy's complete envelope
digest. That policy's signed previousPolicyDigest commits to the immediately
previous complete policy envelope, recursively. The current file never embeds
or requires loading the whole revision history. Every accepted policy envelope,
including the current one, is retained outside the Audit DB at:

```text
<policy_archive_directory>/policies/<envelopeDigest>.json
```

The final component is exactly 64 lowercase hexadecimal characters plus `.json`;
it is derived internally from a validated digest, never a supplied path or URI.
An object contains exactly one canonical no-LF control-policy envelope, at most
64 KiB, under the unchanged common signature convention in section 3. Filesystem
owner/mode/device/inode and no-symlink/special-file checks apply to the configured
root, each fixed parent and each opened file. Readers use one opened handle,
read at most the object cap plus one byte, and compare the validated complete
envelope digest with the expected filename digest. They never enumerate this
directory, follow document-selected paths or accept a DB copy as archive authority.

Archive publication is part of this initial contract, not deferred archival work.
The installation root-policy publisher selected by this contract writes the
exact signed object
to a fresh regular temporary file in the fixed `policies` directory, flushes and
fsyncs it, then publishes it under the digest name with an atomic no-replace hard
link. An existing destination succeeds only after exact canonical-byte equality
and digest/signature validation; different bytes are an integrity failure.
The temporary and final names are on the same local filesystem; no cross-device
copy, overwrite rename or remote object service is a fallback. Fsync the
containing directory before a current policy/head may reference the
object. Remove only the publisher's unreferenced temporary name and sync that
cleanup; never delete a published object. A crash before publication leaves no
referenced object, and a crash after publication retries the same immutable bytes.
A lost response is resolved by the exact digest path, not a new revision.
If file or directory durability cannot be established on the supported filesystem,
publication fails before advancing authority. The native create/link/fsync path
and crash recovery are required initial implementation/qualification scope;
there is no unimplemented future archive extension needed for rotation.

For historical lookup, start from the currently authenticated policy/head and
walk the signed previousPolicyDigest links until the requested policy digest.
At each step validate the bounded object, signature with its independently
provisioned root key, matching installation/authority/policy identity, and exact
version decrement. Keep only the current/next envelope, expected digest/version,
and facts for the requested work; do not accumulate policies, keys, paths or
decoded history. A standalone valid root signature is insufficient if its policy
is not on this adopted chain. Missing, corrupt, mismatched, repeated or skipped
links fail closed. The counter decrement rules exclude cycles without retaining
an unbounded visited set. No fixed ancestor-distance limit makes older work
ineligible: lookup cost is linear in the required chain distance, with bounded
per-object reads and memory. Cancellation or I/O failure yields pending/failure,
never a successful truncated proof. If current authority changes during the
walk, authenticate its new tip and continuation to the already verified suffix;
perform the current permission/revocation checks before the next action.

PolicyVersion may advance through the existing JSON-safe integer capacity.
There is no maximum total object count, automatic pruning, deletion, or later
migration prerequisite. Filesystem exhaustion is an ordinary publication failure
with the prior authority intact. The 32-pending-transition limit in section 9
is backpressure on undelivered work, not a history-retention limit.

Historical root keys are independently provisioned installation trust records
at the fixed path:

```text
<policy_archive_directory>/roots/<rootRefDigest>.json
rootRefDigest = lowerhex SHA256(
 LP("blackops.audit.control.root-ref.v1") || LP(issuerKeyRef))
```

Each is at most 4 KiB and exactly
`{schemaVersion:1,kind:"audit_control_historical_root",installationRef,
authorityRef,keyRef,publicKey,publicKeyFingerprint}`. The installer publishes it
immutably with the same no-replace/durability procedure before retiring that
root from live configuration. Its keyRef must equal the requested issuer and
its fingerprint must match the decoded public key. The directory's independent
installation custody supplies trust, as the former configured key list did;
a policy, work row or runtime delegate cannot add these records. These keys
verify retained policy history only. The current policy still requires the
one root key in live configuration. No historical-root fallback is allowed for
a current document. One bounded file per historical root removes a second
potential lifetime limit without introducing a provider registry or service.

Current compromise status is deliberately separate from immutable policy bytes
and retained verification material. Under the same independently controlled
directory, the installer may only add immutable records at
`revocations/<publicKeyFingerprint>.json`, at most 4 KiB, with exact shape
`{schemaVersion:1,kind:"audit_control_key_compromise",installationRef,
publicKeyFingerprint,recordedAt}`. The filename digest is the material identity,
not a root of trust; independent directory custody authenticates this installed
restriction. It applies to root and provenance-delegate material, never supplies
F1 key status, and its self-declared time grants no historical exception.
Presence denies use according to section 10; an unreadable/malformed record or
inaccessible namespace fails closed. Absence is checked only in this installed,
verified namespace and means no installed compromise restriction for that exact
material. The installer serializes additions with the existing external authority
lock and publishes them durably before returning; removal/replacement is forbidden.
Before a new action, recheck the live root/current delegate and any historical
issuers on its provenance chain. A cached old-policy verification cannot suppress
a newly installed restriction. The local-custody/whole-installation-restore
limitation in section 9 applies here too; no independent rollback witness is claimed.

The exact `control-policy` payload is:

```text
{kind:"audit_control_policy",installationRef,authorityRef,policyId,
 policyVersion,previousPolicyDigest,issuedAt,notBefore,notAfter,
 family:"audit-control-v1",partitionId:"system:global",
 artifactRoles:["checkpoint","anchor"],retention,verifierKeys,
 currentSegment,previousLineageHeadDigest,delegateGrant,historyPins}

retention = {sealedBytes:"retain",verificationKeys:"retain",
             provenance:"retain",physicalDeletion:false}
verifierKeys = [{keyRef,role,publicKeyFingerprint},...]
currentSegment = {segmentId,firstSequence,predecessorRecordHash}
delegateGrant = {keyRef,publicKey,publicKeyFingerprint,notBefore,notAfter,
                 permissions,ordinaryScope:"canonical_partitions",
                 controlPartition:"system:global",
                 controlFamily:"audit-control-v1"}
historyPins = [{envelopeDigest,issuerPublicKeyFingerprint},...]
```

`policyVersion` starts at 1 and increments by exactly 1; previous policy/head
digests are null only at bootstrap. Policy ID and installation/authority refs
are immutable. The root's `currentSegment` must equal the effective current
segment at that revision's adoption. This is an open-slot identity, not a claim
that every record has control origin. Its predecessor hash is null only at
partition genesis. A root policy may start at an existing current open segment;
it does not retroactively authorize older closed segments for control production.

Verifier keys are sorted by role then keyRef, unique by ref and fingerprint,
at most 256 entries, and cover the allowed checkpoint/anchor keys. They constrain
selection in addition to the separately authenticated current online snapshot;
they never supply F1 current trust themselves. Retention deliberately preserves
all bytes, key history and provenance. Physical deletion remains excluded.

There is one active delegate grant. Its interval is inside the policy interval;
both use trusted current time for new actions. The exact permissions list is:

```text
["control_lineage_head","segment_binding","ordinary_reservation",
 "control_reservation","work_extension","production_outcome","ingress_ack",
 "anchor_reservation","anchor_revision"]
```

These nine permissions are exact. The last two have only the closed payloads
and atomic F4 owners in [specification132](132-audit-anchor-authenticated-provenance.md). An older
seven-permission policy grants neither action; no parser supplies those grants
as defaults. Publish the amended v1 policy before using them. Each permission
maps to one closed record shape and exact owner. It is
not a general sign(payload) permission. Ordinary scope allows authenticated
ordinary production reservations for canonical tenant/system partitions, which
never grant a control exemption. Control permission remains fixed to the one
family/partition. `historyPins` is normally empty and contributes at most 256
exact recovery pins per policy revision to the mechanism in section 10. Previously
accepted pins remain in the authenticated archive chain; a new empty list does
not erase them. Lookup searches only for the exact needed envelope/fingerprint,
using bounded per-record reads instead of building an aggregate pin list. These
pins supply historical authenticity only and never remove a current restriction
or authorize a new signature from compromised material.

## 5. Current and historical segment lineage

The `control-lineage` head payload is exactly:

```text
{kind:"audit_control_lineage_head",installationRef,rootPolicyDigest,
 revision,previousHeadDigest,observedAt,
 currentSegment,closedSegmentTailDigest,policyStateDigest,
 transitionTailDigest,acknowledgedTransitionDigest,transitionAckDigest}
```

Revision starts at 1, increments by 1, and never forks. rootPolicyDigest equals
the current complete policy envelope digest; historical membership follows its
authenticated archive chain, not an aggregate list hash. `policyStateDigest` is domain-separated
SHA-256 of JCS `{rootPolicyDigest,currentSegment,closedSegmentTailDigest}` using
`LP("blackops.audit.control.policy-state.v1") || LP(JCS(...))`.
The policy state includes the live segment pointer; it excludes publication
timestamps, revision and ingress progress. The delegate may update that pointer
only by authenticating consecutive B2 rotations under the root's grant.
This root-authorized live portion is an explicit clarification of “signed root
policy”; the root does not manually sign every rotation or work item.
The transition tail and acknowledged-through cursor authenticate the ordered
policy-event backlog described in section 9. The last two members are both null
until the first acknowledgement. They never replace the root policy or segment
history and are excluded from policyStateDigest.

Each historical closed-segment binding is one immutable delegate-signed F row
whose payload is exactly:

```text
{kind:"audit_control_segment",installationRef,policyDigest,
 previousSegmentBindingDigest,partitionId:"system:global",
 segmentId,firstSequence,lastSequence,recordCount,firstRecordHash,chainHeadHash,
 predecessorRecordHash,previousSegmentId,successorSegmentId,closedAt,
 closedStateVersion,closeFencingToken,observedAt}
```

The external head's `closedSegmentTailDigest` authenticates the latest such
row and its predecessor digest chain back to the root bootstrap. No row's
unsigned indexes or own recomputed digest can establish membership. To select
an older closed segment, verify the signed chain from the authenticated head
to its exact binding; retain the policy revisions needed to verify every grant.
A current pointer by itself is insufficient. A missing historical binding,
unknown grant, gap, duplicate, cycle, fork or wrong successor fails closed.

The real issuer obtains the accepted complete closed-segment proof under its
own B2-fenced read root. It compares the proof with the last authenticated open
slot: exact segment/first sequence/predecessor, complete range/count/first/head,
previous segment, successor, closedAt, captured closed version and token.
The successor's first sequence is last+1 and its predecessor hash is the old
head. Preserve arithmetic bounds. Store the signed close binding, then publish
the signed external head that points to the actual B2 current open slot.
If several rotations occurred during downtime, follow every consecutive edge;
process at most 32 closed edges per tick, retaining bounded signed staging rows.
Do not publish a synchronized-current claim or start new control work until
the walk reaches the actual current open slot.

These are attestations by the closed issuer after the accepted protected-record
proof, not an inference from clear B2 row hashes. Root bootstrap plus consecutive
authenticated close bindings authorizes the exact selection. F2 still performs
its own full proof on prepare/persist. Seal recovery accepts only the existing
captured-version+1 rule and unchanged close boundary. No new append/close owner
or second open segment is created.

## 6. Actual construction and provenance owner

Adopt this exact existing-to-new caller chain, with new names descriptive rather
than class/API reservations:

```text
ApplicationConsoleKernel framework scheduler commands
  -> ApplicationRetentionCommandFactory
  -> ApplicationRetentionRuntime
  -> MaintenanceScheduler::runTasks
  -> final F control-maintenance task::run
  -> final private control-work issuer
  -> closed F checkpoint / anchor continuation
```

`ApplicationRetentionRuntime` is the sole production constructor of the new
task/issuer relationship and its delegate custody. Its application configuration
must pass the installed source/key/path validation; arbitrary MaintenanceTask
membership or a Framework-looking name cannot obtain this relationship.
The new task receives no actor, tenant, family, target, key, segment, callback,
control flag or serialized capability through `run(now)`. Its trusted clock
is the composition-owned clock; the scheduler argument is not signing authority.
It selects work internally from the authenticated lineage and persisted recovery
records. The capability is private to this invocation and bound to the selected
work/segment; it is never returned or stored as a reusable bearer token.

The ordinary F composition gets only a distinct final ordinary-registration
entry that hardcodes ordinary production and validates the real ordinary-owner
invocation. That entry cannot call control reservation or the control release
continuation. The shared provenance recorder owns the delegate internally and
has closed, phase-specific methods; it exposes neither seed nor generic signing.
The ordinary owner remains responsible for the real exact-target evaluator and
C3 barrier specified by the ordinary-sign adoption. The real producer/Operation contract is [specification131](131-audit-ordinary-sign-service.md);
synthetic metadata cannot replace it.
The same `ApplicationRetentionRuntime` F composition constructs the one recorder
and supplies that ordinary-only entry to the real ordinary owner; no general
container lookup constructs a second delegate holder. Broader ordinary transport
exposure needs its own explicit composition review.

The two entries share one monotone graph-usability state. Ambiguous/invalid
storage roots and actual ordinary Durable-delivery call failures invalidate it
before any subsequent phase. Both entries refuse the same cached graph before
lifecycle preparation,provider calls or selection; no connection reconnect or
scheduler cache reset restores it. Fresh recovery constructs a new entire graph
and authenticates its actual persisted winner. The exact delivery-edge rule and
healthy terminal distinction are specified by131; this adds no C3 store getter
or synthetic rollback proof.

All legitimate producers must participate in this reservation protocol. A caller
that writes F2 directly would bypass production provenance and must be rejected
by exact construction/call guards. Required proof includes aliases, group imports,
class strings, indirect callables, reflection, extra factories/getters, serialized
tokens, wrong task constructors and ordinary-to-control fallback. PHP visibility
and architecture tests enforce the trusted application-code boundary; they are
not a sandbox against arbitrary code executing with the delegate's OS privileges.

## 7. Durable F work shapes and write order

Use three logical F-owned collections, with migration/file inventory fixed in
the later implementation packet:

1. Immutable reservations: work ID, unique artifact slot, signed reservation
   bytes/digest. Checkpoint slot is `(partitionId,segmentId,audit_checkpoint)`;
   receipt slot is `(partitionId,intentId,audit_anchor_receipt)`. A receipt binds
   the exact A5 intent/triple/version and cannot create another slot on retry.
2. Immutable signed steps: `(workId,ordinal)` unique, predecessor step digest,
   canonical envelope/digest, and optional complete-candidate bytes. Candidate
   bytes are stored in the same transaction as the signed candidate digest.
3. Mutable retry/dispatch indexes: work/source digest, next attempt and finite
   pending state. They may be rebuilt; an “acknowledged” index bit proves nothing.

The signed segment bindings from section 5 are a fourth, separate immutable
collection. SQL metadata must equal decoded signed content. Foreign keys and
unique indexes enforce the one reservation/linear step graph. No UPDATE/DELETE
of authenticated rows, no UPSERT replacement, no ambient transaction around F2,
and no secret/trust-snapshot persistence in these collections.

Reservation payload (`control-lineage` purpose):

```text
{kind:"audit_f_reservation",installationRef,policyDigest,workId,selectedAt,
 productionClass,origin,artifactKind,partitionId,segmentId,segmentBindingDigest,
 signingKeyRef,signingKeyFingerprint,checkpointReservationDigest,intentBinding}

origin = "ordinary_sign_owner" | "framework_control_maintenance"
productionClass = "ordinary" | "control"
intentBinding = null | {intentId,checkpointId,checkpointHash,
 checkpointBytesHash,checkpointCompleteBytesSha256,anchorStoreRef,
 anchorObjectRef,expectedPredecessorVersion,expectedPredecessorHash,
 previousAnchorReceiptHash,anchorReservationDigest,anchorReceiptId}
```

Origin/class are derived constants of the two different constructors, with the
obvious exact pairing. Work ID uses the existing internal UUIDv7 generator and
is generated once for a new committed reservation. The checkpoint binding and
intent binding are null for checkpoints. A receipt links its checkpoint's signed
reservation/outcome, exact complete checkpoint bytes and A5 identity. Automatic
receipt production inherits that checkpoint's production class. An ordinary
later invocation may release existing control bytes only through ordinary access;
it cannot reclassify the underlying production or create a control receipt from
an ordinary/unattributed checkpoint.
The receipt reservation is admitted after A5's external acceptance is known and
before its receipt-input freezing step. Its selected key is passed to that real
owner under A5's existing rules; no field is written early into AnchorIntent.
Checkpoint reservation/prepare order below does not replace A5's external-call,
lease, accepted-object or receipt-input transaction ordering.

Control reservations require an authenticated segment binding under section 5.
For ordinary reservations `segmentBindingDigest` is null: the exact segment still
comes from trusted ordinary selection and F2's full proof, not a control family.
Null never grants the exemption. Key ref/fingerprint are chosen by trusted current
acquisition and frozen before prepare. No later key substitution is permitted.

Each subsequent step has exact payload:

```text
{kind:"audit_f_work_step",installationRef,authorizingPolicyDigest,
 reservationDigest,workId,ordinal,previousStepDigest,phase,recordedAt,data}
```

`ordinal` starts at 1 and increases by exactly 1. The first previous step digest
is the reservation digest. `authorizingPolicyDigest` permits an active replacement
delegate to recover authentic old work without altering the original reservation.
Only the following phase data are accepted, with absent members rejected:

| phase | Exact `data` members |
| --- | --- |
| `preparation_bound`, checkpoint | `{artifactKind,artifactId,artifactHash,unsignedBytesSha256,signedAt,signingKeyRef,signingKeyFingerprint,closedStateVersion,closeFencingToken}` |
| `preparation_bound`, receipt | `{artifactKind,artifactId,artifactHash,unsignedBytesSha256,signedAt,signingKeyRef,signingKeyFingerprint,intentId,intentInputStateHash,checkpointId,checkpointHash,checkpointBytesHash,checkpointCompleteBytesSha256,anchorStoreRef,anchorObjectRef,anchorObjectVersion,previousAnchorReceiptHash}` |
| `complete_candidate` | `{artifactKind,artifactId,artifactHash,preparationStepDigest,completeBytesSha256,completeByteLength}` |
| `artifact_persisted` | `{artifactKind,artifactId,artifactHash,candidateStepDigest,completeBytesSha256,completeByteLength,productionClass,requiredIngress}` |
| `ingress_acknowledged` | `{sourceOutcomeDigest,artifactKind,artifactId,completeBytesSha256,ingressKey,deliveryDisposition}` |

`artifactHash` remains F1 checkpointHash/anchorReceiptHash; no new artifact hash
wire is introduced. The `*BytesSha256` members are ordinary SHA-256 over the exact
canonical no-LF bytes named by the field, authenticated by the enclosing signature.
They are distinct from F1's domain-separated checkpointBytesHash. Reuse the F1
codec byte limits for stored candidate bytes; they need not be embedded/base64
expanded in the provenance envelope. Complete byte length and every binding must
match actual bytes. Receipt preparation comes from the exact A5 frozen receipt
input state; its existing complete-byte/hash fields retain their A5 definitions.

`requiredIngress` is derived exactly: ordinary => `artifact_control_event`,
control => `exempt_control_artifact`. A mismatched value is invalid even with a
valid delegate signature. The immutable `artifact_persisted` step is the typed
F production outcome. It is reconstituted only by the closed verified recovery
owner after checking the full reservation/step chain and actual artifact bytes.
An arbitrary array, signed policy plus row bit, F1 artifact alone, or deserialized
class cannot produce this outcome.
For `ingress_acknowledged`, deliveryDisposition is exactly
`event_ingress_committed` for ordinary production or `exempt_control_artifact`
for control production. In the latter case ingressKey is null and no canonical
ingress is claimed; in the former it is the exact non-null key in section 9.

The committed order is:

1. Acquire current external policy/lineage and online trust. Preserve the exact
   policy transition's pending/acknowledged state separately from permission to
   execute an F primitive. Select the exact eligible closed segment internally;
   recover existing reservations first. Pending ingress never becomes final ack.
2. Begin a short standalone F transaction, take the B2 partition fence, inspect
   the unique F slot and actual F2 preparation/complete presence. A new reservation
   requires both absent. A present slot requires a valid existing reservation;
   an existing F2 row without one is unattributed and fails closed. Sign and insert
   the reservation inside this root, commit, then return it. A lost commit response
   is reconciled by exact signed-row lookup under the same fence; never guess.
3. Call existing F2 `prepare` with the reservation's original key ref and a
   current authenticated snapshot in F2's own root. On return, compare the full
   unsigned body, fingerprint and boundary to the reservation/closed proof and
   commit `preparation_bound`. Refresh current time/context after prepare; do not
   rewrite F2 signedAt or claim that earlier context authenticates a later time.
4. Ordinary: evaluate the exact resulting target and preserve requested/denial/
   unavailable semantics. Control: retain the private invocation capability and
   recheck current policy, online trust and exact preparation. Only then compute
   F1 bytes. Validate those bytes and persist their signed `complete_candidate`
   step plus exact bytes before invoking the complete-artifact persistence call.
5. Persist the exact candidate through F2, or the later accepted receipt store,
   using their own transaction and current verification rules. After committed
   success/reconciled identical replay, sign/commit `artifact_persisted`.
6. Continue accepted anchor/seal orchestration with current checks. Ordinary
   external byte release still follows successful Required/null-key accessed
   delivery for this invocation. Candidate persistence is internal custody,
   never an external file/response/export or anchor release.
7. Later D-control consumes the verified production outcome, performs required
   idempotent ingress or validates the exact control exemption, and returns the
   narrow completion result. Final orchestration acknowledgement also waits for
   all applicable anchor/seal/policy conditions, not merely this one result.

Every step append uses its own short transaction, the exact current predecessor
and uniqueness checks. Concurrent losers reload and validate the committed winner;
they never overwrite its timestamp/signature or emit a second outcome. Two valid
but conflicting signatures for one logical slot are an integrity failure, not
“latest wins.” No external filesystem coordination is held through these roots.

## 8. Crash recovery and the limits of attestation

| Durable point at crash | Recovery and acknowledgement limit |
| --- | --- |
| Before reservation commit | No durable work identity; a new trusted selection may retry. An ambiguous commit first reconciles the unique signed reservation. |
| Reservation committed, F2 absent | Verify its signature/current grant and exact slot, then execute original-key prepare. Ordinary/control cannot compete for a new class in that slot. |
| F2 prepare committed, no preparation step | Only the owner of that authenticated reservation may retry existing F2 prepare with the original key. F2 re-proves its persisted boundary/body, returns identical inputs, and the recorder binds that result. It cannot invent a replacement ID/time/ref or accept an unrelated row as provenance. |
| Preparation bound, before candidate commit | Current reauthorization/trust is required to compute bytes; a revoked/unavailable key leaves the original work pending, not replaced. |
| Candidate committed, complete artifact absent | Recover exact candidate bytes, verify signature/binding/current trust and retry the same persistence. No new candidate/body or anchor request is generated. |
| Complete artifact committed, no outcome | Require byte-for-byte identity with the authenticated candidate, reconcile the store, and append the one outcome. A complete row without that candidate is not adopted as control or ordinary production. |
| Outcome committed, D-control absent/failing | Preserve the outcome and pending index. No final orchestration acknowledgement. F primitive qualification may stop here truthfully. |
| D-control ingress committed, acknowledgement lost | Retry the exact idempotency key and exact immutable event input through D-control. After successful identical ingress, append the signed acknowledgement. No artifact mutation or new policy transition. |

The admission proof uses PostgreSQL's accepted transaction/fence and immutable
storage contract. Signed reservation plus the restricted complete-candidate
path, not F2's unsigned row alone, establishes production origin. In particular,
the delegate must not offer “sign this DB row” or reconstruct a missing
reservation from an unsigned origin flag. It signs an extension only after the
authenticated prior state and that phase's real owned operation/reconciliation.

Signatures reject arbitrary recomputable forged reservations/steps, wrong keys,
class changes, phase substitution and recomputed hashes. They do not prove that
a DBA never deleted every prior signed reservation and artifact or restored a
mutually consistent old database. Such restoration is an installation recovery
operation: stop production, compare independently retained policy/freshness/anchor
evidence, and explicitly re-establish the allowed history before reactivation.
No ordinary/control inference from time alone, no automatic new reservation over
an unattributed existing artifact, and no unqualified rollback-resistance claim.
If resisting arbitrary coordinated Audit-DB history rollback is a required threat
model, this signed-row route is insufficient; a per-work external witness is a
real extra requirement, not a free property of this contract.

## 9. Policy changes, publication and later D-control

A root policy revision and a delegated current-segment advance are both semantic
policy changes. This conservative choice avoids quietly exempting a change to
the policy's required current segment. Both create exactly one transition:

```text
transitionId = lowerhex SHA256(
 LP("blackops.audit.control.policy-transition.v1")
 || LP(JCS({installationRef,previousPolicyStateDigest,newPolicyStateDigest})))
```

Each transition is an immutable control-lineage envelope with exact payload:

```text
{kind:"audit_control_policy_transition",installationRef,authorizingPolicyDigest,
 transitionId,previousTransitionDigest,previousPolicyStateDigest,
 newPolicyStateDigest,cause,createdAt}
```

`cause` is `root_revision` or `segment_advance`; initial activation is a root
revision with null previous state/transition. Persist the signed transition and
new signed publication candidate together, then publish that head using the
file protocol below. The head's transitionTailDigest points to this immutable
transition; its predecessor chain authenticates all pending earlier transitions.
Its derived state is exactly `pending_ingress` or `acknowledged`, according to
the signed head's contiguous acknowledgedTransitionDigest cursor and matching
ack envelope. This is separate from A5 acknowledgement. A head refresh or
acknowledgement-only update with unchanged policyStateDigest creates no event.
transitionEnvelopeDigest below refers to this stable transition envelope,
not a mutable head refresh. Keep transition/ack/publication envelopes in finite
typed F-owned collections alongside the signed segment/work rows.

Publish the new authenticated policy state as pending, retain its predecessor
and transition identity, then make it available to the later D-control consumer.
The authenticated policy may authorize bounded F primitive work while its event
is pending; otherwise the real F producer could not be qualified before D-control.
Its durable results remain pending at the outer orchestration boundary. Production
scheduler activation requires the later real D-control integration and proof;
F primitive Acceptance supplies neither activation nor an event acknowledgement.
Published restrictive changes/revocation deny subsequent protected-phase
acquisitions; an unavailable consumer never preserves an old grant or permits a
fallback. Serialize semantic
publications but allow up to 32 ordered pending transitions, so bootstrap and a
real rotation can be qualified before D exists. Reject an enabling publication
that would exceed this bound. Emergency installation suspension stops further
production even at the bound; recovery must retain and drain its ordered
transition evidence before replacement activation. D consumes pending transitions
oldest first; a later acknowledgement can never skip an earlier pending event.

The later D-control packet must define the real Catalog/provider tuple and exact
safe evidence. This contract assigns no event name, Catalog ID or version that
does not exist. Its input is the verified typed policy-transition outcome with
old/new state digests, root policy identity, cause and current-segment bindings.
Ingress is keyed by this immutable transition ID, has depth one, and does not
audit its own ingress again. Artifact ingress remains keyed by artifact identity,
with kind included where necessary to avoid checkpoint/receipt ambiguity.
The exact raw keys are finite ASCII `control-policy:<transitionId>` and
`control-artifact:<artifactKind>:<artifactIdDigest>`, where artifactIdDigest is
lowerhex SHA-256 of `LP("blackops.audit.control.ingress.v1") || LP(artifactKind)
|| LP(artifactId)`. This binds the actual artifact ID without exceeding the
existing 256-byte ingress cap for an otherwise valid long F1 ID. Any same-key
different-body collision fails; it never replaces an event.

Only normal committed/idempotent return from the accepted D-control/C3 path
permits its closed caller to append a signed ingress acknowledgement. The
delegate authenticates this owned delivery result, never an arbitrary SQL
“delivered” bit. On failure, pending remains. The policy acknowledgement is a
control-lineage envelope with exact payload
`{kind:"audit_control_transition_ack",installationRef,authorizingPolicyDigest,
transitionEnvelopeDigest,transitionId,previousPolicyStateDigest,
newPolicyStateDigest,ingressKey,acknowledgedAt}`. The subsequent signed external
head advances acknowledgedTransitionDigest to that transition and sets
transitionAckDigest to this acknowledgement without changing policy state.
Earlier pending transitions must already be acknowledged; the tail may remain
ahead of this cursor. Only equality with the tail establishes a fully drained
policy backlog. A crash before head publication reuses the same signed ack.

Control artifact outcomes require no checkpoint/receipt event, but their exact
signed provenance is still verified by the later D-control orchestration. It
records `deliveryDisposition:"exempt_control_artifact"` for that outcome, not a
fabricated canonical event. An ordinary re-release of those bytes remains an
ordinary sign invocation; it neither creates another production outcome nor
inherits sign-access exemption. Conversely, a control job cannot promote an
ordinary or unattributed artifact to suppress its required control event.
For this historical persistence fact, D-control authenticates the provenance
chain and exact canonical artifact bytes. It does not declare the artifact
currently verifiable or feed an old snapshot into F1 to manufacture current
validity. A later sign/release/seal still performs its current F1 checks.

The external policy file and high-water file are installed independently of the
Audit DB. All acquisition, publication, publication recovery and installation
restriction writers use the same existing high-water inode and exclusive
nonblocking `flock(LOCK_EX | LOCK_NB)`. Contention returns unavailable; there is
no wait/retry loop while retaining another resource. A control acquisition never
holds this lock across F2, a work/ingress/seal transaction or an external call.
Its caller completes acquisition before entering that phase's transaction. The
online stream keeps its separately specified lock and phase placement; do not
nest the two floor locks or claim a shared atomic trust/database transaction.

The control floor is one canonical no-LF regular file, at most 4 KiB, with exactly:

```text
{schemaVersion:1,kind:"audit_control_floor",installationRef,authorityRef,
 rootPublicKeyFingerprint,minimumPolicyVersion,acceptedPolicyVersion,
 acceptedRevision,acceptedDocumentDigest,pendingRevision,pendingDocumentDigest,
 sourceObservedAt,observedNow}
```

`acceptedDocumentDigest` and `pendingDocumentDigest` are ordinary lowercase
SHA-256 of the exact complete canonical `audit_control_authority` document bytes.
They are not the shared envelope digest: that document wraps two signed envelopes.
Each envelope retains the unchanged shared signature/hash convention. The stored
root fingerprint must equal the independently configured root fingerprint.
minimumPolicyVersion is positive; acceptedPolicyVersion and acceptedRevision
are nonnegative JSON-safe integers only under the following closed state matrix.

| Floor state | Exact constraints |
| --- | --- |
| Independently provisioned initial state | For a new installation only: both configured minima and stored minimumPolicyVersion are 1; acceptedPolicyVersion=acceptedRevision=0; acceptedDocumentDigest, pendingRevision, pendingDocumentDigest, sourceObservedAt and observedNow are all null. The installer precreates the regular policy file with zero bytes and durably provisions both files. No authority exists yet. |
| First publication pending | Both configured minima and stored minimumPolicyVersion remain 1. Accepted versions remain 0 and acceptedDocumentDigest/sourceObservedAt remain null. pendingRevision=1, pendingDocumentDigest is non-null, and observedNow is the non-null publisher Clock sample durably recorded when pending was established. |
| Accepted with no publication pending | Accepted versions are positive, acceptedPolicyVersion <= acceptedRevision, and acceptedDocumentDigest/sourceObservedAt/observedNow are non-null. Both pending members are null. |
| A later publication pending | Retain the complete positive accepted tuple and sourceObservedAt. pendingRevision=acceptedRevision+1, pendingDocumentDigest is non-null, and observedNow is nondecreasing. |

Every other null/zero/positive combination, overflow or partial pending pair is
invalid. The zero sentinel is not a reset or upgrade path for an existing
installation. Framework consumers never create, initialize, replace the inode,
reset or repair the floor. Nonzero recovery floors come only from the stopped
independent installation recovery described below, with authenticated retained
state at least as strong as the last durable accepted/pending state.

The first publication is explicit. The installer provisions the configured
delegate seed/ref/fingerprint and archives a valid root-signed policyVersion 1.
That policy has null previousPolicyDigest/previousLineageHeadDigest and the exact
bootstrap currentSegment. Its root-authenticated delegateGrant, together with
the independently installed matching delegate pin, authorizes the closed
publisher to sign the first `root_revision` transition and lineage head. The
head has revision 1, null previousHeadDigest/closedSegmentTailDigest and null
acknowledgement cursors; it points to that real pending transition. The first
transition has null previous transition/state digests. No D acknowledgement or
work-origin capability is fabricated. This narrow installation publication
does not call ordinary authority acquisition on an empty source or use an old
grant; it is permitted only against the independently provisioned zero sentinel.
It cannot sign ordinary/control work, F1 artifacts, or any caller-selected payload.
The normal acquisition entry cannot invoke or select this bootstrap path.

The publisher's finite publication/recovery order is:

1. Authenticate the root policy and installed delegate binding, create or recover
   the exact signed candidate and transition, and ensure the policy envelope and
   historical inputs are durably archived. Persist the immutable publication
   candidate/transition before acquiring the floor lock; do not perform SQL under
   that lock. Candidate creation owns its trusted signing-time capture; its
   recorded timestamp does not replace the later publication/acquisition Clock.
2. Open the existing floor, acquire the nonblocking lock, and validate its bounded
   canonical bytes, identity and exact state. A new candidate requires the expected
   accepted predecessor and the next revision, or exactly the first-use case above.
   If pending already exists, only the exact pinned revision/document digest may
   be recovered. Fetching its immutable publication row occurs outside this lock;
   after reacquiring, compare the entire expected floor before proceeding. A stale
   capture or another pending candidate returns unavailable/conflict, never latest-wins.
3. Sample the trusted Clock exactly once in this locked publication attempt.
   Validate its shape/rollback, the candidate's current root/delegate intervals,
   installed pins and current compromise restrictions. Require the source
   observation <= now and nondecreasing against the last accepted source observation;
   require now >= stored observedNow when present. Validate the complete predecessor,
   policy-version and transition bindings. A head-only update preserves the policy
   version/digest; a root-policy change increments its version exactly once.
4. Before touching the source, durably write pendingRevision/pendingDocumentDigest
   and observedNow=now while preserving the accepted tuple. Then write the exact
   candidate to the existing policy-file inode and durably flush it. Finally write
   the accepted policy version, revision, document digest, sourceObservedAt and
   observedNow=now, set minimumPolicyVersion to the greater of its stored and
   configured minimum, and clear pending. The candidate must satisfy both
   configured minima and the stored policy minimum before any pending write.
   Each file update uses seek-to-zero, bounded full-write loop, exact ftruncate,
   fflush and fsync, with every result checked. Recheck identity/owner/mode; no
   rename-based floor replacement or directory-durability assumption is used here.
5. Only after the final floor fsync may the publisher report publication success.
   That success is neither an acquired phase permission nor a D-control
   acknowledgement. A consumer must still perform the acquisition below.

Every ordinary/control consumer uses this finite acquisition sequence:

1. Validate the installed configuration and local file metadata. Open the existing
   floor, obtain the same exclusive nonblocking lock, and read its canonical state
   from that descriptor with a cap+1 bounded read. Validate refs, root fingerprint,
   ranges/nullability, owner/mode and inode identity. A missing/corrupt floor fails;
   it is never reconstructed from the policy file or Audit DB.
2. If either pending member is set, return unavailable without exposing any old or
   new authority. In particular, accepted R1 + pending R2 + still-valid R1 source
   is unavailable. Only the separate publisher recovery may finish R2. A zero
   accepted revision also returns unavailable, even if a signed source was copied
   into the file without completing publication. No consumer clears pending or
   performs first-use initialization.
3. While still locked, open/read the policy file from one checked descriptor,
   bounded to 1 MiB plus one byte; verify stable regular-file identity and the
   common raw/canonical limits. Authenticate the current policy against the live
   configured root only, then its lineage head against the exact root-granted
   delegate matching configured ref/fingerprint. Compare the complete document
   digest, head revision and policy version exactly with the accepted floor tuple;
   neither a higher uncommitted source nor an older source is accepted. Require
   the configured revision/policy minima and stored minimumPolicyVersion.
4. Validate family/partition, policy/head/grant bindings, source observation and
   current installed root/delegate compromise restrictions. If this phase depends
   on historical provenance, its required historical-issuer restriction checks
   also occur under this lock before acceptance; earlier verification cannot bypass
   a newly added marker. Historical filesystem lookups retain the per-object,
   streaming bounds from section 4; no SQL or work transaction runs under the lock.
   An archive authenticates old origin, never supplies a live-grant fallback.
5. Sample the composition-owned trusted Clock exactly once while locked. Reject
   throws/invalid time or now < stored observedNow. Require policy.issuedAt <= now,
   policy.notBefore <= now < policy.notAfter, delegate.notBefore <= now <
   delegate.notAfter, and head.observedAt <= now. The accepted sourceObservedAt
   must equal that authenticated head observation. Publisher observation and
   local now have separate watermarks: never compare a new source observation
   against an earlier consumer Clock sample. Invalid/pending acquisitions may
   fail before reaching this sole Clock read; no document supplies now.
6. Before returning any immutable internal authority result, durably advance
   observedNow to that sample in the same floor inode under the same lock using
   the exact full-write/ftruncate/fflush/fsync procedure above. Preserve all other
   accepted fields and null pending pair. If the entire state is unchanged, its
   already durable bytes need no rewrite. Unlock/close successfully, then return.
   A short write, flush/fsync/close failure grants no result, seed access, signature,
   release or seal permission. Seed files, F1, F2 and database work are outside
   acquisition and occur only after the lock is released.

First-use and normal crash handling are the same once pending is durable. A
crash before pending leaves the initial/old accepted source authoritative only
under a fresh normal acquisition. A crash after pending, whether the source still
contains R1, partially written bytes or the complete R2, releases no authority.
With an intact floor, the publisher may retry only the exact pinned candidate,
revalidate current grant/clock/restrictions, and finish its source/floor writes.
It cannot regenerate a different head at the same revision or clear pending to
restore R1. A crash after final fsync but before response is an identical accepted
publication; a later consumer still samples time and advances observedNow.
Missing/torn/corrupt floor, missing exact candidate, expired/revoked candidate
authority or failed recovery remains unavailable and requires stopped independent
installation recovery; there is no runtime downgrade to the old authority.

All processes for this installation share these exact files. Ordinary restart
preserves accepted/pending state and both watermarks. A stopped recovery must
establish a floor no lower than the last durable accepted or pinned pending
revision/policy boundary and preserve the clock watermark; it may not restore an
older DB/application backup as authority. Signatures and a local floor cannot
detect restoring all files/configuration/DB and trusted time together; the
independent installation channel must re-establish minima/time in that case.

Reacquire at each named protected control phase: before reservation admission,
before a new provenance signature, before granting the control external-release
exemption, before ingress acknowledgement, and immediately before entering the
seal phase. Refresh after preparation or other intervening work as those phase
owners require. Publication and compromise restrictions affect subsequent
successful acquisitions; an observed pending publication never permits fallback.
A restriction cannot retroactively undo a phase that already obtained permission.
Validity is at the owned Clock sample, not continuously guaranteed through a
later DB commit, OS delay or external call. Do not describe this as instantaneous
global revocation or an atomic trust-update/transaction-commit boundary. This
control contract neither adopts the real ordinary evaluator/release integration
nor changes the [online contract](129-audit-online-authority-and-seed-custody.md)'s phase samples or F1/F2/A5 ownership.

F local Acceptance must exercise the actual ApplicationRetentionRuntime and
MaintenanceScheduler producer in its isolated qualification installation, with
real signed policy/delegate files and durable work. With an absent D consumer,
the final task preserves those results but takes the existing scheduler exception
path using a safe ResourceUnavailable failure; it cannot return normal overall
success or mark the task completed. No test-created control token, dummy D adapter
or fabricated event acknowledgement is needed. It cannot claim policy-event
delivery, final orchestration acknowledgement or live scheduler activation. Operational activation follows
Accepted F, real D-control integration and its non-recursion proof; G follows both.

## 10. Rotation, revocation and historical evidence

Root keys, provenance delegates, and F1 keys have separate lifecycle semantics.
Their refs and public fingerprints are immutable. Never rebind an existing ref
to new material or use record-protection keys as a substitute.

Root rotation follows the unchanged [online contract](129-audit-online-authority-and-seed-custody.md)'s stopped installation
contract. Stop and drain every user of either stream and resolve any in-flight
publication before changing installed key bindings. Retain durable work,
preparations, transition/ack history and pending D-control events; draining users
does not mean deleting or falsely acknowledging those records. An unresolved
publication-pending floor stays unavailable and requires the exact recovery in
section 9 before ordinary cutover. If its authority is no longer usable, remain
stopped for independently established recovery; do not clear it to an older head.

Through the independent deployment channel, archive the old root trust record,
install a fresh root key ref/public key, and update both fingerprint-bound
floor/configuration bindings while users remain stopped. Preserve online
epoch/hash/source-observation/local-clock state and control accepted/pending
tuples, source/local watermarks and all configured/stored minima; none decreases
or becomes an initial sentinel. Historical old-root records remain lookup-only.
Stage a new-root-signed online document at the next epoch and a control policy
at the next policy version, preserving each stream's own predecessor/equivocation
rules. The control policy binds the last accepted head; its next head is signed
by the installed delegate under that new root grant and uses the stopped
installation publication path below. Existing old-root source files cannot be
acquired under the new root pin. Restart users only after coherent source,
configuration and floor state has passed fresh acquisition in both streams.
There is no live root reload, dual-key grace, document-supplied root or old-key
fallback. A compromise requires independently vouched-for recovery evidence;
retaining an old root record does not override a current compromise restriction.

Delegate rotation is also a stopped, drained installation operation. A policy
revision alone cannot change the independently configured delegate ref,
fingerprint or seed. Use the following finite cutover with the root unchanged:

1. Stop and drain every control-acquisition, publication and delegate-seed user.
   Resolve any existing pending publication with its exact candidate under the
   old installation before replacing its bindings; otherwise remain stopped for
   independent recovery. Keep all immutable F work and the D-control backlog.
   Reserve one place within the existing 32-transition pending bound by draining
   actual ingress when necessary; rotation cannot skip its real policy event.
2. Retain the exact accepted policy/head, delegate grant, lineage and historical
   root records in their existing archive/immutable F locations. The root signs
   exactly the next policy revision: previousPolicyDigest is the accepted policy
   envelope digest and previousLineageHeadDigest is the accepted head digest.
   Its delegateGrant names one fresh key ref and public key/fingerprint, with a
   currently valid interval and the fixed permissions above. The policy retains
   the accepted currentSegment and the existing installation/family bindings.
3. Through the independent installation channel, provision the replacement
   regular raw-32-byte seed file and update delegate_key_ref,
   delegate_public_key_fingerprint and delegate_seed_file together in the stopped
   configuration. Derive its Ed25519 public key using native sodium and require
   exact equality to the new root-authenticated grant/ref/fingerprint. Neither
   the policy nor a database row selects a seed path. Use a fresh ref/material;
   never rebind an old ref, overwrite its historical grant, or retain an automatic
   old-seed fallback. Provisioning/configuration failure leaves users stopped.
4. The narrowly owned stopped installation publisher authenticates the new root
   policy against the live installed root and the new delegate against its
   independently installed pin/seed. That new grant explicitly authorizes the
   first new `root_revision` transition and head; the old grant does not sign or
   authorize this step. It does not call normal acquisition against the old
   source, which now correctly fails the configured delegate match. The candidate
   has revision=acceptedRevision+1, previousHeadDigest equal to the accepted head,
   rootPolicyDigest equal to the replacement policy, and unchanged currentSegment,
   closedSegmentTailDigest and acknowledgement cursors. Its new transition links
   the existing transition tail and old policyStateDigest to the new policy state.
   Only that transition changes the tail. The old predecessor is authenticated
   through retained history solely for continuity, never as a current grant.
5. Persist that exact signed transition/publication candidate outside the floor
   lock and execute section 9's same-lock pending/source/accepted publication
   protocol with the new grant. Keep the floor inode, minimum versions/revisions,
   source watermark and local Clock watermark; no rotation resets them. The same
   stopped publisher path serves root replacement with the new independently
   installed root and its granted installed delegate. Its input is this fixed
   installation transition, not a caller-selected payload or an ordinary/control
   work-signing capability. It cannot bypass a pending candidate or the backlog.
6. Resume users only after the new configuration can acquire the newly accepted
   source, including a fresh single Clock sample and durable observedNow update.
   All new extensions use the new current grant and reference the unchanged old
   reservation/phase chain. Retired grants authenticate historical signatures
   only. No administrator signs each work extension; the delegate cannot appoint
   its replacement, extend its lifetime or add permissions.

The installer keeps users stopped across partial configuration/source updates;
this protocol does not claim a multi-file atomic install. A crash before the
new pending floor is durable resumes the same independently installed cutover
and exact retained candidate against the old accepted predecessor. It must not
start users on the old source with new pins or automatically reinstall old pins.
After pending is durable, only that pinned candidate under the replacement grant
may complete; old/partial/new source bytes all remain unavailable until the final
floor fsync. Expiry/revocation or missing candidate requires stopped independent
recovery, with preserved floors/history. A crash after accepted fsync but before
response reuses the accepted publication, then performs normal acquisition;
there is no second transition, revision reuse or inferred D acknowledgement.
The same boundaries apply to first use, with its explicit zero predecessor.

Routine policy/head/source refresh while installed root and delegate pins remain
unchanged uses section 9 directly without configuration edits or process restart.
The policy archive supports indefinitely many legitimate rotations up to the
existing safe counter range; no total-history or retained-key limit is added.

- Ordinary retirement/expiry of a provenance delegate prevents new signatures.
  Authentic earlier records may still explain production origin under retained
  policy history. This is not present sign/release/seal authority, nor an exception
  to F1's current active-key rules.
- Compromise revocation is stronger: distrust all records from that material,
  regardless of self-declared recordedAt. Check the current independently
  installed material restriction even when the archived policy and old signature
  are otherwise authentic. The next locked protected-phase acquisition observes
  the restriction; stronger immediate cessation requires installation suspension
  and cannot undo an already permitted phase. Its associated replacement/recovery
  root-policy revision still has the one ordered, pending
  policy-change ingress obligation. Archive lookup cannot acknowledge that event.
  Recover only exact history envelope
  digests independently vouched for in a new root policy's `historyPins`.
  A pin of a terminal envelope authenticates its exact predecessor digest chain;
  it never grants new signing. Do not invent a trusted timestamp from an old row.
- F1 checkpoint/anchor keys retain current trust, revocation and time validation
  for every new sign/release/seal. Authentic control provenance cannot rescue a
  revoked/expired F1 key or replace the frozen preparation's key/fingerprint.

Historical policy proves where a work item came from. The current installed
policy plus online acquisition decides whether its next action is permitted.
Missing historical authenticity, missing current grant, policy disagreement,
expired current material or a stale freshness head leaves the work pending or
fails safely; it never relabels it as ordinary/control or generates a new identity.

## 11. Required verification

Discriminating future proof must cover a real scheduler/runtime positive;
ordinary/control invocations over the same segment/bytes; forged class rows with
recomputed hashes; valid policy plus forged reservation; alias/callback/serialized
issuer entry; hostile root/delegate substitution; old-head-only historical proof;
rotation followed by restart; every stage gap in section 8; native concurrent
ordinary/control reservations; conflicting same-slot signed records; complete
artifact without candidate provenance; Required failure with zero release and
no control fallback; root/delegate/F1 key rotation and compromise; policy file
publication crashes/torn floor; one actual depth-one policy event; and actual
idempotent D ingress before acknowledgement. A mock signer or manually constructed
typed outcome cannot substitute for these ownership/recovery proofs.

The control-acquisition cases are first activation from only the independently
installed zero sentinel; refusal to bootstrap during a normal read; R1 source
with pending R2; publication/read lock contention and concurrency; same-revision
different-document substitution; uncommitted higher or older source; one owned
Clock sample; cross-consumer/restart Clock rollback; and failure before durable
observedNow update returning no authority. Exercise each first and later
publication crash boundary, including unavailable old/partial/new source bytes
while pending and idempotent recovery after the final fsync. Add a compromise
restriction after historical verification and require the next locked phase to
deny it; do not test for a fictional atomic trust/database commit guarantee.

The delegate-installation cases are a new root-signed grant with the old
configured delegate failing closed; the complete stopped ref/fingerprint/raw32
seed transition; the replacement grant authorizing the first new head; restart
before pending, after pending and after accepted fsync; preserved pending work,
floors and D-control backlog; and old signed history remaining authentic while
new extensions use only the replacement grant. Test ref rebinding, seed/pin
mismatch and attempted old-key fallback as failures. Shared-root replacement
must preserve both streams' floors/clock state under the online stopped-rotation
contract; same-pin routine source refresh must continue without restart.

The archive proof must retain more than 32 legitimate policy revisions and
historical root keys, then authenticate/recover old work without loading an
aggregate history. Cover unreachable but correctly signed policy objects,
wrong-digest names, missing/truncated/substituted ancestors, version gaps,
foreign installation/root records, a current document attempting historical-root
fallback, publication response loss and file/directory durability failures.
Add a current compromise restriction after an old policy was verified and prove
that cached historical success grants no new action. Keep the separate test of
32 pending transitions/backpressure; draining ingress permits further rotations
without deleting any archive object or requiring another storage protocol.

The trusted-local-file and PostgreSQL history-preservation threat boundary is
explicit: this contract does not prove resistance to coordinated restoration of
all trusted files,history,configuration and Clock. Independently retained evidence
and stopped installation recovery are required. No stronger guarantee is implied.
