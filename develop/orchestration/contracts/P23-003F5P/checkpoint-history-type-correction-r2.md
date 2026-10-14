# Checkpoint history transport and unsuccessful disposal — correction r2

Author: `/root/f4_shared_source_inventory`; 2026-10-14. **Frozen author candidate for affected independent review; not adoption, Product issuance, execution or Acceptance.** Only this file and `checkpoint-history-type-correction-r2-inputs.json` are owned by this unit. The assigned C checkout is `.phpunit.cache/f2-orchestration/accepted-integration`; Root reports installation-authority commit87f5595 and remote matching. No Git operation independently checked that report.

This amendment closes the two exact proposed-declaration joins in independent review `939e594aa673a11416109e03c541514a9c6618b4adb661f8deffd6f164c9e225`, inputs `1f4326128278657bca6d98115d561e0440a04269087a382814c89caf1df177a8`. It supersedes only the transport/failure-disposal declarations and caller rows identified below in type closure67af749f/7390836f and their directly affected Endpoint rows in CIR7cfb9ec8/0afefd95. All original files remain immutable. The independently closed CIR-01, CIR-02 and PI-04 conclusions, original current-root generation, exact scope/endpoint identities, candidate permit, one-use grant and native comparison groups are reused, not re-reviewed here.

Root has selected one fixed native-group projection on the existing Record, actual Reader decode and literal consumers, and a bounded existing-Endpoint disposal method if needed. No new Product file, helper, decoder caller family, authority phase, capture field, SQL query, signing surface or test path is introduced. Existing130 `04463943` / D179 `d0510467` match the separately named installation adoption; the stale earlier STATE checkpoint is not treated as a newer base. The companion pins the actual current input bytes without a Git claim.

## 1. HT-01: one closed Record projection and its exact construction

All namespaces equal the literal `src/` paths below under `BlackOps\\`; no prefix or table grants a directory-wide caller allowance. R4 means exactly the four actual representation declarations already frozen: empty safe `__debugInfo`, rejecting `__serialize`, rejecting `__unserialize`, and private guarded `__clone`. No extra private helper is implied by a method ceiling.

Replace only the Record row with:

~~~text
src/Internal/Audit/Authority/History/AuditHistoryRecord.php
private __construct(AuditHistoryTag tag, string payload,
                    ?AuditHistoryNativeGroup native)
static fromCapture(AuditHistoryTag tag, string payload,
                   ?AuditHistoryNativeGroup native): self
static fromRead(AuditHistoryTag tag, string payload,
                ?AuditHistoryNativeGroup native): self
tag(): AuditHistoryTag
bytes(): string
native(): ?AuditHistoryNativeGroup
R4
~~~

This is **exactly10 declarations, maximum3 arguments**: constructor1, factories2, getters3, R4. `native` is a fixed typed projection, not a decoder or arbitrary metadata accessor. Private storage retains the same original bounded payload and, only for a scalar-group tag, one immutable `AuditHistoryNativeGroup`. No new success flag is present.

The constructor enforces the existing tag-specific byte cap, empty/trailer lengths, and this exact relation: `native` is present iff tag is in the existing scalar set below, and its `tag()` is the exact same enum case. For every other tag it must be null. It performs no JSON parse, Codec call, autoload registration, signature/trust check or I/O. Group constructors continue to reject references, wrong scalar types, unknown/missing keys, bad integer/UTF-8/NFC forms and forbidden tag schemas. Record does not duplicate that parser.

The unchanged scalar set is exactly:

~~~text
1,3,5,16,17,18,19,21,23,24,25,26,28,31,33,35,
48,49,51,52,53,55,64,65,67,68,72,73,75,76,79
~~~

The byte tags retain their original payloads:4 floor<=4096;2/74/77 wrapper<=1MiB;27/29/50/54/66/69 envelope<=64KiB;30/32/34 original F1 bytes<=1048575. Empty6/20/22/70/78 remain zero bytes;255 remains exactly48 bytes. Native groups remain flat and<=16384. No record contains both a replacement serialization and the bytes originally read.

### 1.1 Factory/decoder sites, including same-local argument binding

| Callee | Complete literal caller set and argument rule |
| --- | --- |
| `AuditHistoryRecord::__construct` | Only its own `fromCapture` and `fromRead`, forwarding their exact admitted three arguments. |
| `AuditHistoryRecord::fromCapture` | `AuditCheckpointCaptureWriter::capture`, `AuditControlCaptureWriter::capture`, and `AuditHistoryWriter::finish` only. The first two serialize each native tag from the same locally held group: `payload = GroupCodec.encode(group)`, then `fromCapture(group.tag(), payload, group)`. Native tag/group/payload cannot originate from different locals or branches. For an original bytes/empty record the fixed literal tag and original payload are passed with null. Writer.finish has only the literal255/fixed48-byte trailer/null call; it cannot create a native group. |
| `AuditHistoryRecord::fromRead` | Only `AuditHistoryReader::next`. After native prefix/tag/length admission and the complete bounded original payload read, native tags execute `group = GroupCodec.decode(tag, payload)` exactly once and pass that same `tag`, unchanged `payload`, and actual returned `group`. Every other tag passes null. A scalar projection provided by a selector/caller cannot substitute for decode. |
| `AuditHistoryGroupCodec::encode` | Only `AuditCheckpointCaptureWriter::capture` and `AuditControlCaptureWriter::capture`, the actual same-group serializer sites above. No Record, Replay, comparison owner or artifact owner calls it. |
| `AuditHistoryGroupCodec::decode` | Only `AuditHistoryReader::next`, the actual original-payload site above. No outer/inner Replay, MutationAdmission, PublicationWriter, Record or ArtifactReplay gets a decoder edge. |
| `AuditHistoryNativeGroup::__construct` | Only its own existing `fromNative` and `fromDecoded`. Unchanged2 arguments. |
| `AuditHistoryNativeGroup::fromDecoded` | Only `AuditHistoryGroupCodec::decode`, using its actual fixed-tag validated scalar result. |
| `AuditHistoryNativeGroup::fromNative` | Only `AuditCheckpointNativeGroups::{partition,segment,endpoint,preparation,complete,proof}`, `AuditWorkNativeGroups::{reservation,step,presence,end}`, `AuditControlNativeGroups::{segment,transition,acknowledgement,publication,endpoint}`, and the two `CaptureWriter::capture` sites for their already specified fixed header/external/end groups. This is the prior closed native shaping vocabulary, not another parser/capture owner. |

The last table uses brace notation only to enumerate literal methods of the resolved class; the JSON expands each edge. All listed History classes are under `src/Internal/Audit/Authority/History/`. GroupCodec remains its existing<=8 methods/max2 arguments; NativeGroup remains exactly9/max2. Record's constructor deliberately does not obtain a broad encode/decode permission to compare fields. **Original-byte/group binding is instead proved by the two source-guarded same-local factory flows just specified.** The source guard must reject moving decode/encode away, substituting another valid group or payload, passing reconstructed bytes, or calling either factory from another method. A name/count-only allowlist is insufficient.

### 1.2 Complete getter consumers

The following nine literal entry methods form the semantic/comparison reader set. Private helpers of those classes may receive only their already admitted bounded scalar/byte arguments; they do not gain Record or NativeGroup getter edges implicitly.

| Literal owner path | Exact entry methods |
| --- | --- |
| `src/Internal/Audit/Authority/History/AuditWorkHistoryReplay.php` | `verify` |
| `src/Internal/Audit/Authority/History/AuditSegmentHistoryReplay.php` | `published`, `staged` |
| `src/Internal/Audit/Authority/History/AuditTransitionHistoryReplay.php` | `verify` |
| `src/Internal/Audit/Authority/History/AuditPublicationHistoryReplay.php` | `verify` |
| `src/Internal/Audit/Authority/History/AuditCheckpointHistoryReplay.php` | `replay` |
| `src/Internal/Audit/Authority/History/AuditControlHistoryReplay.php` | `replay` |
| `src/Internal/Audit/Provenance/AuditCheckpointMutationAdmission.php` | `assertUnchanged` |
| `src/Internal/Audit/Provenance/PostgreSqlAuditControlPublicationWriter.php` | `recheck` |

The exact caller matrix is:

| Getter | Literal consumers, with no implied class-wide allowance |
| --- | --- |
| `AuditHistoryRecord::tag` | `AuditHistoryWriter::append`; `AuditHistoryGrammar::accept`; `AuditHistoryReader::checkComplete`; each of the nine methods above. |
| `AuditHistoryRecord::bytes` | Same twelve methods as tag. Writer emits exactly these bytes, Reader hashes/counts original bytes, Grammar checks the fixed length relationship, and semantic/comparison entries use unchanged original byte tags. |
| `AuditHistoryRecord::native` | `AuditHistoryGrammar::accept` and each of the nine methods above. Writer and complete-pass hashing do not use it. Each caller requires present/null according to the fixed tag; it does not silently coerce absence. |
| `AuditHistoryNativeGroup::tag` | `AuditHistoryRecord::__construct`; `AuditHistoryGroupCodec::encode`; `AuditCheckpointCaptureWriter::capture`; `AuditControlCaptureWriter::capture`. Other readers use the already bound Record tag; no redundant semantic Group.tag edge is necessary. |
| `AuditHistoryNativeGroup::scalars` | `AuditHistoryGroupCodec::encode`; both `CaptureWriter::capture` sites when assembling their existing bounded expected endpoint group; `AuditHistoryGrammar::accept`; the nine methods above. |

Thus NativeGroup.tag has4 fixed consumer methods, NativeGroup.scalars13, Record.tag/bytes12 each and Record.native10. Factory callers are separate from getter callers. `AuditHistoryReader::next` uses its parsed locals to create the Record and passes it to Grammar.accept; it does not need to reread its own just-constructed getters. Reader.checkComplete uses next plus only tag/bytes for its complete count/hash/trailer pass. Reader construction/checkComplete/seek offsets/finishPass and the four local constructor sites stay as in67af749f, with exactly10 methods and no new helper.

`AuditCheckpointArtifactReplay` is deliberately **not** a direct Record or NativeGroup reader. Its existing unsigned/complete entries receive the exact original byte strings selected by WorkHistoryReplay.verify or CheckpointHistoryReplay.replay. Its three pure F1 sites, bounded raw preflight and exact original-byte facts remain unchanged. The previous vague phrase “five semantic/artifact replay owners” is replaced by the literal set above; it does not survive as an additional allowlist.

### 1.3 Actual end-to-end transport

1. The two CaptureWriters append the Record objects just constructed from exact native SQL/header groups or original bytes. HistoryWriter.append serializes tag/payload, and Grammar accepts the actual attached group. No current authority is created.
2. The actual captured anonymous file is sealed and committed before floor acquisition. The local replay Reader checks complete trailer/count/hash/physical EOF. Its next operation, including every later bounded repeated scan, creates the group from the original record payload through the sole actual GroupCodec.decode site.
3. Both outer replay entries read native header/boundary/end groups through Record.native/scalars and original bytes through Record.bytes. The four semantic owners use the same route. Their complete existing semantic/historical issuer/artifact checks still dominate the sole respective VerifiedEndpoints.fromReplay sites.
4. After the external floor finishes, MutationAdmission.assertUnchanged or PublicationWriter.recheck takes the exact existing comparison loan and constructs its local Reader. It obtains the actual original native group and byte records through precisely the same route, then compares against actual fenced SQL rows and the fixed original scope. No selector scalar, digest-only equality or regenerated envelope replaces byte equality.
5. Successful comparison performs checked capture close before the NativeObservation. Phase7's later final root uses its existing retained bounded original/native groups and creates no Reader, new decode or reopen. This amendment neither adds SQL under a floor nor turns a parsed native group into production permission.

The existing16 additional cold resolutions already include Record, NativeGroup, Reader, Grammar and GroupCodec. No additional class, initializer, callback or dependency is needed. The exact two composition preload sites, nonempty Normalizer FORM_C warmup and no-current-signing/online/seed/provider/Clock/F2/SQL-under-floor constraints remain unchanged.

## 2. HT-02: exact unsuccessful disposal within the existing owner family

### 2.1 Additional endpoint operation, no success substitute

Both literal existing types gain the same single ninth declaration:

~~~text
src/Internal/Audit/Authority/History/AuditVerifiedCheckpointEndpoints.php
src/Internal/Audit/Authority/History/AuditVerifiedControlEndpoints.php

discardAfterFailure(): void
~~~

Their complete surface is private ctor(scope,finiteFacts,capture), fromReplay(same3), comparison(), finishComparison(array exactActualBoundedRows), discardAfterFailure(), R4 — **exactly9 methods/max3 arguments**. No helper/getter/graph argument or Throwable parameter is added. Original `CheckpointNativeComparison` / `ControlNativeComparison` schemas and successful comparison's exact `{scope,finiteFacts,capture,actual}` shape are unchanged.

A private monotone failed/discard-started state is explicitly part of this declaration. It is an unsuccessful terminal state, never a successful-replay marker. `discardAfterFailure` sets it before any cleanup, clears any usable `actual`, and takes its private original capsule into a local disposal reference if still present. It then invokes that exact capsule.close once through the latch below and releases its private capture reference on every path. With capture already null after phase7's first successful comparison, it performs no file operation. Repeated discard has no lower close call; a remembered cleanup failure throws only the existing finite sanitized failure, and cannot be reported as checked-success cleanup. No raw Throwable/path/SQL/value is stored or attached as previous cause.

After failure/discard begins, `comparison` and `finishComparison` fail before returning a result or acting on data. They do not return `{capture:null,actual:...}` as a successful result. A close failure inside finishComparison likewise marks the endpoint failed and leaves no usable actual facts; outer discard may dispose only the already owned latch, never retry native close. On an ordinary successful first finishComparison, the capsule must close successfully before actual is assigned and capture cleared. The existing later phase7 successful comparison with capture=null may replace only its bounded actual result after its own real native reread; a failed final root instead discards and poisons. No capture is reopened.

These types hold no Graph/Connection. Their named outer owner retains the original existing abort/invalidate responsibility; discard is not an alternate transaction handler, new authority getter or reset.

### 2.2 Single close attempt is enforced where the resource is already owned

The following refinement names the existing close state; it adds no method to CapturedHistory, SealedHistory or PrivateHistoryFile:

- Each capsule and seal has private monotone close state: open, attempt-started, checked-closed or failed. These are private primitive state values, not a new Product enum/helper or a public status projection.
- On the first close entry, mark attempt-started **before** the lower close call or any possibly failing final check. Make data/loan operations unavailable immediately. Invoke the one owned next layer, record checked-closed only after it returns normally, and record failed on any exception.
- A later close entry on checked-closed returns without calling the next layer. A later entry on attempt-started/failed denies with the finite sanitized failure and calls no next layer. It never converts a prior failed close into success. This permits a lexical outer failure guard to discharge its obligation without creating a second physical close when an inner endpoint already attempted it.
- The actual native file's adopted final-stat/fclose code and close-attempt guard remain exactly the calibrated native r2 contract. Final-stat failure still reaches the single consuming close. Observable false/warning/Throwable/resource invalidation failures deny success; masked consumed native statuses retain their published limitation. No retry, new kernel detector, fsync duty, close-all loop or GC success premise is added.
- Destructor behavior is unchanged at the actual raw-resource holder: last resort only if no attempt began, never proof of successful cleanup. Capsule/Seal/Endpoint gain no destructor or hidden helper.

A copied PHP reference to the **same final capsule object** is only the bounded lexical fallback described below; it creates no second descriptor holder, raw stream alias, independent close state or resource getter. Every cleanup path reaches the same capsule/seal/native latches. The actual checked native close count is exactly1 per created history resource on terminal managed paths, or0 after an earlier already checked close; an induced failed close still counts as its one attempt. Calling a terminal capsule guard again does not call Sealed.close or native close again. Tests distinguish those events rather than treating the number of outer catch blocks as the native close count.

### 2.3 Capture construction and the sealed-before-commit gap

Both `AuditCheckpointCaptureWriter::capture` and `AuditControlCaptureWriter::capture` have this fixed ownership progression, expressed through local references and existing operations, without another factory/cleanup helper:

| Point | Sole lower disposal obligation and exact route |
| --- | --- |
| PrivateHistoryFiles.create has not returned | The existing custodian owns its created names/directory/native constructor cleanup. CaptureWriter has no adopted file to close. |
| PrivateHistoryFile returned; Writer is appending or finishing | The creating CaptureWriter retains the file as its **disposal owner**. HistoryWriter is its bounded writing/sealing borrower, not a second native closer. On any failed writer construction/append/finish/factory before finish returns, the CaptureWriter calls that file.close once in its failure cleanup, even when data use was poisoned. |
| HistoryWriter.finish returns SealedHistory | Successful return transfers disposal to that exact seal. Writer drops its writable file reference/marks its existing terminal state; CaptureWriter clears the former file-disposal local immediately and keeps the seal. First seek(0) has already permanently ended writing. No stale Writer reference can append. |
| ReadTransaction.commit fails after seal success | **New literal edge:** creating CaptureWriter.capture calls SealedHistory.close. No capsule exists, no floor begins, and no fallback tries the prior file owner. The existing owned transaction abort/poison path still runs and no captured result escapes. |
| RO commit succeeds but CapturedHistory.fromCapture fails | CaptureWriter still owns the seal and closes it through that same exact edge. Capsule validation happens before adoption; a failing capsule constructor does not also close a seal that its caller still owns. |
| CapturedHistory.fromCapture returns normally | That single capsule now owns the seal; CaptureWriter clears the seal-disposal local before returning the capsule. A returned capsule has exactly one native owner chain; the creating writer no longer closes it. The caller then owns the phase's lexical fallback obligation. |

`AuditSealedHistory::fromWriter` and both CapturedHistory factories validate their closed scalar/scope arguments before adopting the opaque lower owner; their ownership transfer is the successful factory return. This is a transfer of an existing final owner object, not the raw native constructor contract. The latter still consumes an adopted resource if its own constructor fails, as130§9.3 requires. No second close-capable native alias is manufactured during either transfer.

HistoryWriter.finish already owns the fixed trailer append, checked first seek and sole SealedHistory.fromWriter site. It gains no close or destructor declaration. On failed seal construction its existing borrower remains unusable for data, and the CaptureWriter's still-owned PrivateHistoryFile cleanup applies. `AuditSealedHistory` remains exactly10/max4, both capsules exactly10/max3, and HistoryWriter stays within its original<=10 inventory. The direct capsule-close permission previously listed for a creating CaptureWriter is replaced by this exact file/seal transfer route; no post-return capsule cleanup site in CaptureWriter is needed.

### 2.4 Checkpoint acquisitions and actual write-root handoffs

Each real checkpoint workflow keeps the exact capture object it obtained until the operation consuming that phase's capture has either returned successfully or thrown. It does not recover a capture from an authority getter. The matching acquisition receives that same object. Its floor and any online floor are finished before entering any SQL root.

The existing public/private split is made literal: PreparationWorkflow.prepareActual owns phase5 capture/current checks and actual F2 prepare; public prepare owns phase6 capture/recording. PersistenceWorkflow.persistActual owns phase8 capture/current checks and actual F2 persist; public persist owns phase9 capture/outcome recording. CandidateWorkflow.complete owns phase7, including the signer/admission and final recorder. No new helper, phase, argument or intermediate credential is introduced by naming those existing entries.

| Actual holder / handoff | Failure obligation |
| --- | --- |
| Registration.prepareSelected / ControlIssuer.prepareSelected after phases3/4 capture, before ReservationAcquisition returns | The local exact capsule remains the fallback. A locked ControlAcquisition failure first finishes its owned floor then closes through its existing capsule site. A later online/admission/resource-factory failure has no consumed endpoint at the workflow and is closed by the workflow's local failure guard. |
| Any returned phase authority/resources, before Recorder consumes; CandidateWorkflow before CandidateAdmission successfully takes its permit | The same workflow local remains open for failure cleanup. Failure of argument/scope checks, permit construction/take or a call before endpoint return cannot skip close because it occurs inside that lexical guard. No PhaseAuthority getter/disposal method is added to its exact10 methods. |
| Recorder's fixed method successfully receives endpoints from MutationAdmission.consume | That Recorder method now owns **endpoint disposal on every unsuccessful exit**. Endpoint local assignment is before its beginRoot call; beginRoot is inside the protected try/catch, not before it. If consume throws before returning the endpoint, the caller workflow's capsule guard applies instead. |
| PhaseAcquisition.beforePrepare / beforePersist successfully consumes endpoints | The same exact root-owner rule applies inside those existing methods, including beginRoot, actual compare/close, commit and fresh online checks before normal return. If acquisition/consume has not returned an endpoint, the corresponding prepareActual/persistActual fallback owns the local capsule. |
| CandidateAdmission.admit successfully takes its permit tuple | It retains the original endpoint local before beginRoot and owns endpoint disposal through the actual pre-seed comparison, checked close, observation construction and commit. If take fails before returning, CandidateWorkflow's same capsule guard closes it. The signer gets no result before successful commit and no new endpoint/capture getter. |
| assertUnchanged fails before/during borrowed Reader checks, SQL reread or byte equality | It throws to its already named root owner; it does not manufacture a success call to finishComparison. The root owner invokes discardAfterFailure, then completes its existing failure/abort boundary. No separate catch in a lower helper needs another disposal method. |
| finishComparison/close or later commit/frame/write fails | The endpoint is discarded by that same root owner. If close already began/succeeded, terminal latches yield no second lower close; no successful observation/result can escape after the failure. Original sanitized failure wins over a cleanup failure. |
| Candidate final recorder root after pre-seed close | capture is already null; discard clears usable facts and poisons, with0 file/Reader/open/close calls. The final bounded full-byte native reread and one main authority consumption remain mandatory. |

The complete literal callers of `AuditVerifiedCheckpointEndpoints::discardAfterFailure` are **eight** existing root-owning methods:

- `src/Internal/Audit/Provenance/AuditWorkProvenanceRecorder.php`: `reserveOrdinary`, `reserveControl`, `preparationBound`, `completeCandidate`, `artifactPersisted`.
- `src/Internal/Audit/Authority/AuditCheckpointPhaseAcquisition.php`: `beforePrepare`, `beforePersist`.
- `src/Internal/Audit/Authority/AuditCheckpointCandidateAdmission.php`: `admit`.

There is no disposal caller at MutationAdmission.consume/assertUnchanged, an observation, frame, seed owner, arbitrary workflow or another authority method. Those eight methods already hold the actual returned endpoint; if it was never returned, the declared same-capsule fallback remains the owner. Captured.close's own sticky state is the final arbiter when an inner cleanup and that outer fallback both see the same object: one lower attempt, never two.

### 2.5 Control publication and installation handoffs

The existing ordinary publication and installation workflows retain the same capture passed to their fixed candidate acquisition or publish/recover facade. No new selection origin is created. The selected digest is still bound by phase14 authenticated replay before writes, and accepted replay/tag79 still uses the exact accepted candidate without max+1.

Candidate phases10–13 return a PublicationAuthority with its original endpoints. The matching PublicationWriter method checks its fixed phase, consumes once and stores the actual endpoints before entering its protected beginRoot. Failure before consume returns is closed by the calling workflow's retained capsule; failure after endpoint return is disposed by that Writer method. recheck remains a comparison operation, not a second failure owner. It throws to that branch on Reader/equality/full-proof/checked-close failure. Its observations still require checked close and the same active root generation before delegate claim/seed.

The complete literal callers of `AuditVerifiedControlEndpoints::discardAfterFailure` are the existing `src/Internal/Audit/Provenance/PostgreSqlAuditControlPublicationWriter.php` methods `rootRevision`, `segmentAdvance`, `refresh` and `recoverExact`, each only when its existing authority-consuming branch has actually returned endpoints. This does not create a new recovery branch or grant SQL authority to phases14/15; the selected external publication route remains the Publisher path. `readCommittedCandidate` holds only its original read-selection/native rows and receives no endpoint/capture, so it is not a disposal caller.

Phases14/15 go through Publisher.recover/publish and already own their capsule completion/failure cleanup. They first finish the actual floor session, then explicitly close the capsule, and only then construct/return the result. Their caller's local fallback handles pre-delegation failure and becomes a terminal no-I/O guard after Publisher already attempted close. Installation.staged/pinned acquire their actual committed candidate/read facts before creating that new phase capture; an earlier read failure creates no capture obligation. No SQL is added under a floor, and no external publication returns a native signing grant.

### 2.6 Complete lower-close caller map

These are exact resolved callers of the existing close methods. There is no `close` accessor or new native call site outside this table and the already adopted raw-holder factory/destructor internals.

| Callee | Literal caller set |
| --- | --- |
| `History/AuditPrivateHistoryFile::close` | `History/AuditCheckpointCaptureWriter::capture` and `History/AuditControlCaptureWriter::capture` only while they still own the pre-seal file; `History/AuditSealedHistory::close`; the native holder's already declared constructor-failure/destructor sites. No Writer/Reader/replay/SQL code calls the raw owner after successful sealing. |
| `History/AuditSealedHistory::close` | `History/AuditCheckpointCaptureWriter::capture` and `History/AuditControlCaptureWriter::capture` only while they still own the returned seal before capsule transfer; `History/AuditCapturedCheckpointHistory::close`; `History/AuditCapturedControlHistory::close`. |
| `History/AuditCapturedCheckpointHistory::close` | `History/AuditVerifiedCheckpointEndpoints::{finishComparison,discardAfterFailure}`; `Authority/AuditControlAcquisition::{inspectCheckpoint,checkpoint}` for its actual failed acquisition; `History/AuditCheckpointHistoryInspection::{ordinary,control}` for no-permission completion/failure; and the seven checkpoint workflow entries in the next table. Exactly13 resolved external method callers; no creating CaptureWriter site after transfer. |
| `History/AuditCapturedControlHistory::close` | `History/AuditVerifiedControlEndpoints::{finishComparison,discardAfterFailure}`; `Authority/AuditControlCandidateAcquisition::acquire` on failure; `Authority/AuditControlPublisher::{publish,recover}` on completion/failure; and the eight publication/installation workflow entries below. Exactly13 external method callers. |

In this table `History/` means literal `src/Internal/Audit/Authority/History/`; `Authority/` means literal `src/Internal/Audit/Authority/`. Those prefixes are expanded per edge in the JSON, not wildcard caller grants.

| Exact workflow path | Failure-only capsule-close sites and phase |
| --- | --- |
| `src/Internal/Audit/Provenance/AuditOrdinaryCheckpointRegistration.php` | `prepareSelected`:3 |
| `src/Internal/Audit/Provenance/AuditControlCheckpointIssuer.php` | `prepareSelected`:4 |
| `src/Internal/Audit/Provenance/AuditCheckpointPreparationWorkflow.php` | `prepareActual`:5; `prepare`:6 |
| `src/Internal/Audit/Provenance/AuditCheckpointCandidateWorkflow.php` | `complete`:7 |
| `src/Internal/Audit/Provenance/AuditCheckpointPersistenceWorkflow.php` | `persistActual`:8; `persist`:9 |
| `src/Internal/Audit/Control/AuditControlPublicationWorkflow.php` | `rootRevision`, `segmentAdvance`, `refresh`, `recoverExact`: only the fixed capture created in that branch; candidate phases followed by their distinct committed-publication capture or exact recovery capture as already issued. This confers no scheduler bootstrap/new-policy selection origin. |
| `src/Internal/Audit/Control/AuditControlInstallationPublicationWorkflow.php` | `bootstrap`, `rootRevision`, `staged`, `pinned`: only the fixed capture created in that branch under the original selected digest. |

The actual successful consumer must have closed the preceding capture before a workflow clears its local, returns a phase result, invokes F2, uses seed/signing or starts a separate post-F2/publication capture. Clearing/replacing a still-live fallback local is forbidden by the source guard. On an exception the local guard calls close only for the capture it actually obtained. It never calls close on an unassigned variable, an earlier phase's cleared local, another invocation or a reconstructed object. This catches failure immediately after acquisition returns as well as failure entering or executing the later root.

For each named SQL root owner, its catch retains the original cause and obtains the existing finite failure from its actual Transaction.abort(original), whose original monotone graph invalidation/owned rollback/close semantics remain. An enclosing cleanup finally still invokes endpoint discard if assigned even if abort itself cannot complete; a cleanup exception cannot bypass graph poisoning or overwrite the retained primary failure. A pre-root rejection is inside that same protected region. CaptureWriter uses its existing ReadTransaction.abort boundary with the same file/seal cleanup guarantee. The surrounding graph-owning workflow retains its original invalidate/failure mapping and performs only the same-capsule fallback. No second abort or reconnect is used to claim cleanup success.

Each fixed root branch uses a nullable local endpoint only as a **local ownership variable**, not a caller success input. Successful consume/take assignment transfers the primary disposal obligation; no result is fabricated to fill that local. On catch it invalidates/aborts through its already selected common-graph/transaction boundary and invokes discard if the local exists, preserving the original sanitized failure on all cleanup failures. If no endpoint was obtained, the outer workflow owns close. Existing floor-owning failures finish their actually owned floor before any SQL abort, then propagate through the same graph boundary. No catch/finally path emits a success, signs, calls F2, reconnects, resets the graph or produces later C3 success evidence.

## 3. Method, path, authority and native preservation ledger

| Owner | Final count / direct delta |
| --- | --- |
| AuditHistoryRecord |9->10, adds native, factories/ctor2->3 args; all R4 retained. |
| AuditHistoryNativeGroup |9 unchanged; complete literal factory/getter caller binding only. |
| AuditHistoryGroupCodec |Existing<=8 unchanged; encode at two CaptureWriter.capture sites, decode solely Reader.next. |
| AuditHistoryReader |Exactly10 unchanged; four local constructor sites unchanged; original read/decode and getter flow pinned. |
| AuditSealedHistory |Exactly10 unchanged; two literal pre-capsule CaptureWriter close callers and transfer/terminal close-state refinement. |
| Each CapturedHistory |Exactly10 unchanged; comparison loan/identity untouched; explicit same-object terminal close guard and literal failure callers. |
| Each VerifiedEndpoints |8->9, only discardAfterFailure plus unsuccessful-state guards; original success shape, identity and factory origin unchanged. |
| HistoryWriter / both CaptureWriters |Existing<=10, same constructors/entries; native ownership local transfer and inline failure cleanup, no new helper. |
| WorkRecorder / CheckpointPhaseAcquisition / CandidateAdmission |10 /10 /6 unchanged; literal catch paths call existing newly ninth endpoint method. |
| PublicationWriter |Existing7 unchanged; four existing branches own unsuccessful endpoint cleanup. recheck still returns actual NativeObservation only after equality and checked close. |
| CheckpointPhaseAuthority / CandidatePermit / ControlPublicationAuthority |10 /8 /9 unchanged. No capture getter/disposal or another claim/consume surface. |
| Preparation/Persistence / Candidate / Registration/Issuer workflows |7/7 /6 /6/6 unchanged. Only the existing listed entries retain and clean their local capsule on failure. |
| ControlPublicationWorkflow / InstallationPublicationWorkflow |10 /10 unchanged. No new selection, dispatch target, callback or resource helper. |
| PrivateHistoryFile and other native holders |Exactly adopted10/10/10/9/10 unchanged, including constructor/destructor and R4. No native operation added by this transport/ownership amendment. |

All original wire/SQL/full-history/current-authority/native constraints remain. In particular:49 nonterminal tags+255;15 phases; full uncapped historical issuer proof and bounded memory; exact original F2/candidate/envelope bytes; same Connection and fencing; one common monotone graph; READ ONLY capture committed before the floor; no SQL/seed/provider under that floor; floor finish before SQL; checked history close before seed/sign/F2/result; final phase7 bounded reread without reopen; all R4; supported consuming-close premise and unchanged masked-error limitations. Neither an endpoint failure flag nor a capsule close latch is a replacement for actual successful replay, native equality, physical EOF, seed admission or an observation's root generation.

Every path named here is already in the frozen combined inventory or the prior23+2 type closure. There is no new path or class admission. The existing26 edit paths/exact35 new PHP test paths, all3986 predecessor IDs,27 bases/58 native variants,512M limit, deterministic CI partition and required quality identity remain unchanged. The separate verification-review intro wording issue, if adopted by Root, does not alter this type correction or any native case result; this author does not edit that frozen artifact.

## 4. Discriminating verification assigned to existing paths

These are required future actual Product/guard cases, **Not Run** here. They fit the already issued named tests and their existing case/data inventories; no extra TestCase/helper/fixture/path or undeclared method budget is granted. Tests must exercise the real owner, not merely count names or construct a success array.

| Existing admitted test path | Concrete affected cases |
| --- | --- |
| `tests/Internal/Audit/Authority/History/AuditHistoryGroupCodecTest.php` | Valid actual Reader-native group roundtrip; wrong/unknown/missing scalar key/type/ref/UTF-8; non-native tag with group, native tag with null, mismatched group tag; original byte payload retained exactly. Validate the same-local encode/decode to Record flow, not a parallel parser. |
| `tests/Internal/Audit/Authority/History/AuditHistoryGrammarTest.php` | Both outer replay boundary paths receive actual Record.native/scalars and original bytes; foreign/missing projection denies. Physical suffix, truncated group/byte/trailer, wrong count/hash and reordered/omitted group deny before endpoint factory. The two comparison Reader paths use the same complete-pass/byte route. |
| `tests/Architecture/AuditAuthorityHistoryArchitectureTest.php` | Pin all expanded factory/getter/decode/close/discard edges, exact arguments/counts/R4 and four Reader construction sites. Mutation negatives move encode/decode, substitute an unrelated valid group/payload, add an ArtifactReplay/getter/decoder caller, use a lower close after transfer, clear the fallback early, remove catch around beginRoot, use finishComparison on failure, reset a latch, add a destructor success path or introduce a getter/helper. |
| `tests/Internal/Audit/Authority/History/AuditHistoryCapturePostgreSqlTest.php` | Actual successful seal then real RO commit failure: no capsule/floor/result, one owned close attempt; failed seal/factory before transfer uses original file, failed capsule creation after RO commit uses seal. Checked factory handoffs detect both skipped close and double lower close. No file leak is excused by eventual GC. |
| `tests/Internal/Audit/Authority/AuditCheckpointPhaseAcquisitionTest.php` | Failure after ControlAcquisition returns and before actual write root; consume rejection before endpoint return; beginRoot refusal after endpoint return; pre-F2 native mismatch/read failure and checked-close failure. Original same capsule closes once; no F2/seed/result; phase authority unusable and actual graph poisoned. |
| `tests/Internal/Audit/Authority/AuditCheckpointCandidateSignerTest.php` | Failure before permit take returns relies on CandidateWorkflow's capsule; failure after take/begin/compare/close/commit uses CandidateAdmission endpoint disposal. No seed/F1 operation follows. Final recorder failure after the first checked close does0 reopen/Reader/close calls and retains final actual bounded reread. |
| `tests/Internal/Audit/Provenance/PostgreSqlAuditCheckpointProductionTest.php` | Both actual ordinary/control reservation entries and before/after F2 phase handoffs execute their permitted disposal routes. Wrong native bytes/absence/endpoints and an exception mid-comparison deny with the real root/graph boundary. A lower cleanup exception preserves the original sanitized failure and never causes a second close. |
| `tests/Internal/Audit/Provenance/AuditCheckpointAmbiguityPostgreSqlTest.php` | Actual root/commit uncertainty before and after history close leaves no returned phase fact; disposed graph cannot be retried/reconnected; fresh-graph recovery still uses actual immutable rows. Already-closed phase7 endpoints cannot reopen or masquerade as a first comparison. |
| `tests/Internal/Audit/Authority/AuditSigningImportNativeIntegrationTest.php` | Actual supported native close/final-stat/read/append failure propagation through capture -> replay -> native compare -> endpoint/root owners, with unchanged58-route receipts. Confirm0 seed/sign on every pre-seed failure and distinguish0 extra native calls on outer terminal guard from1 original consuming attempt. PHP's unobservable consumed-close limitations are retained, not turned into an observable negative PASS. |
| `tests/Architecture/AuditCheckpointProductionArchitectureTest.php` | Exact eight checkpoint discard callers and same-capsule workflow fallback, shared graph, no PhaseAuthority extra method, no alternative observation or success result. The same original endpoint and root-generation checks survive new failure branches. |
| `tests/Architecture/AuditProvenanceArchitectureTest.php` | Four publication writer discard callers, SQL-only mutation owner, immutable same Connection/fence/transaction order and no Publisher endpoint/seed/SQL widening. Pre-root failure stays inside the fixed owned catch. |

The already issued `tests/Internal/Audit/Control/AuditControlInstallationPublicationTest.php`, `tests/Internal/Audit/Provenance/PostgreSqlAuditControlPublicationPostgreSqlTest.php` and `tests/Internal/Application/ApplicationAuditControlInstallationIntegrationTest.php` must exercise the listed workflow/Publisher paths with staged/pending/already-accepted candidates and the original selected digest; this creates no new test file. The companion repeats these literal admitted paths. They must distinguish pre-delegation fallback from actual Publisher-owned completion/failure, exact phase14 digest check before writes, and0 extra close after inner completion. Separate full-production/DB proof remains mandatory; a passing descriptor holder test or mock same-name recorder cannot stand in for it.

The native scenario obligations do not create another58-case variant or overwrite the binder. Existing managed errors use their actual observed failure/cleanup routes; killed-process cases use the existing parent reap/descriptor/filesystem observations. A process killed before cleanup is not claimed to have executed PHP close. No test callback, production fault flag, arbitrary resource injection, invisible limit waiver or extra native lease is authorized here.

## 5. Evidence and handoff

Only bounded text/source/JSON reads, exact path/caller/count bookkeeping and SHA-256 comparisons ran. No Product/test implementation, PHP/Node/test/native/DB/Docker/compiler/network/Git operation, governance/STATE change or subagent occurred. No command output is a runtime, close, memory, cold-load or SQL qualification result. The input JSON records each selected input's before/after hash, literal caller matrices, method counts, affected paths and test joins. Any concurrently changed governance input is identified explicitly rather than silently classified as stable Product.

The sole observed concurrent input change is Root's append to the F5 Task (0e669232 ->980b975f): complete child packaging/Reader promotion scope and the separate CVR interruption-intro review. The original34442-byte prefix is unchanged; its appended1464 bytes do not change this HT assignment or Product/native boundaries. All27 other selected inputs remained unchanged at this author freeze.

The proposal supplies a complete finite route for HT-01 and HT-02; independent affected review may accept or reject its exact declarations. It does not self-close the review or authorize Product implementation. Root retains packaging, authority adoption, resource leases, Acceptance and commits. Original130/129 native support and all closed CIR/PI conclusions remain the governing boundaries.

残り工程: affected independent HT review and Root adoption of these exact transport/cleanup joins with the previously closed declarations; complete durable implementation packet; actual Luna implementation and native/strict/full/CI/export gates. F4/D/Required/full F5/F6 and1.3 release gates remain open.

Next Action: Root sends this frozen candidate and inputs to `/root/f3_authority_review` for the assigned bounded r3 review, preserving all original maps and closed CIR/PI conclusions. No Product or runtime issuance follows before the combined packet is adopted.
