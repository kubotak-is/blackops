# Pure checkpoint replay and owned descriptor surfaces — candidate delta r2

Status: **bounded author correction for independent review; not adoption, Product issuance, implementation, native qualification or F5 Acceptance.**

Owner: `/root/f4_shared_source_inventory`. C=`.phpunit.cache/f2-orchestration/accepted-integration`; E5=`.phpunit.cache/f5-orchestration/20261012`; E13=`.phpunit.cache/f5-orchestration/20261013`. Root reports C base046fa8a1d122d4d031622b6bc880f8fd40f5a990. Only this file and its inputs JSON are written; no independent Git check was run.

## 1. Fixed inputs and scope of this correction

This r2 carries forward the complete source-backed pure-codec/preload contract and proof obligations of E13 `pure-checkpoint-codec-floor-delta-r1.md` SHA256 `f816225a9bbd211feeed5533e5dbc0519be512d177b31dcdb3170c8ea7869bcc`. It closes that proposal's three named caller/dependency/descriptor-budget joins rather than weakening them. It also names the exact additional installation-composer preload caller supplied by the parallel installation investigator.

The original history map SHA256 `4527806ba7734c5480dda53a202d1351a8e8e4753a2f1d484658308d2450b2df` and composition map `e39f62937df1afb4140072b843ec10d3473de889e660f23c764da9b975188647` remain immutable. The complete calibrated native contract is E13 `native-calibration-contract-proposal-r2.md` SHA256 `847ca0bdfd192a72a631211d5ef3adff24684c2b2117981b2daba7fb1d61f118`, with bounded Green review `native-calibration-contract-review-r2.md` SHA256 `79530b88` prefix (full hash in inputs). Its substantive gates and supported-platform limitation apply to **every** resource owner below. This delta is not another native calibration or an alternative interpretation of its masked-close evidence.

Names prefixed H below are under `src/Internal/Audit/Authority/History/`; K is `src/Internal/Audit/Authority/Custody/`; A is `src/Internal/Audit/Authority/`. All listed new types are candidate Product paths, not existing implementations. Native methods below never grant invocation/signing authority.

## 2. Two exact replay joins are closed

### 2.1 Preflight caller and three F1 sites

Add precisely `H/AuditCheckpointArtifactReplay` to the fixed callers of `A/AuditAuthorityJsonPreflight::assertDocument(string):void`. Its single private byte-admission method, `admit(string):void`, checks the 1,048,575-byte category limit and invokes assertDocument at **one direct source site**. Both public entries call admit before any JCS decoding or F1 invocation. All other existing preflight callers stay unchanged; no History-directory permission is added.

Freeze ArtifactReplay's method inventory as:

| Method | Responsibility |
| --- | --- |
| `__construct(AuditAuthorityJsonPreflight)` | Closed graph supplies the actual finite parser; no callbacks or configurable limits. |
| `unsigned(string originalUnsignedBytes):AuditCheckpointUnsignedFacts` | admit, owned JCS decode/toNative, object-shaped detached value, input, exact original canonical-byte comparison, fixed unsigned facts. |
| `complete(string originalCompleteBytes):AuditCheckpointCompleteFacts` | admit, the one Codec.decode site, exact unsigned projection through input, the one Codec.bytesHash site, fixed complete facts. |
| private `admit(string):void` | Exact category length/no suffix/BOM/CR/LF and single Authority preflight site. |
| private `input(array unsigned):array{bytes:string,hash:string,preimage:string}` | Sole direct Codec.signingInput site; private only, no caller-supplied object reaches it. |

This is **five declared methods**, with no additional hidden helpers/magic getters. The service stores only its closed parser collaborator, not artifact bytes, paths, resource handles or phase permission. The two fact types retain the original private sensitive representation and four runtime controls. Any need for another method during implementation must be reported against the frozen inventory, not silently inferred from the former “at most six helpers”.

The only new outside F1 static inventory remains ArtifactReplay -> `AuditCheckpointCodec::{signingInput,decode,bytesHash}` exactly1 each. No complete/anchor/helper/Signer/Verifier/Trust call is added. bytesHash's unchanged internal decode is not an extra outside site. Neither this replay nor its parser obtains current authority or a Clock.

### 2.2 Fifth collaborator

Replace the original four-argument constructor of `H/AuditCheckpointHistoryReplay` with exactly:

~~~text
__construct(
  AuditHistoryReader,
  AuditWorkHistoryReplay,
  AuditSegmentHistoryReplay,
  AuditHistoricalAuthority,
  AuditCheckpointArtifactReplay
)
replay(AuditCapturedCheckpointHistory, AuditAuthenticatedControlHead):
  AuditVerifiedCheckpointEndpoints
~~~

The fifth collaborator is the same closed composition-owned ArtifactReplay used by WorkHistoryReplay, not a service lookup, static cache, new parser or configurable substitute. It serves only the already specified F2 byte-association branch. WorkHistoryReplay retains its existing constructor(HistoricalAuthority,ArtifactReplay). Only those two named semantic owners call ArtifactReplay's entries. Constructor arity is exactly5, replay arity2, total methods remain capped at10; this adds a constructor argument/property, not a new method. The actual graph pins both object identities and construction/call sites.

## 3. Purpose-level native ownership closes the descriptor budget

R4 means **all four actual runtime methods**, not source-only substitutes:

~~~text
__debugInfo():array                  // exactly [], no held value
__serialize():array                 // throws fixed sanitized failure
__unserialize(array data):void       // throws fixed sanitized failure
private __clone():void              // no allowed clone call; guarded and non-public
~~~

Every descriptor-owning class below is final, holds its stream only in private sensitive storage, implements R4 and explicit close plus destructor. Destructors attempt the same owned close only if no close attempt has begun, catch failures, and never establish success. No base class hides extra declared methods; no resource/path getter, callback, native operation enum, nullable write mode, boolean durability switch, close retry, magic accessor or new native dependency exists. Exactly the following additional purpose splits are proposed: **AuditNativeFloorFile, AuditPolicyArchiveFile and AuditCustodyDirectory**. They close operations the combined mutable-file method cannot supply without violating its purpose.

Constructors take only the verified plain stream and the fixed detached expected identity needed by that owner; both arguments are SensitiveParameter. Identity is the closed native device/inode/UID/mode tuple, never arbitrary metadata, a caller-selected link policy or an authority result. The class fixes its regular/directory kind; floor/anonymous/archive owners fix their respective1/0/positive-link predicates. NativeFile's source/seed/witness or new archive-temporary caller retains its already specified category/link-lifecycle check; no generic link-policy argument is added. In particular an invocation-owned archive temporary may reach nlink0 on collision cleanup, and that must not prevent its one consuming cleanup close. The actual trusted opener validates the configured path/parents and transfers exclusive stream ownership once. After transfer it retains no close-capable alias. A constructor that fails after transfer disposes its own resource once; the opener does not close it again. Native reads/checks return only bounded bytes or detached stat scalars, never the handle.

### 3.1 Mutable regular files and bounded read-only sources

`K/AuditNativeFile` has exactly10 methods:

~~~text
__construct(verifiedPlainStream, expectedIdentity)
readBounded(int cap):string
writeDurably(string exactBytes):void
close():void
stat():array                         // closed detached native stat identity only
__destruct()
R4
~~~

`readBounded` seeks this same descriptor to0 and reads at most cap+1 using <=16KiB native reads, rejecting overflow, failed/no-progress read, warning, changed identity or invalid position. cap is a fixed caller-owned material limit, never runtime configuration. The common maximum is1MiB; seed callers use cap32 so the native overflow read is33. Empty bytes are data only for the few already allowed initial states; the semantic caller still validates shape/minimum length. No stat-size shortcut substitutes for the cap+1 read.

`writeDurably` is **full same-inode durable replacement** of mutable contents: validate identity and bounded exact bytes; seek0; loop checked positive writes in <=16KiB chunks; truncate to exact length; check explicit PHP fflush; check fsync on the same descriptor; verify resulting position and identity. Return only after all checks. No rename, recreate, zero-floor recovery or inode replacement. The method exposes none of write/seek/truncate/flush/sync separately. Each final mutation receives its own durability barrier. A same-state already-durable floor exemption is decided by the semantic floor owner before calling it, never by comparing arbitrary NativeFile bytes and skipping fsync.

Only the existing source/witness/seed custodians read through NativeFile. Only the concrete control publication source owner writes the mutable policy source through writeDurably. Runtime source/seed readers have no write call edge and use their required read-only descriptors. This is not permission for an online-source publisher: online source atomic rename remains the separately installed external publisher contract.

### 3.2 Locked online/control floors

`K/AuditNativeFloorFile` has exactly the same10 method names/signatures as NativeFile, with one distinct purpose: its constructor validates the existing process-owned0600/nlink1 floor and obtains `flock(LOCK_EX | LOCK_NB)` on that descriptor; its close explicitly checks `flock(LOCK_UN)` before its one PHP close attempt. Lock state is private ownership state established by the constructor, not a caller mode flag. Lock failure closes the newly adopted resource and grants no session. No opener reconstructs a missing/corrupt floor.

The same type serves the two already separate floor protocols with each exact configured floor's identity; the online and control semantic owners remain separate, and their locks are never nested. No seed, SQL, provider or external delivery is added under either lock. `readBounded`/`writeDurably` use online cap2048 or control cap4096 enforced by those fixed semantic callers, and preserve their exact state/nullability/rollback rules.

This extra type is necessary: leaving lock ownership in an outer object while hiding the descriptor in NativeFile would otherwise require a raw-handle getter or an unissued unlock method. Making every NativeFile constructor lock would change unrelated source/seed/archive semantics. The separate floor purpose avoids both changes.

### 3.3 Immutable signed policy files

`K/AuditPolicyArchiveFile` has exactly10 methods:

~~~text
__construct(verifiedReadOnlyPlainStream, expectedIdentity)
readBounded(int cap):string
confirmDurability():void
close():void
stat():array
__destruct()
R4
~~~

There is **no write/truncate/seek-public method**. readBounded owns its internal seek/read checks. confirmDurability checks identity, PHP fflush and fsync on the actual verified descriptor, then identity again. It never writes or rewrites identical bytes. This supplies E5 custody r2's exact existing-digest replay requirement: a valid signature or existing filename does not prove the preceding publisher's directory sync completed.

Runtime historical reads use only readBounded/stat/close through AuditPolicyArchiveReader and never install, overwrite or prune an object. Exact publication replay/current-head reference uses confirmDurability on the same verified immutable file **plus** containing-directory sync described below before advancing authority. Positive native link count for authenticated policies remains permitted; arbitrary extra aliases are neither enumerated nor removed. All other file categories retain their original link rules.

This type is necessary because `NativeFile::writeDurably` cannot establish readonly immutable-file durability without prohibited write/truncate, and adding a separate sync method to the selected NativeFile would make11 methods. No overloaded writeDurably(null), empty-write sentinel or configurable immutability flag is used.

### 3.4 Directory descriptors

`K/AuditCustodyDirectory` has exactly9 methods:

~~~text
__construct(verifiedPlainDirectoryStream, expectedIdentity)
syncPublication():void
stat():array
close():void
__destruct()
R4
~~~

syncPublication checks the actual directory identity, PHP flush/fsync and identity again; it is callable only at the already specified archive namespace durability points. Scratch directory setup uses stat/close only, followed by the owned path's rmdir before any evidence write. This is a directory owner, not a regular-file rewrite or a raw resource for an external fsync. Its one unused method slot does not authorize a generic native operation helper.

### 3.5 Anonymous private history

`H/AuditPrivateHistoryFile` has exactly10 methods:

~~~text
__construct(verifiedAnonymousPlainStream, expectedIdentity)
appendChunk(string bytes):void
readExact(int length):?string
seek(int offset):void
close():void
__destruct()
R4
~~~

It admits only the already created/unlinked nlink0 regular private descriptor after the private directory is closed/removed. No pathname remains. appendChunk takes1..16,384 bytes, performs a full positive-progress checked write loop, verifies exact native position/identity and **checks PHP fflush for every successful append**. Scratch gets no fsync requirement or durability claim.

The first seek is the writer's final `seek(0)` after the complete trailer append. It permanently ends writing, records the final checked length, performs checked native seek/position validation and begins read-only replay. Any later append fails even if a stale writer reference survives. Subsequent seeks are only0..sealed length, and the fixed reader additionally admits only its previously observed record offsets. No additional public seal/finishWrites method is required.

readExact accepts1..16,384 and returns exactly that many bytes, or **null only when the same descriptor reaches physical EOF before any byte of that call**. Partial nonempty data followed by EOF is an error, never a successful short return. False read, empty non-EOF progress, invalid tell, warning or changed identity fails. Normal prefix/payload reads reject null. After the terminal48-byte record, the reader requests one byte and requires null; any returned byte proves a forbidden suffix. This exact nullable EOF result is the minimum cohesive read correction needed to check physical EOF without adding an eleventh descriptor method or a zero-length operation sentinel.

There is no public position or finishWrites. The reader maintains checked logical offsets from the successful byte counts; the file owner privately checks native offsets after read/write/seek. No arbitrary larger chunk, history size/time cap, growing offset list or weakened EOF condition is introduced.

## 4. Every existing consumer is reconciled

The table distinguishes fixed semantic owners from the raw descriptor owners. A semantic session holding a descriptor-owner object never gains its resource, and cannot make another close-capable alias. Native warning interception/identity checks remain within the exact owning operation.

| Existing consumer / operation | Exact r2 resource use and ordering |
| --- | --- |
| AuditCustodyIdentity's empty UID witness | Empty tmpfile only; validate its URI/path/fstat before trusting UID, transfer to NativeFile for closed stat/close ownership, then require expected name removal. No evidence/seed bytes enter it. Initial trusted temporary-path validation and no ext-posix/getmyuid substitution stay unchanged. |
| AuditControlSourceHintReader; online authority-source reader; historical root/compromise readers | NativeFile.readBounded with existing1MiB/4KiB limits, stable identity and explicit close. They do not acquire a floor, fsync read-only source material, sample Clock or create a grant. Any atomic source replacement still uses one complete checked descriptor. |
| Actual raw online/delegate seed custody | NativeFile.readBounded(32), exact32 semantic check and unchanged ref/fingerprint checks, explicit close before seed/F1 use, finally zero owned seed buffers. These are existing Root-owned signer/custody roles; this delta changes no seed gateway API or exposes seed bytes to history. No new seed read under a control floor. |
| Online acquisition | Own NativeFloorFile for the existing high-water inode. Read2048; unchanged authenticated online checks/Clock placement; writeDurably for a changed accepted floor; close performs explicit unlock and one consuming close before an authority result. No nested control floor. |
| AuditControlLockedSession.open -> AuditControlFloorSession | Constructor-created NativeFloorFile owns the lock. The control session is a semantic owner of that object, not a second resource owner. Configuration/path/custody validation remains in the existing closed owners. |
| ControlFloorSession.readFloor / readSource | readFloor -> NativeFloorFile.readBounded(4096). readSource creates a bounded NativeFile for the exact existing source, reads the whole checked wrapper and explicitly closes it. It neither exports nor caches a source descriptor across unrelated work. |
| ControlFloorSession.replaceObservedNow | Exact changed current-floor bytes -> NativeFloorFile.writeDurably. If exactly unchanged and already durable, the existing semantic exception skips the rewrite; it never skips the later explicit unlock/close. |
| ControlFloorSession.publishExact; sole caller AuditControlPublisher | Before any new head references the exact authenticated archived policy, verify its same immutable final object through PolicyArchiveFile and establish its file durability plus CustodyDirectory.syncPublication. Then preserve the actual pending -> source -> accepted protocol: pending floor writeDurably, mutable source NativeFile.writeDurably, accepted floor writeDurably. A failure leaves the existing pending-recovery rules intact; no SQL or seed call occurs here. The source descriptor is explicitly closed, and session.finish releases the actual floor before publication returns. |
| Exact already-linked policy replay / publication recovery | Read/validate the exact digest path; PolicyArchiveFile.confirmDurability plus directory sync even when no byte rewrite is required. The selected installation CLI supplies an already archived digest, not permission to create, replace or enumerate archive objects. This confirmation is within the existing publication/custody protocol, not a new archive producer. |
| Independent policy installer's already specified archive protocol | A new temporary regular file uses the NativeFile durable-write sequence; after its checked file fsync, no-replace hard link, directory sync, removal only of this invocation's positively owned temporary name, then directory sync. Existing final objects use the separate immutable-file confirmation. This is the preserved independent-installation obligation from custody r2, not a newly issued Framework installer entry/caller. F runtime cannot install roots, remove markers or overwrite published policies. |
| AuditPrivateHistoryFiles.create | Own and validate private0700 directory through CustodyDirectory; create exact capture child x+b, chmod0600 and check identity before data; unlink and confirm nlink0; explicitly close directory and rmdir; transfer anonymous stream to PrivateHistoryFile. No syncPublication or scratch fsync. Managed failure cleans only still-owned names; constructor/transfer state prevents a duplicate close. |
| AuditHistoryWriter.append / finish | Split magic/prefix/payload/trailer into <=16KiB appendChunk calls. Each successful append checks flush. finish emits exactly trailer255/48, then seek(0), then creates the sealed result. ReadTransaction.commit remains after this successful finalization and before locked replay. No separate finishWrites call remains. |
| AuditHistoryReader.checkComplete / next / position / seekRecord / finishPass | readExact in <=16KiB pieces after tag-specific cap admission; maintain checked logical position; physical-EOF null check after trailer. position returns that internal counter. seekRecord calls file.seek and sets the counter only after success. finishPass checks its grammar/pass boundary, never closes the native file. Full initial trailer/count/hash/EOF verification still precedes any accepted result. |
| Work/segment/transition/publication replay owners | Retain only the original finite offsets/digests and reread through Reader; they never call raw descriptor APIs. Original49 tags+255,15 phases, uncapped full history, backward work authentication and actual current issuer checks stay unchanged. |
| Exact endpoint reread / phase owner | Same anonymous descriptor is lent through the original exact fenced comparison, while the external floor is already unlocked/closed. Explicit PrivateHistoryFile.close follows the final original-byte equality and precedes delegate seed/sign or the phase result. For pre-F2 phases close precedes F2; inspection closes before its fact result. Failure denies/aborts its original owner, never returns a credential. |

`AuditControlFloorSession` itself now has an exact10-method semantic surface: constructor; readFloor; readSource; replaceObservedNow; publishExact; finish; R4. **No private native helper or destructor is appended to this class.** Its underlying NativeFloorFile owns the raw resource and last-resort destructor. finish calls that owner's explicit close; the other bounded source/archive/directory owners are held in their operation-local try/finally scopes and explicitly closed before the method escapes. This keeps the source/accepted publication ordering and all runtime representation controls without putting a seventh functional method into the semantic session.

The actual known installation graph has no direct raw-file/native-floor consumer: ApplicationAuditControlInstallationComposer builds Control/AuditControlInstallationPublicationWorkflow, which uses only the existing fixed HistoryCapture and PublicationAcquisition methods. Its selector reads the exact untrusted digest through AuditPolicyArchiveReader::policy. The actual AuditControlPublisher remains the sole floor publication consumer; the publication SQL writer remains SQL-only. These caller facts were confirmed directly by the parallel installation owner, not inferred from command naming.

## 5. Exact native-operation inventory

The following are **direct source call-site counts per descriptor method**, not assertions about kernel syscall counts or the number of loop iterations. A PHP fwrite may combine native short writes, and fsync may call native fflush internally; the calibrated distinction is preserved. Metadata admission also uses the fixed is_resource/resource-kind/plain-wrapper checks; it accepts no user wrapper. Each I/O method installs its fixed local warning handler for its owned operation and restores the previous handler in finally. No native warning text/cause/resource/path becomes output.

| Method/body | Fixed native sites; normal successful ordering |
| --- | --- |
| NativeFile / PolicyArchiveFile / CustodyDirectory constructor | fstat1 after adopting the previously verified plain resource; compare the exact role identity. No flock, data read/write or sync. |
| NativeFloorFile constructor | fstat2 around flock1(LOCK_EX\|LOCK_NB); require existing correct floor identity under the obtained lock. No data write/repair. |
| PrivateHistoryFile constructor | fstat1 and ftell1; require expected nlink0/size0/position0 and exclusive ownership. |
| Any regular-file readBounded | fstat2 around fseek1, fread1 loop, feof1 site and ftell1. Reads total at most cap+1 in <=16KiB chunks; strict exact position. No write/truncate/flush/sync. |
| NativeFile / NativeFloorFile writeDurably | fstat2, fseek1, fwrite1 loop, ftruncate1, fflush1, fsync1, ftell1. Positive checked native results, exact final length/position and stable identity; no close or rename substituted for sync. |
| PolicyArchiveFile.confirmDurability / CustodyDirectory.syncPublication | fstat2 around fflush1 then fsync1. No fseek/fwrite/ftruncate/link/unlink. File and directory methods are distinct typed purposes. |
| PrivateHistoryFile.appendChunk | fstat2, fwrite1 loop, fflush1, ftell1. Successful call appends exactly its1..16KiB input; no sync/seek/truncate. |
| PrivateHistoryFile.readExact | fstat2, fread1 loop, feof1 site, ftell1. Exact requested bytes or actual initial EOF/null only; no successful partial return. |
| PrivateHistoryFile.seek | fstat2, fseek1, ftell1. First offset must0 and permanently ends writing; later offset bounded by sealed length. |
| Every stat() | fstat1, closed scalar result; no handle/path/byte getter. PrivateHistoryFile has no public stat/position method. |
| Nonfloor explicit close | fstat1 for the final role identity check, then fclose1; check PHP result/warning/Throwable and resource invalidation. A failed final check still leads to the one cleanup close attempt, but denies success. |
| NativeFloorFile.close | fstat1, flock1(LOCK_UN) if this owner actually acquired it, then fclose1. Unlock/check failure still leads to the one cleanup close attempt and denies success. No retry or extra unlock owner. |
| Every destructor | Zero new native call sites: at most one call of its own close if no explicit/constructor cleanup attempt began, wrapped as last-resort cleanup. It never grants success or repeats a close attempt. |
| R4 | Zero native/resource operations. Serialization/unserialization/clone cannot duplicate or dispose the original resource; partially constructed objects have no close attempt unless ownership was actually transferred. |

The role identity check includes the correct private descriptor state and expected native identity; it is not a claim to defeat a hostile same-UID/root/kernel/ACL/mount actor. Native floor lock/unlock call sites exist **only** in NativeFloorFile; mutable replacement sites only in the two mutable types; readonly archive/namespace fsync sites only in their named purpose methods. The actual namespace link/unlink/rmdir/chmod/create checks remain at the previously specified custodian/independent installer, outside these descriptor surfaces, with no generic operation API added. Owned scratch namespace cleanup occurs before any nonempty evidence append.

A failure after any partial native progress marks that descriptor unusable for further data operations. Only its one owned disposal attempt remains. Observable final-stat/unlock/close failure prevents the caller's protected result even when cleanup itself succeeds. Native-operation tests must pin these source sites and discriminate result/order/state; they may not accept the same counts with different data or reordered sync/unlock barriers.

## 6. The complete calibrated native contract remains mandatory

All durable final mutations retain checked exact write/truncate/fflush/fsync on the same descriptor, plus directory fsync where required. Immutable existing archive replay retains both file and directory durability before success/reference. Read-only sources/seeds and the adopted exactly-unchanged already-durable-floor exception are not forced through a write or given a fictitious fresh durability proof. Scratch retains full writes, explicit flush, seek/framing/EOF and explicit close with **no new scratch fsync**.

Every owner checks observable false/zero/-1, warning and Throwable as appropriate; close consumes the owned PHP resource once and checks its invalidation. **PHP resource invalidation is not proof of native descriptor discharge or hidden-close-error detection.** The supported boundary is genuine Linux/glibc consuming-close semantics, valid exclusively owned PHP core plain streams and stable runtime/library binding. No external fd/FILE alias, signal/thread/callback closer, replacement libc, arbitrary wrapper, blind retry, /proc test, helper process, FFI or production extension is added. The broader guarantee of observing every native fclose/close error remains explicitly rejected.

Retain all60 observations and classifications:25 controls,17 observable failures,4 numeric-write results,5 unreached fault routes, and9 masked native close errors. The9 remain3 deliberately non-consuming close-live counterexamples versus6 consumed-descriptor masked errors. The3 are unsupported replacements and resource-only-cleanup counterexamples, never successful cleanup or Product negative proof. The6 remain unobservable in PHP; earlier checked durability/currentness/permission gates are still required. No result is relabelled because this candidate's surface changed.

The new compact APIs do not waive required positive syscall-route evidence, observed-negative API tests, masked-error regressions, native CI-image calibration, actual owner outcomes, interruption/lock/archive recovery, forced child cleanup or bounded-memory qualification. Installation filesystem/credential/trusted-path/durability assumptions and the platform support limitation remain visible. The original native proof/lease is reused only for its actual unchanged measured observations; no new Product/native outcome is inferred.

## 7. Cold prerequisite and full history remain unchanged

Retain the r1 proposal's exact15 existing Framework codec types, the three already named replay/fact types and constructed Preflight, with the same closed literal resolution guards. `H/AuditCheckpointReplayRuntimePrerequisite::initialize():void` still has only private constructor and initialize, no readiness flag/reset/provider/callback. Outside either floor it resolves that finite closure and requires:

~~~text
Normalizer::normalize("e\u{0301}", Normalizer::FORM_C) === "\u{00E9}"
~~~

This warms the existing required polyfill's canonicalDecomposition, combiningClass and canonicalComposition. No empty/ASCII substitute, extension mandate, dependency addition, F1 method invocation or normalization-disabled fallback is allowed. Its same cold-process proof obligation remains; this r2 performs no runtime proof.

The exact initializer production caller set is now **two fixed source sites**:

1. `ApplicationRetentionRuntime::signingTasks()` once after installed signing admission and before either floor-capable graph is exposed.
2. `ApplicationAuditControlInstallationComposer::compose(ApplicationConfigurationSnapshot):AuditControlInstallationPublicationWorkflow` once before constructing/using either acquisition/publisher. Only InstallationRuntime.publish calls this composer after canonical policy-digest argument admission; malformed/missing argument, list/help and signing-absent maintenance do not initialize it.

The second edge is an explicit integration correction supplied by the installation author; a cold first installation publication cannot inherit a maintenance process's warm state. These are literal caller identities, not a generic application preload service. No other F1 static site or type-resolution allowance is broadened.

Full49-tag/15-phase history,64-bit checked counters, complete trailer/count/hash/physical EOF, uncapped history length, current installed issuer checks under the control floor and exact original F2/candidate association remain. There is no SQL/seed/provider/current-online/F2/signing/nested floor under the control floor. The sole Clock sample follows complete current/historical replay. The exact anonymous descriptor survives only for its already required later fenced byte reread and is explicitly closed before the original phase boundary. F1/F2/F3 fields/wires and all release/Required/zero-release gates stay unchanged.

## 8. Issuance/review consequences and verification status

Update only the exact final candidate manifests: Preflight caller/site; HistoryReplay fifth dependency; five-method ArtifactReplay; the revised two original descriptor inventories plus the three explicitly named purpose owners; ten-method ControlFloorSession; nullable exact-EOF PrivateHistoryFile result; writer/reader removed finishWrites/position edges; exact two initializer callers. Pin every public/private/magic method and constructor parameter count. Test source guards must reject an eleventh descriptor method, missing R4/destructor/explicit close, generic IO dispatcher, raw handle export, lock split across owners, immutable write-on-replay, omitted directory sync, append after first seek, successful short read, early EOF/prefix success, duplicate close, added seed/SQL/current-trust call or late preload.

Required later native cases are the actual admitted owners and phases: zero/short/error writes and flush/fsync failures through writeDurably; immutable file and directory sync failures; explicit unlock failure with cleanup; close API/resource evidence with the supported NC-01 distinction; anonymous writer failure/trailer/EOF/seek/cleanup; cold valid non-ASCII parsing; and actual process death at named scratch/floor/link/directory points. Case counts and resource leases belong in Root's complete packet; none are fabricated here. No successful qualification is invalidated merely by another author checking unchanged source evidence.

These three originally assigned joins have a concrete candidate closure. The one additional initializer caller is coordinated and named. The existing installation-policy selection and Root's online/seed/delegate import assembly remain separately owned; this resource correction invents no substitute gateway, installer archive producer, release outcome or F4/D continuation. There is no new concrete residual descriptor/API gap identified within these frozen consumers; independent review and implementation proof may still find one.

Commands here were read-only selected text/hash/JSON bookkeeping and writing only the two authorized ignored files. No Product/governance/authority/STATE edit, Git, PHP/Node, tests, Docker, DB, network, compiler or native lease occurred. Current STATE/Task were read to confirm ownership; Root's concurrent governance work is not represented as this author's work.

Release Documentation Impact: this ignored candidate changes no public source/artifact or released capability. Installation/guide text and exact support limits remain required parts of Root's later adopted native contract and full delivery.

残り工程: Root independently reviews/adopts this finite correction with the Green native contract, incorporates exact authority/installation imports and commands/resources into the complete worker packet, and obtains complete issuance review before Luna. Product/native/strict/full/export/same-SHA and full1.3 gates remain open.

Next Action: submit this r2 and its immutable selected-input manifest to the assigned independent reviewer; keep all r1/native/history/composition evidence frozen.
