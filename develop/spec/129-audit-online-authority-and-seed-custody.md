# Specification 129: Audit Online Authority and Seed Custody

Status: Authority direction adopted — exact Product implementation remains unissued

This is the most-specific online F authority contract adopted by [D179](../decisions/179-audit-signing-authority-and-production-provenance.md).
The [F5 Task](../orchestration/tasks/P23-003F5-audit-signing-authority-production-and-seal.md) owns exact implementation
ownership and qualification. BlackOps1.3 remains unreleased. F1 mathematics and
G's complete manifest-bound export context remain unchanged.

## 1. Online authority and custody

Adopt a **separate internal, purpose-separated signed online acquisition document**.
The installation independently configures its verification public key and exact
local source path. A configured root signs the entire bounded acquisition payload,
including installation/partition scope, new-work key selection, trust, freshness,
epoch and validity window. Framework acquisition rereads that file for each phase.
It never authenticates only a trust-snapshot hash.

Use **installation-owned regular files containing raw 32-byte Ed25519 seeds**,
selected by an exact configured `(keyRef, role, publicKeyFingerprint)` mapping.
The only reader is a closed Internal signer gateway. It reads a seed immediately
before calling F1, clears its owned buffer afterwards, and never returns seed
material. Do not introduce an Application-facing seed-provider interface, adapt a
StorageKey provider, generate seeds, or add a new package.

This is adequate for F1's existing exportable raw-seed contract. It deliberately
does not offer HSM/non-exportable signing: that would require a different F1 input
contract as well as a provider. A new provider would add public lifecycle,
exception, selection and capability surfaces without removing the need for an
independently authenticated public-key/trust source. The configured concrete
file route is the smaller supported installation contract.

The installed verification public key authenticates successive complete
documents without changing a whole-document pin for each receipt/head.
Ordinary document refresh needs no process restart. Root/configuration/seed-map
changes use a controlled drain and restart; none refresh invisibly.

Export `AuditVerificationContext v1`, `expectedContextHash`, `expectedManifest`,
purpose `audit_export_verification`, and G's stage/result contract remain exactly
as specified. The online document is not accepted as an export context, export
result, ordinary sign authorization, or control-invocation credential.

## 2. Installation ownership and public configuration

The installer controls an application configuration snapshot independent of
request data, Audit DB contents, an export bundle, and the acquisition file.
It provisions the verification public key through its existing trusted deployment
configuration channel. Reading the key from the document being verified, or
deriving it from that document's fingerprint, is forbidden.

`audit.signing` is optional. Absence means this integration is unavailable;
it does not change the existing `audit.outbox` defaults or activate ordinary Audit
composition. When present it is a closed object with **all** the following required fields
and only one optional member, `ordinary`, defined by [specification131](131-audit-ordinary-sign-service.md).
No other sibling or omitted required member is accepted:

| Configuration field | Exact contract |
| --- | --- |
| `installation_ref` | Generic ref: valid UTF-8, NFC, no Cc/Cf, 1..256 UTF-8 bytes. |
| `authority_ref` | Same generic-ref grammar; the one accepted online authority stream. |
| `authority_file` | Absolute canonical local regular-file path under the installation-controlled source directory; path rules below. |
| `authority_key_ref` | Generic ref for the independently installed verification key. It is not an F1 checkpoint/anchor/export key ref. |
| `authority_public_key` | Canonical unpadded base64url of exactly 32 public-key bytes. No private key, certificate URL, discovery, environment-name fallback or algorithm option. |
| `minimum_epoch` | Native integer 1..9007199254740991. Independently provisioned rollback floor for this authority stream. |
| `high_water_file` | Absolute canonical path to a precreated, dedicated existing regular file outside the Audit DB, export, application request/upload storage, and acquisition source file. |
| `seed_files` | Sorted list of 1..256 exact objects `{key_ref,role,public_key_fingerprint,path}`. `key_ref` generic and unique; `role` exactly `checkpoint` or `anchor`; fingerprint exactly 64 lowercase hex; path as below. Sort by unsigned UTF-8 key-ref bytes. Duplicate paths/inodes or fingerprints across entries reject. |

Paths are 1..4096 UTF-8 bytes, absolute POSIX paths, NFC, with no Cc/Cf, NUL,
empty path components, `.` or `..`; components are at most 255 bytes. No URI,
stream wrapper, relative/base-path expansion, glob, `~`, environment interpolation,
symlink component or final symlink is accepted. Source, high-water and every seed
path must be distinct. File descriptors must identify regular files, with one
link, and match the checked path identity. All three locations are installation
owned; application inputs cannot create/replace their directories. OS ownership,
ACLs and mounts remain installation prerequisites; a string-prefix path check is
not claimed to establish that trust.

Seed files have mode `0400` or `0440` and no writable/group-other-readable grant
beyond that exact mode. The service account obtains read access through ownership
or its configured group. The high-water file has mode `0600`; the source file may
be readable but must not be writable by an untrusted caller. The implementation
checks type/mode/identity; the installer is responsible for parent-directory and
ACL access. Deployment changes do not occur by in-place mutation of seed bytes.

The seed mapping's ref/role/material association is an installation invariant:
once a ref is provisioned, it is never rebound to different material or role,
including after restart. Rotation uses a new ref and new material. Mapping paths
may move during a stopped deployment only while retaining the exact fingerprint.
The trusted installer compares its retained provisioned mapping before accepting
such a change; this is an installation responsibility, not a claim that an
existing Framework deployment command performs that comparison. Framework checks
the installed fingerprint against each acquired key before seed use. Do not call
replacement material a recovery of the old ref. Current document
refresh cannot alter this mapping or its fingerprint pin.

The source publisher is the installation's existing external trust/head authority.
It holds the **authority-document signing key outside the application runtime**,
reads its trusted key-status source and the actual external anchor-store head,
builds the exact payload below, signs it, and publishes a fully written file by
atomic rename in the configured source directory. The root public key may be the
same installation root used for the separately designed control policy, but
purposes and authenticated streams remain separate. Online acquisition needs no
delegate or generic provider registry. This contract does not place the root
private key in `seed_files` or add a fourth F1 crypto role.

Head publication must be based on the authoritative external-store observation
and the matching signed receipt after external acceptance and receipt durability.
The candidate artifact or an Audit DB row alone cannot manufacture that head.
The installation must actually supply this publisher/source to enable sealing;
Framework has no self-signed/default-source bootstrap. Missing source is a finite
unavailable result, not a missing G manifest prerequisite. Framework supplies the
consumer and conformance contract, not a new daemon or a remote source framework.

This **is a public configuration addition**, despite introducing no `PublicApi`
class. It therefore needs configuration/installation documentation and release
impact review. No Stable capability is promoted by this contract.

### 2.1. Supported native file and close contract

The online/control custody implementation supports genuine Linux/glibc semantics
with PHP core plain-file streams and stable installed runtime/library bindings.
Each valid descriptor is exclusively owned by its fixed custody owner. No external
closer, signal handler, thread/callback, FFI, replacement libc or arbitrary stream
wrapper may share, close or repurpose its native fd/FILE handle. Under this
supported native contract, a reported close error consumes a valid descriptor;
EBADF denotes an already invalid descriptor. Non-consuming replacements and other
runtime/platform disposal semantics are unsupported by this custody contract.
This limitation does not narrow unrelated Framework capabilities.

Every durable result requires the specified checked same-descriptor full write,
exact truncate, PHP fflush and actual fsync after the final mutation. Archive
publication also requires its explicit checked directory fsync. A successful
bounded full-write loop may complete partial writes; zero progress or an
incomplete write is failure. The existing identical-already-durable-state
exception remains, and read-only sources/seeds gain no invented write or sync.
Check each observable failure return, operation-local warning and Throwable;
reconstruct the owned finite failure. Storage/filesystem fsync semantics and
installation trust remain prerequisites, not physical power-loss guarantees.

Explicitly unlock when held, then consume the owned PHP resource once. Check the
PHP fclose return, operation-local warning/Throwable and resource invalidation.
An observable failure denies the protected result. Never retry a close or use a
destructor/close as a durability barrier. PHP resource invalidation is observable;
native descriptor disposal separately relies on the supported exclusive-ownership
premise above. PHP may return true while discarding an underlying close/fclose
error. A normal PHP return does not attest the hidden native close status, and
this contract does not promise detection of that hidden error.

Anonymous history scratch is not durable authority storage. Its bounded writes,
framing, seeks, physical EOF, explicit PHP flush and closure remain checked.
A plain-descriptor fflush may be a no-op; it is no durability assertion and adds
no scratch fsync requirement. No Product native extension, helper process, FFI,
/proc inspection or fault callback is introduced to expose hidden close status.

The reviewed disposal assumptions are documented by [Linux close(2)](https://man7.org/linux/man-pages/man2/close.2.html)
and [glibc2.41 Closing Streams](https://sourceware.org/glibc/manual/2.41/html_node/Closing-Streams.html).
The qualified PHP8.5.7 behavior and exact runtime image are retained in the F5
Report. The actual CI image must calibrate its admitted routes; matching PHP
version text alone is not runtime qualification. Installation and public Guide
must disclose this support limit before the signing/control capability is released.

## 3. Shared authority envelope and exact online payload

Online and control use the same envelope,domains,bounds and root-fingerprint
binding. [Specification130](130-audit-control-authority-and-production-provenance.md) owns control semantics; common
verification neither selects a key nor mints invocation authority.

The canonical envelope has exactly:

```text
{
  schemaVersion: 1,
  purpose: "online-acquisition",
  issuerKeyRef: <configured authority_key_ref>,
  payload: <exact online payload below>,
  signature: <canonical unpadded base64url of 64 bytes>
}
```

The shared verifier's finite purpose vocabulary is `online-acquisition`,
`control-policy`, `control-lineage`. The online consumer accepts only the first.
It obtains the exact already trusted key from configuration. The common verifier
does no key discovery, provider lookup or role promotion. Control's authenticated
delegate resolution, when used, is performed by its own policy owner before
calling the common verifier.

Use existing `AuditLengthPrefix::encode` (`pack('N', strlen(bytes)) . bytes`):

```text
LP(bytes) = four-byte unsigned big-endian byte length || bytes
J(value)  = existing RFC8785 JCS bytes, without LF
H(bytes)  = lowercase-hex SHA-256(bytes)

signaturePreimage =
  LP("blackops.audit.authority-document.signature.v1") ||
  LP(purpose) || LP(issuerKeyRef) || LP(J(payload))

documentHash = H(
  LP("blackops.audit.authority-document.hash.v1") ||
  LP(J(completeEnvelope))
)
```

Sign/verify the preimage with pure Ed25519. Version 1 is fixed by both the exact
schema check and the v1 domain. There is no algorithm field, detached issuer key,
stored self-hash, optional metadata, alternate domain or signature fallback.
The signature covers **every payload member** and the purpose/issuer association.
The computed complete-envelope hash binds the durable high-water value; it is
not trusted as the root of authenticity.

These new authority domains intentionally use the already existing U32BE LP.
They are not F1's S/B encoding and do not modify any checkpoint, receipt, trust
snapshot or export-context formula.

The online payload has exactly seven properties:

| Property | Exact shape and meaning |
| --- | --- |
| `authorityRef` | Must equal configured `authority_ref`. |
| `epoch` | Positive safe native integer, monotonically increasing for every changed payload/signature document in this stream. Gaps are permitted; reuse for different complete bytes is forbidden. |
| `observedAt` | Exact valid UTC `YYYY-MM-DDTHH:mm:ss.ffffffZ`. Publisher's observation time; not a replacement for the application Clock. |
| `validUntil` | Same timestamp grammar; `observedAt < validUntil`, at most 300 seconds later using checked timestamp arithmetic. No configurable extension, skew tolerance or stale grace period. |
| `scope` | Exactly `{installationRef,partitions}`. installationRef equals configuration; partitions is a strictly unsigned-byte-sorted list of 1..256 exact `{partitionId,checkpointKeyRef,anchorKeyRef}` objects with unique partitions. Partition grammar is F1's `system:global` or `tenant:` + 64 lowercase hex. Each ref uses F1 generic-ref grammar and names an entry of its exact declared role in this document's trust snapshot. |
| `trustStoreSnapshot` | Existing exact five-field `{ref,hash,observedAt,validUntil,entries}` shape and F1 semantic hash. Entries: 1..256 exact eight-field F1 objects, strictly sorted refs, snapshot-wide unique ref/material, unchanged status and time semantics. No seed/private material. |
| `freshnessSnapshot` | Null or existing exact five-field `{ref,hash,observedAt,validUntil,heads}` with the existing freshness semantic hash. Non-null heads: 1..256 exact `{partitionId,anchorStoreRef,anchorObjectRef,anchorObjectVersion,anchorReceiptHash}` objects, unique and strictly sorted by unsigned partition bytes. Every head's partition occurs in scope. Refs are generic, version positive safe integer, hash 64 lowercase hex. |

All closed objects reject missing/extra keys, duplicate JSON names and wrong
types. No coercion/normalization/trim. The root signature binds selections as well
as the entire nested snapshots. A new-work selector cannot be replaced by a
caller-selected key; existing-work recovery still uses its immutable ref rather
than this selector. Structurally valid inactive/noncurrent unused trust entries
remain legal, as in F1; the selected key is checked for the current operation.

For every successful acquisition at Clock sample `now`:

```text
trust.observedAt = payload.observedAt <= now < payload.validUntil = trust.validUntil
```

This equality is intentional: F2 accepts a trusted snapshot and performs its own
Clock read, but does not accept the new envelope. Matching both window endpoints
propagates the authenticated online lease to F2's existing window check without
changing its API, Clock ownership or snapshot hash algorithm. The publisher
re-observes/reissues the trust snapshot with each online lease; it does not simply
wrap a longer-lived snapshot and let F2 ignore the shorter acquisition lease.

If freshness is non-null, also require:

```text
freshness.observedAt <= payload.observedAt
payload.validUntil <= freshness.validUntil
```

Thus every included non-null snapshot is current throughout the document's lease.
Null freshness or missing target head is allowed for early signing/persistence
that makes no current/suffix assertion. It is forbidden for final seal. Requiring
all scoped partitions to have already produced receipts would create a bootstrap
cycle; this online head set may cover a subset of scope. G's manifest-wide exact
coverage rule is unchanged and is not reused here.

Complete canonical envelope size is 1..1048576 bytes, with no BOM, CR/LF or
whitespace suffix. Before the reused recursive JCS decoder, apply a quote/escape
aware raw preflight: nesting depth at most 8, object members at most 32, list
items at most 256, and at most 8192 JSON value tokens. Root depth is 1; object
member names do not count as value tokens. Then validate exact bounded shapes,
canonical encodings and equality to JCS re-encoding. No recursive unbounded
metadata and no decode-before-cap route. Existing trust/freshness hash formulas
use their established S/B domain encoding, not this new envelope LP.

## 4. Acquisition, currentness, rollback and restart

Each phase opens the configured regular source file once, reads at most
1048577 bytes through that same descriptor, and rejects overflow/short/changed
identity/nonregular input. Atomic publisher replacement may yield the old or new
complete descriptor; both must independently pass signature, epoch and time
checks. No last-good fallback, directory scan, URL fetch or unbounded retry.
File replacement racing the pre-open identity checks may fail this attempt;
the next ordinary attempt may reacquire.

The acquisition owns a durable high-water file. It is an installation trust input
and monotonic acceptance record, not an Audit DB table. Its canonical closed
state is at most 2048 bytes:

```text
{
  schemaVersion: 1,
  purpose: "online-acquisition",
  installationRef: <configured installation_ref>,
  authorityRef: <configured authority_ref>,
  authorityRootFingerprint: H(decoded configured authority_public_key),
  epoch: <0..9007199254740991>,
  documentHash: <null or 64 lowercase hex>,
  sourceObservedAt: <null or canonical timestamp>,
  observedNow: <null or canonical timestamp>
}
```

The sole provisioned initial state has epoch 0 and all three last fields null. Any
positive epoch requires all three non-null. Framework never creates, initializes,
replaces, truncates to reset, or silently repairs a missing/corrupt state. A root
fingerprint mismatch fails before use even if issuer ref spelling is unchanged.

Finite acquisition order:

1. Validate installed configuration/path metadata. Read bounded document bytes;
   raw preflight, exact envelope/canonical/base64 parsing and expected purpose/
   issuer comparison precede native signature verification. Bounded online member
   shape checks may precede signature verification, but no member is used as trust.
2. Verify against the independently installed key, then validate all online scope,
   semantic hashes, relations and configured stream identity. Do not open seed
   files, F2, protected records or external anchor connections during this stage.
3. Open the existing high-water file, acquire an exclusive nonblocking `flock`
   (contention returns unavailable), and read/validate its bounded canonical state
   while locked. No other code may replace its inode while runtimes are active.
4. Sample the installation-trusted Clock exactly once **inside this lock**.
   Normalize a valid `DateTimeImmutable` to six-digit UTC; reject exceptions,
   invalid dates and `now < stored observedNow`. Check document/snapshot windows,
   the requested partition's exact scope membership, and all current whole-trust
   invariants. The document's timestamp is never used as `now`.
5. Require document epoch >= configured minimum and >= stored epoch. Equal epoch
   requires `hash_equals(stored documentHash, computed documentHash)`; otherwise
   it is equivocation. When epoch increases, require
   `payload.observedAt >= stored sourceObservedAt`. Publisher observations and
   consumer Clock samples are distinct; do not compare the new publisher
   observation to the last local Clock sample. Same-document reacquisition only
   advances observedNow.
6. Store the accepted epoch/hash/sourceObservedAt and new local observedNow before returning any
   phase input. Write to the same existing inode under the same lock: seek to 0,
   bounded full-write loop, truncate to exact new length, `fflush`, then `fsync`;
   require every observable operation result to succeed under section2.1. Only
   then check unlock/close under that supported contract and expose the internal result. If bytes are unchanged, no rewrite is needed. No rename-based
   floor update, directory-durability assumption or acknowledgement before fsync.

The in-place state choice favors simple fail-closed crash behavior over automatic
repair. Interrupted/torn state may make acquisition unavailable after restart;
it never causes automatic epoch 0. A process can crash after fsync but before
return: later acquisition accepts the same document/hash and advances time.
An acquisition failing during state persistence grants no result, seed access or
signature/release/seal permission. Recovery is an installer action with all users
stopped and an independently established floor at least as strong as the last
acknowledged state; copying an older application/Audit DB backup is insufficient.

Ordinary restart preserves this file and the exact configured root/minimum epoch.
Installed clock trust means the application's host/system time source is an
installation responsibility; injecting `ClockInterface` is not authentication.
Use the existing operations Clock supplied by trusted composition and validate
rollback as above. No NTP service, network call, DB clock or fresh-time callback
is introduced. State and Clock trust must be re-established after a VM/all-state
rollback; no cryptographic protocol can detect restoring both its floor and its
trusted clock without an external monotonic reference.

This proves authenticity and **bounded staleness**, not globally latest knowledge:
an unobserved newer document can be withheld while an older signed document is
inside its maximum 300-second lease and not below either floor. Once this stream
has accepted a newer epoch it never falls back. Multiple processes sharing this
installation must share the same trusted floor file. Separate installations have
separate floors and are not claimed to observe a root update simultaneously.
Emergency revocation requires the publisher to stop issuing the old state and
publish the new epoch; processes recheck at every protected phase. Where waiting
for an unexpired old document is unacceptable, the installer must suspend work or
advance the independent minimum epoch. There is no false instantaneous global
revocation claim.

Root-key rotation requires stopping/draining all users, installing the new public
key and fingerprint-bound floor/configuration through the independent deployment
channel, preserving a nondecreasing epoch floor and clock watermark, then
restarting. No dual-key grace, document-supplied root, or automatic key rotation.
Routine trust/freshness changes only increment the signed document epoch and
atomically replace its source file; they require neither config edits nor restart.

## 5. Concrete seed route and finite internal surfaces

The design surfaces below express responsibility and data boundaries. They are
not a Product file allowlist or an issued final PHP callable map. The Task must
freeze helper decomposition,types,constructors,exact callers and guards before
implementation. No object is registered as an Application service or deserialized
from an ordinary request; the closed F entry owns these dependencies.
Guarded construction/call graphs and actual path tests prove provenance; readonly
objects/private factories by themselves are not credentials.

| Candidate path / Internal design surface | Responsibility |
| --- | --- |
| `src/Internal/Audit/Authority/AuditAuthorityDocumentCodec.php` — `decode(string bytes): array`; `hash(string canonicalBytes): string` | Shared bounded preflight/exact envelope/canonicalization and the fixed complete-envelope hash. It does not authenticate a key or accept arbitrary metadata. |
| `src/Internal/Audit/Authority/AuditAuthorityDocumentVerifier.php` — `verify(string canonicalBytes,string expectedPurpose,string issuerKeyRef,string trustedPublicKey): array` | Shared purpose/issuer-bound native Ed25519 check against an already resolved trusted raw 32-byte public key. Returns detached payload only after successful signature verification. No sign/seed/native-keypair method. |
| `src/Internal/Audit/Signing/AuditOnlineConfiguration.php` — `fromConfiguration(array signing): self`; `installationRef(): string`, `authorityRef(): string`, `authorityPath(): string`, `authorityKeyRef(): string`, `authorityPublicKey(): string`, `minimumEpoch(): int`, `highWaterPath(): string`, `seedPath(string keyRef,string role,string fingerprint): string` | Validates/detaches installation configuration. The public-key accessor returns validated raw32 public bytes; seedPath returns only the exact configured path after role/fingerprint equality, never seed material. Paths/public-key material use SensitiveParameter and safe representation. No arbitrary service/callback/class-string configuration. |
| `src/Internal/Audit/Signing/AuditOnlineAcquisition.php` — constructor `(AuditOnlineConfiguration,ClockInterface)`; `acquire(string partitionId): AuditOnlineAuthority` | Owns bounded source read, full authentication, exact online payload validation and high-water locking/persistence. No secret or F2/DB access. Caller cannot pass a snapshot, clock value, hash, source path or key. |
| `src/Internal/Audit/Signing/AuditOnlineAuthority.php` — immutable secret-free Internal result with accessors `snapshot(): array`, `now(): string`, `epoch(): int`, `documentHash(): string`, `newCheckpointKeyRef(): string`, `newAnchorKeyRef(): string`, `assertSealHead(string checkpointBytes,string receiptBytes): void` | Sole production construction is successful acquisition. Result includes the acquired partition and whole authenticated payload privately, with detached snapshots. No public decode/hydrate/fromArray or serialization contract. A result is consumed within its phase and not persisted/reused as a later-phase credential. |
| `src/Internal/Audit/Signing/AuditOnlineSigner.php` — constructor `(AuditOnlineConfiguration,AuditOnlineAcquisition)`; `checkpoint(AuditCheckpointPreparation): string`; `anchorReceipt(array unsigned,string checkpointBytes): string` | Sole new F1 signer caller. Each method acquires current online authority itself, validates immutable input/ref/material/time, reads the one mapped seed, calls the matching F1 method, and clears its own seed buffer in finally. Returns held complete bytes only to the closed F producer. |
| `src/Internal/Audit/Signing/AuditOnlineFailure.php` / `AuditOnlineException.php` | Exact finite failure contract below; no raw source/path/secret/previous exception. |
| `src/Internal/Application/ApplicationAuditSigningRuntimeComposer.php` | Exact composition owner; uses validated application configuration, operations Clock, and the reviewed F producer. It does not register a generic signer, expose a Container seed provider or activate ordinary D incidentally. |

`AuditOnlineSigner::checkpoint` decodes/validates the unsigned input from the real
F2 preparation, reacquires authority for that exact partition, looks up the
preparation's stored ref with role checkpoint, and constant-time compares the
current key fingerprint to **both** the F2 preparation fingerprint and the
configured mapping fingerprint. It checks `signedAt` under the current key before
opening the seed. The signed document's new-key selection does not override a
committed preparation's ref. Missing exact mapping is unavailable; no fallback.

For anchor receipt signing, the real later-F AnchorIntent owner supplies its
immutable unsigned body and held complete checkpoint after the required receipt
input claim/persist. The gateway validates the body/binding, independently
reverifies the checkpoint and both roles at current acquisition time, and compares
the immutable receipt ref's snapshot fingerprint to the installed seed mapping.
It never selects a replacement ref on restart. A5's `receiptSigningKeyRef`,
`receiptSignedAt`, body/preimage hashes remain immutable; no seed or extra
fingerprint member is silently appended to A5's exact wire. The installation's
non-rebindable ref/fingerprint mapping supplies seed identity continuity; F2's
additional durable checkpoint-material pin remains independently enforced.

Rotation does not grant a historical-time exception. A current snapshot must
still validate every signature used by that phase, including F2's immediate
predecessor and the checkpoint when signing a new receipt. Disabling the old
checkpoint key can therefore stop successor preparation/persistence or later
verification even when the new signing key is active. Do not repair that failure
by selecting replacement inputs or treating past signedAt as present validity.

Read a seed using only the exact configuration map entry after all those checks.
Validate regular-file identity/mode and read at most 33 bytes from the one opened
descriptor. Exactly 32 bytes are required; do not decode hex/base64, trim an LF,
accept a sodium 64-byte secret key, inspect neighboring files or search another
ref. Do not derive or sign before required ordinary authorization/requested
delivery succeeds. F1 independently derives the public key and rejects a seed
that does not match the authenticated snapshot entry.

The gateway holds raw seed bytes in a local SensitiveParameter-covered operation,
not a property, reusable cache, callback argument, result, trace, configuration or
DB field. In `finally`, sodium_memzero clears its owned nonempty seed buffer;
F1 retains its existing cleanup of its owned keypair/secret-key buffers. State
the PHP limitation honestly: clearing owned variables is not a proof of erasing
all historical VM copies. Any failure is reconstructed without a lower cause or
raw argument retention. No logger receives document or seed/path diagnostics.

## 6. Required phase ordering and preservation of F2

| Phase | Mandatory acquisition and next effect |
| --- | --- |
| New preparation | Trusted F work producer derives partition/eligible closed segment, acquires current authority, takes that partition's authenticated newCheckpointKeyRef, then calls existing F2 `prepare`. F2 owns its unchanged one Clock/UUID read and durable preparation. Neither online acquisition nor authorization substitutes its time/UUID. |
| Preparation recovery | Recover the original ref from authenticated durable work lineage and call F2 with that ref. Existing F2 replay still reads no Clock/UUID/trust and returns the same immutable preparation. Online acquisition is a distinct later-owner action, not retroactively a replay precondition inside F2. |
| After prepare / exact-target authorization | F2 may have sampled time later than the pre-prepare acquisition. The producer computes the target from the returned immutable body, performs the separately adopted required policy evaluation and requested delivery. Denial does not undo preparation; it prevents subsequent seed/sign/complete persistence/release. |
| Immediately before checkpoint signing | `AuditOnlineSigner::checkpoint` reacquires after prepare, not reuse the old authority time. Validate frozen signedAt <= newly sampled now and exact ref/fingerprint before seed read/F1. No network/callback/unrelated work between this validation and local signing. |
| First complete persistence | Reacquire immediately before calling F2 persist with the new snapshot. F2 keeps its own current Clock sample, fingerprint/predecessor/signature checks and native transaction ownership. If its later time crosses the snapshot/key boundary it fails as already specified. F2 identical-byte replay remains storage idempotence, never present-trust proof. |
| Before checkpoint external release/call | Reacquire for exact held bytes; reverify complete checkpoint/current key/material/preparation binding. Validate accepted AnchorIntent/call marker and ordinary sign-access Required delivery or separately authenticated control origin. Hold bytes through the release gate; failure exposes zero bytes and makes no external call. Null/no matching future receipt head is permitted here: anchoring an unfinished checkpoint makes no seal/current/suffix assertion. |
| Receipt input capture / signing / persistence | New selection comes from a current authenticated newAnchorKeyRef. Durable A5 inputs and its owned times/claims are preserved. The signer reacquires again after input persistence and verifies the checkpoint plus current anchor key and immutable body before seed read. Before later receipt durability/release, reacquire and reverify the exact pair. New acquisition never rewrites persisted input/time/ref/bytes. |
| Final seal | Under the later F5 partition-fence transaction, acquire afresh immediately before the final validations/CAS. Require current authority, current trust/signatures, exact authoritative persisted checkpoint/receipt/accepted external outcome, and a non-null matching current freshness head. Same physical work target/version/token must reach CAS. Failure keeps closed. This primitive is delivered with remaining F before any F5 CAS, not deferred to G/F6. |

Release/ordinary-access transactions and F5's fence cannot be kept open across
unbounded source waits: acquisition uses local bounded files and nonblocking
floor locking, and fails closed on contention. The release coordinator must put
its final acquisition/verifications immediately before Required accessed append
inside its permitted same-connection release sequence. The outward byte return
or external call follows only normal committed return. It performs no extra
acquired-time caching or alternate release after a failed gate. A later source
revocation cannot retroactively undo a completed phase; each new phase resamples.
Validity is checked at the specified trusted phase sample, including F2's own
sample, not claimed continuously throughout a subsequent database commit or OS
scheduling delay. This preserves the existing sampled-time contracts instead of
inventing an atomic global trust-update/SQL-commit guarantee.

`assertSealHead` requires exactly one authenticated head for the acquired target
partition and equality of receipt's store/object/version/hash to that head,
plus exact receipt/checkpoint binding and:

```text
checkpoint.sealedAt <= checkpoint.signedAt <= receipt.anchoredAt <=
receipt.signedAt <= freshness.observedAt <= now < freshness.validUntil
```

It also requires the enclosing authority/trust windows and the selected keys'
`notBefore <= signedAt <= now < notAfter`, active status, and null revokedAt.
It cannot substitute the checkpoint unsigned hash for complete-byte identity,
accept a wrong store/object with the same version, or infer the head from F2.
An independently published next epoch supplies the new head after the receipt
exists; this is a normal file refresh, not an operator pin edit/restart per seal.
Current means the authenticated lease/head boundary just sampled, with the
bounded-staleness limit above; it does not mean instantaneously globally latest.

## 7. Finite failure precedence

The Internal failure vocabulary has these values and no others:

| Value | First-applicable causes |
| --- | --- |
| `configuration_invalid` | Closed configuration/type/path/key syntax violation or forbidden map alias detected before acquisition. |
| `authority_unavailable` | Missing/unreadable/nonregular/unstable bounded source, floor lock contention, missing/corrupt floor, observable file I/O/flush/fsync/unlock/close failure under section2.1. |
| `authority_invalid` | Invalid/over-limit/noncanonical envelope or payload, wrong purpose/issuer/root signature, wrong installation/authority/scope, invalid semantic hash/relations, mismatched configured root fingerprint. |
| `clock_unavailable` | Clock throw/invalid timestamp or local now below stored observedNow. |
| `authority_stale` | Expired/not-yet-current document or snapshot; epoch below installed/stored floor; equal-epoch equivocation; publisher observation rollback. |
| `trust_unavailable` | Selected exact ref absent/wrong role/non-active/revoked/not-yet-valid/expired; authenticated signedAt outside its key/time window. |
| `binding_invalid` | Artifact/preparation/partition/unsigned-body/complete-byte/fingerprint mismatch or invalid artifact signature. |
| `freshness_unavailable` | Seal requires a missing head/snapshot, wrong exact triple/version/hash, or failed receipt-to-freshness chronology. |
| `seed_unavailable` | No exact provisioned mapping; mapped seed missing/invalid mode/type/identity/length; seed does not derive authenticated public material; native signing failure. |

The exception has only a failure enum, fixed message `Audit online operation
failed.`, code 0, and null previous. No cause/message/path/array constructor
parameter. Map lower exceptions at the owning boundary and reconstruct fresh.
Configuration/read/canonical/signature/source/floor/Clock/time failures precede
seed and all Artifact signer calls; signer body/binding/trust checks precede seed
open. Seal verifies binding/signatures before exact freshness matching. Required
Audit delivery failure is owned by the sign-access coordinator and is never
turned into online success or a control fallback. Existing F2 store failures
retain their own accepted finite mapping; these values do not redefine G codes.

## 8. Required verification

Use real temporary regular files, canonical signed authority documents and native
sodium/F1 operations in the focused tests; use actual multi-process/file locking
and restart for floor behavior. A fake returned trusted object alone cannot prove
the source/seed/entry boundary.

- Real independently installed public key succeeds. Re-signing the entire payload
  under an attacker key, substituting a copied pin/source/installation/scope/ref,
  changing key selection, or swapping a valid export/control document fails before
  seed/F2/external access. A valid signature on semantically invalid nested trust
  or freshness still fails. Snapshot self-hashes alone never suffice.
- File and raw parsing tests cover oversized/deep/wide/duplicate/noncanonical
  documents, wrong native types, CR/LF, noncanonical base64, missing/fifo/device/
  symlink/wrapper source, source replacement race and bounded read behavior.
- Prove each inclusive lower/exclusive upper time boundary and one-microsecond
  neighbors, payload's 300-second maximum, publisher versus local Clock
  distinction, source-observation regression, Clock rollback/throw and selected
  key expiry/revocation. Invalid new epoch must not advance the floor.
- Native floor evidence: equal epoch/same bytes succeeds; equal epoch/different
  valid signed bytes is equivocation; higher accepted epoch followed by lower
  rejects across new process/Connection; contention fails without callbacks;
  failed full-write/fflush/fsync returns no input; torn/missing state
  never initializes itself; crash after fsync/before return recovers. Restoring
  only DB cannot lower floor. Full trusted-state rollback is an explicitly
  out-of-boundary recovery exercise, never a claimed cryptographic detection.
- In the actual supported CI image, distinguish native call hits/effects from
  PHP negative branches. Keep successful completed partial writes, observable
  failures, unreached injection routes and masked close errors separate. Retain
  the calibrated three non-consuming descriptor-residual counterexamples and
  six consumed-descriptor masked errors as distinct regression categories; none
  is detectable-negative Product PASS. Qualify actual protected-owner outcomes
  with all preceding currentness/durability barriers and explicit unlock/cleanup.
  Process death, lock release and archive recovery remain mandatory separate proof.
- Exact F2 preparation survives crash/key selection rotation. Reacquire after
  prepare when signedAt is later than the first sample. Same-ref snapshot material
  replacement fails its durable fingerprint/configured map comparison before
  seed open. F2 replay retains its zero Clock/UUID/trust path, but replay then
  revoked/expired online signing/release/seal fails independently.
- Seed cases use real raw32 files: exact role/ref/32 succeeds; 31/33/64, LF/encoded
  form, wrong fingerprint/derived public key, wrong mode/link/path, unavailable
  exact ref, seed alias, or an attempted StorageKey adaptation fails with no
  alternate-key read. Restart uses the same ref/material; new active selector
  cannot replace committed input. Secret/path/snapshot canaries are absent from
  messages, causes, debug/serialization and retained traces.
- Receipt signing with a currently invalid checkpoint key fails even if the
  anchor key/seed is valid. Receipt binding/cross-partition/complete-checkpoint
  digest substitution fails before seed. Persisted receipt inputs/ref/time/bytes
  remain identical across restart and current root/snapshot refresh.
- Real ordinary authorization/requested -> signer -> persist -> reacquire ->
  Required accessed/commit -> byte release ordering; deny, null/no policy,
  wrong actor/target and ambiguous delivery failure never reach seed or external
  release as appropriate. Retain the earlier F2 preparation on post-prepare deny.
  An online result or signed control-policy file does not grant the exemption.
- Seal succeeds only after a new signed source epoch publishes the independently
  observed exact receipt head. Null/absent/wrong/stale head or store/object/
  version/hash/chronology mismatch leaves closed. Prove ordinary file refresh
  supplies the head without config changes/restart. Later revocation invalidates
  verification without rewriting sealed history. Root rotation requires its
  explicit stopped configuration/floor transition.
- Exact caller/native-op/package guards reject sibling/alias/class-string/dynamic
  access, a seed getter/provider registration, IO in F1/F2, and a snapshot-only
  authenticator. Existing F1/F2/package negatives remain. The actual adopted
  final source determines tests, strict gates, package controls and same-SHA work;
  this specification claims no passing tests or qualified endpoint.
