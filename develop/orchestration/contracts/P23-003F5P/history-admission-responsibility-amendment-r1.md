# History native admission — finite responsibility amendment r1

Status: Candidate for affected independent review; effective only after Root adoption and committed Task issuance.

This amendment resolves measured Codec complexity30>15 and NativeGroup107>15 /
Kan4.51>1.6 without changing those limits. It adopts the finite responsibility
split from [the preserved proposal](history-admission-budget-options.md), with
all three corrections from [design review r1](history-admission-design-review-r1.md):
HAD-01 actual/captured version domains, HAD-02 both local recovery matrices and
HAD-03 the existing timestamp guard owner. The original proposal's open questions
and manual estimates do not remain implementation choices. Original F1/F2,
wire tags/keys/caps, native custody and actual origin contracts remain unchanged.

Only the seven new helpers, single-schema admission, Codec preflight/getter
reduction, local relations and exact caller/preload/verification deltas below
supersede earlier text. This is an internal unreleased contract change; it does
not establish runtime, Product Acceptance or1.3 release readiness. Current Stable
and public routes are unchanged. Root owns adoption/Git/leases; Luna receives the
committed exact scope and returns findings before any further expansion.

## 1. Exact type and method inventory

All seven added paths are under `src/Internal/Audit/Authority/History/`. Each is final, has no instance state and a private zero-argument constructor. Public relation operations are static and return `void`; the scalar operation returns one admitted `bool|int|string|null`. None can create a group, record, proof or authority. They accept only the explicit fixed inputs below. They expose no schema getter, arbitrary predicate/key list, closure, callable, service registry or dynamic dispatch.

`S` below requires `#[SensitiveParameter]`. An array parameter has an exact closed shape DocBlock copied from the corresponding immutable History payload, not a new general-purpose row-bag contract. Private scalar helper `mixed` means one pre-admission scalar candidate, not a retained object or a public authority input. NativeGroup remains the only Product caller that supplies raw scalar candidates.

| Exact added filename | Complete declared methods | Responsibility / direct callers |
| --- | --- | --- |
| `AuditHistoryScalar.php` | private `__construct()`; public `admit(S string $kind,S mixed $value):bool\|int\|string\|null`; private `admitBoolean(S mixed $value):bool`, `admitInteger(S string $kind,S mixed $value):int`, `admitString(S string $kind,S mixed $value):string`. **5/max2.** | Strict single-value domains, returning the unchanged admitted primitive. `admit` has exactly one call site in NativeGroup.__construct's schema loop. Its three private operations are called only by its own admit. |
| `AuditHistoryContextRelations.php` | private `__construct()`; public `header(S array $v):void`, `externalAnchor(S array $v):void`, `partition(S array $v):void`, `recordEndpoint(S array $v):void`, `stagedBegin(S array $v):void`. **6/max1.** | Partition/tenant, genesis and local context pairs; exactly the five fixed NativeGroup.__construct dispatch arms below. |
| `AuditHistorySegmentLifecycle.php` | private `__construct()`; public `assertValid(S array $v):void`. **2/max1.** | One complete Segment12 lifecycle. Exactly one NativeGroup.__construct call site shared by the four Segment12 tags. |
| `AuditHistoryClosedSegmentRelations.php` | private `__construct()`; public `proof(S array $v):void`, `binding(S array $v):void`, `version(S string $state,S int $version):void`. **4/max2.** | Closed proof/binding local relations and the state-dependent segment version range. proof/binding each have one NativeGroup.__construct site. version has exactly two sites: SegmentLifecycle.assertValid and this class's proof. |
| `AuditHistoryWorkRelations.php` | private `__construct()`; public `presence(S array $v):void`, `reservation(S array $v):void`, `step(S array $v):void`. **4/max1.** | Actual work presence, reservation class/null binding and fixed ordinal/phase/predecessor relationships. Exactly three NativeGroup.__construct sites. |
| `AuditHistoryCheckpointEndRelations.php` | private `__construct()`; public `assertValid(S array $v):void`. **2/max1.** | The single bounded checkpoint endpoint and its actual-record absence/null pairs. Exactly one NativeGroup.__construct site. |
| `AuditHistoryPublicationRelations.php` | private `__construct()`; public `transition(S array $v):void`, `publication(S array $v):void`, `end(S array $v):void`. **4/max1.** | Bootstrap transition, publication predecessor/acknowledgement and accepted/next-revision local relations. Exactly three NativeGroup.__construct sites. |

There are **7 new paths /27 new declared methods**; no class exceeds6 methods or2 arguments. No other helper/type/test path is requested. The private constructors have zero Product instantiation sites. The exact static edges above, not a namespace-wide exemption, are the complete helper caller allowance. Core safe-string/timestamp calls move from NativeGroup.__construct to Scalar.admitString; existing Core constant reads are finite. Helpers do not call one another except the one stated SegmentLifecycle→ClosedSegmentRelations.version edge.

### One schema and complete31-tag routing

Keep NativeGroup's private SCHEMAS as the sole payload-key/domain schema; restore literal field keys and use semantic private F/?F descriptor constants where required by the pinned password rule. Remove the concatenated field-name constants. `signing_key_fingerprint` remains a literal key. Set successor tag18 to the explicit open/closed/sealed domain;17/19 remain closed/sealed and23 open. The step predecessor descriptor is nullable integer1..3. Preparation31 closed_state_version remains2..9007199254740990. This changes no wire key or payload shape.

NativeGroup.__construct performs, inside its existing sanitizing try/catch: closed tag/schema lookup; non-list array admission; exact string-key set; PHP-reference rejection; per-field strict scalar admission into a fresh array using Scalar.admit's actual primitive return; the following fixed literal dispatch on that admitted array; then its detached scalar snapshot. It must finish all checks before retaining a SensitiveParameterValue. Constructor/fromNative/fromDecoded retain native `array` parameters and may document the pre-admission input precisely as `array<array-key,mixed>`; only the admitted array receives the closed shape annotations. Both factories continue to call only this constructor. No helper receives a closure, tag-controlled method name, captured object or database row reader.

| All31 scalar tag values, with adopted enum owners unchanged | Required relation entry after every scalar has passed |
| --- | --- |
| 1;3;16;21;52 | ContextRelations.header;externalAnchor;partition;recordEndpoint;stagedBegin respectively |
| 17,18,19,23 | SegmentLifecycle.assertValid |
| 24;49,53 | ClosedSegmentRelations.proof;binding respectively |
| 25;26;28 | WorkRelations.presence;reservation;step respectively |
| 35 | CheckpointEndRelations.assertValid |
| 65;73,76;79 | PublicationRelations.transition;publication;end respectively |
| 5,31,33,48,51,55,64,67,68,72,75 | Scalar/schema admission is complete for the local group. The existing cross-record, signed-envelope, actual-origin and phase checks remain in their original Grammar/replay owners. |

Every other one of the50 tags fails native-group admission. Dispatch uses fixed calls in a literal match, not a callable table or an authority dispatcher. The three-enum union remains spelled in full on the existing tag-bearing declarations; no new helper needs a tag argument. Record retains its exact10/max3 and all existing getter/factory consumers. NativeGroup retains its exact9/max2 and R4 behavior.

### Local relation content, without expanding authority

* Context: Header and Partition tenant fields are paired; system:global iff that pair is absent. Header phases1–9 require segmentId;10–15 require system:global. Partition sequence0 iff head null. ExternalAnchor acknowledgement pair and current first-sequence/predecessor genesis relation; StagedBegin actual-current genesis relation; PreviousRecordEndpoint sequence/previous-hash genesis relation. Actual tenant digest-to-scope equality, workId-to-actual-reservation equality and external signed-content equality keep their original owners.
* Segment lifecycle: first1 iff predecessor hash null; final/head paired; open iff closed_at/successor/token are null; closed/sealed require a nonempty final/head pair. A populated open row is legal. If final is present it is >=first; present closed_at is >=opened_at; successor is never self. Delegate state/version bounds to the finite version operation. It must not manufacture closed state for tag18.
* Closed facts: proof range/count arithmetic, genesis equivalence for predecessorRecordHash and previousSegmentId, no previous/self or successor/self identity, and the actual state/version range. Binding9 requires last>=first and successor!=segment; its first sequence need not be1 because bootstrap may begin later. version admits actual open1..MAX and actual closed/sealed2..MAX. Tag31 captured closed_state_version remains2..MAX−1. The reserved increment in spec125:122–135 constrains captured F2 preparation versions, not every observed Segment12/Proof14 version. Actual closedMAX is a valid local observation even though F2 cannot newly prepare it. Actual sealed2 is locally admissible but does not prove the separate F2 captured+1 binding. F2's captured-version+1 binding and actual proof origin stay outside these pure local helpers.
* Work: presence follows the complete matrix below, projected onto its reservation/preparation/complete booleans and step_count; no candidate member is introduced. Control reservation requires segment_binding_digest and ordinary forbids it; the other five prohibited reservation projection fields stay exactly null in SCHEMAS. Step ordinals1/2/3/4 map exactly to preparation_bound/complete_candidate/artifact_persisted/ingress_acknowledged. Previous ordinal is null at1 and ordinal−1 otherwise; the first previous digest equals reservation, later previous digest does not stand in for the reservation. Equality to the actual preceding step remains Grammar/replay work.
* Checkpoint endpoint: retain all five null pairs: work_id/reservation_digest; preparation_checkpoint_id/unsigned_sha256; unsigned_sha256/unsigned_length; candidate_sha256/candidate_length; complete_sha256/complete_length. Require last_ordinal0 iff last_step_digest is null and higher_work_ordinal_present=false. After strict scalar and pair admission, apply the complete matrix below. These checks do not recompute bytes, infer actual rows or replace agreement with the separately captured records.
* Publication: previous transition/state digests paired; null bootstrap pair requires root_revision. Publication previous revision/head paired; revision1 iff absent, otherwise previous_revision=revision−1; acknowledgement pair paired. End accepted_revision0 iff accepted_head_digest null; next-head/document hash paired; `next_revision−accepted_revision` is0 or1. Difference0 additionally requires the same non-null head and a present document hash; difference1 admits absent or present actual-next pair as the current protocol permits. Difference0 remains possible at MAX. Exact prior/candidate document equality, phase14/15 authorization and higher-than-next contradiction handling still belong to the existing actual-record/Grammar/replay owners. An equal revision alone never grants recovery authority.

### Complete endpoint and presence matrix

P means preparation_checkpoint_id and its unsigned hash/length; C means candidate
hash/length; F means persisted complete hash/length. Every other combination is
invalid. These rows apply after strict scalar admission and the five null pairs.

| Work/reservation present | last_ordinal | P | C | F |
| --- | ---: | --- | --- | --- |
| No | 0 | absent | absent | absent |
| Yes | 0 | absent or present | absent | absent |
| Yes | 1 | present | absent | absent |
| Yes | 2 | present | present | absent or present |
| Yes | 3 or4 | present | present | present |

Ordinal0 with P present is the committed F2 prepare before ordinal1 binding;
ordinal2 with F present is committed F2 persistence before ordinal3 recording.
Both are required recovery positives. Candidate presence is exactly ordinal>=2:
its actual bytes are stored with ordinal2 itself. Preparation cannot be required
iff ordinal>=1, and complete cannot be required iff ordinal>=3.

WorkRelations.presence for tag25 uses the same projection: reservation=false
requires count0 and absent preparation/complete; reservation=true with count0
permits preparation and forbids complete; count1 requires preparation and no
complete; count2 requires preparation and permits complete; count3/4 requires
both. No candidate member is added. Grammar still compares the observed actual
step count/ordinal sequence, tag30 at ordinal2, preparation31/32 and complete33/34
presence, work/reservation identity and End digests/lengths to original records.
Replay retains signed predecessors, F1/F2 byte equality, live scope and actual
source/phase preconditions. No helper success grants authority. Historical
ordinal4 remains readable without adding its producer to this child.

MAX is AuditValidationException::MAX_SAFE_INTEGER. For all integer domains require an actual int before bounds; booleans/floats/numeric strings do not pass. Scalar.admit's null branch returns null only for the enumerated nullable descriptor literals and `null`; it must not accept an unknown nullable-looking descriptor. Its non-null dispatch explicitly lists the finite integer, bool and string descriptor sets; default rejects. The private operations return their input only after actual strict type/domain admission, without coercion. String admission retains the exact safe-string and grammar rules, `aft:v1:` token form, state/cause/class/phase membership and real timestamp check. Do not replace domain validation with PHP scalar-type checks.

## 2. Codec correction without an eighth helper

Keep exactly the two public methods encode(S NativeGroup):string and decode(T,S string):NativeGroup, max2 arguments. Here T is the full adopted three-enum union, not an actual alias declaration. Remove Codec.SCHEMAS, schema and assertScalars. The final NativeGroup is already admitted and immutable; encode obtains its actual scalars once, JCS-encodes within a fresh-exception boundary and enforces16384 bytes. It does not reread Group.tag. Thus remove precisely the GroupCodec.encode→NativeGroup.tag getter edge: Group.tag consumers4→3; Group.scalars consumers remain13. All Record getter/factory and two same-local serializer flows remain unchanged.

Decode first rejects non-native tags using the exact31 literal cases above. Keep the current nonempty/16384/raw-CR/raw-LF check. Keep the existing escaped-string stripping regex. Then require the **original** first/last bytes to be `{`/`}`, explicitly reject a null preg_replace result, and require the stripped text to match the following finite language:

```text
/\A\{[^{}\[\]"]*\}\z/D
```

This is the same flat-container exclusion expressed as one grammar: exactly one outer object; no inner brace, array delimiter or residual quote. It is not a replacement JSON parser. Both `preg_match===false` and0 fail through `!==1`. The null check must short-circuit before passing stripped text to preg_match. Original braces remain separately checked, so removing quoted prefix/suffix material cannot conceal an invalid raw boundary. Raw CR/LF remain rejected even inside quoted text before recursive JCS.

Only after this preflight may JCS decode/toNative/canonicalJson run in the sanitizing catch. Require the decoded value to be an associative array and the complete original bytes to equal the canonical bytes. Then call NativeGroup.fromDecoded exactly once on that tag and those actual parsed scalars; its constructor owns all keys/domains/relations. No guessed scalar DocBlock may silently assert that unvalidated mixed values passed: the candidate array must retain its actual broad pre-admission type until NativeGroup checks it. Group/Record immutable snapshot annotations describe only successfully admitted output.

The sole Reader.next decode edge and exact original-payload/group binding do not change. Codec.encode cannot accept an arbitrary array, and no relation helper gains codec/group/record getter access. This replaces earlier suggested primitive descriptor constants in the now-deleted duplicate Codec schema; NativeGroup's semantic F/?F constants remain appropriate. R4 and fresh, fixed, cause-free exceptions remain mandatory.

### Quality boundary

The original proposal's manual complexity estimates remain planning evidence,
not qualification of this corrected matrix. Actual class cyclomatic15, Kan1.6,
methods10, ordinary parameters5 and strict baseline remain unchanged. Measured
failure returns a concrete contract finding to Root; it grants no extra helper,
obfuscated field, suppression, threshold or baseline change. A private per-owner
literal list of the five endpoint null pairs is cohesive validation, not a new
generic relation engine. Actual source/tests decide compliance.

## 3. Exact amendment, preload and affected inventory

This supplement replaces only the earlier no-extra-History-helper restriction with the seven exact types above, after Root adoption and committed issuance. All original copied contracts and input JSON remain byte-identical. README supplies this finite precedence; manifest and allowlist pin its exact effective scope. The retained investigation and review are historical evidence, not alternate implementation authority.

The supplement supersedes only: the no-extra-History-helper clause with the seven paths/method/caller sets in §1; Codec's duplicate private schema operations with §2; the one removed tag-getter edge; and the finite preloaded type list. It must retain every original wire tag/key/cap,31-group semantics, source-origin/same-local binding, method/argument ceilings, baseline/quality thresholds and all other caller limitations. No widened namespace wildcard or Product public API follows.

The affected Product inventory is exactly the seven new paths plus the existing `AuditHistoryNativeGroup.php`, `AuditHistoryGroupCodec.php`, and `AuditCheckpointReplayRuntimePrerequisite.php` in the same History directory. Record's already-assigned union/format/test correction remains in the rejected four-file unit; this architecture proposal gives Record no new method or dependency. The three adopted enum files are untouched by this proposal.

Spec130§9.2 additional History/authority resolutions grow **18→25**, with these seven literal FQCNs placed immediately after AuditHistoryNativeGroup in the existing initializer's actual eager resolution sequence:

```text
BlackOps\Internal\Audit\Authority\History\AuditHistoryScalar
BlackOps\Internal\Audit\Authority\History\AuditHistoryContextRelations
BlackOps\Internal\Audit\Authority\History\AuditHistorySegmentLifecycle
BlackOps\Internal\Audit\Authority\History\AuditHistoryClosedSegmentRelations
BlackOps\Internal\Audit\Authority\History\AuditHistoryWorkRelations
BlackOps\Internal\Audit\Authority\History\AuditHistoryCheckpointEndRelations
BlackOps\Internal\Audit\Authority\History\AuditHistoryPublicationRelations
```

The prerequisite retains exactly constructor/initialize and only ApplicationRetentionRuntime.signingTasks / ApplicationAuditControlInstallationComposer.compose as its two production callers. A class-name string alone is not resolution. All seven must actually load before either floor-capable graph/use; no loader is added to NativeGroup, Codec, Reader or replay. The original15 existing types, three replay/fact types, separately built Preflight, failure types and nonempty Normalizer FORM_C warmup remain unchanged. No new F1/F2 preload exception is needed. D179's rationale should record measured class-level budget failure, single-source schema admission and pure responsibility boundaries; it is an architecture amendment, not a quality waiver.

Allowlist total grows **297→304** by those seven Product paths only. Recompute manifest hashes and exact package required counts from the amended finite inventory; do not infer a package count from304 or edit old evidence. Effective closed type inventory27 becomes34. Existing test path inventory35 new/26 existing and native58-run inventory do not grow. The exact affected verification paths are:

* `tests/Internal/Audit/Authority/History/AuditHistoryGroupCodecTest.php`: all behavioral checks through the real two factories/codec and existing carrier controls; no new standalone helper test file.
* `tests/Architecture/AuditAuthorityHistoryArchitectureTest.php`: literal type/method/caller/sensitive-argument/preload inventories and mutations below. No F1/F2 guard exception; no general allowance for seven public statics.
* `tests/Architecture/AuditRecordArchitectureTest.php`: retain the complete timestamp caller inventory with exactly `Internal/Audit/Authority/History/AuditHistoryScalar.php => admitString:1`. Its A2 reference row permits AuditRecordTime only at that path; remove a planned NativeGroup timestamp row instead of allowing both. Preserve every unrelated tuple. Include alias/FQCN positives and relocated-method, duplicate-call, sibling-helper and extra-A2-type negatives. The AuthorityHistory guard additionally fixes this one Core-validation site and every helper edge. This existing path is already in the26-file allowlist.
* `tests/Fixtures/Audit/AuthorityFilesystemCase.php`: update both existing cold cases' exact expected loaded-class closure and require the real actual-composition path. Existing `authority-filesystem-native.php`/native runner inventories remain58 runs; change observation inventory only if their current literal ownership requires it, not a new case or flag.
* `tests/Consumer/framework-package-export.sh` and `tests/Consumer/framework-package-required-inventory.sh`: add the seven literal paths to both archive expectations; update the first script's pinned hash and exact required count in the second.

These are the six named affected verification paths. No CI workflow, runtime configuration, dependency, SQL migration or seventh test path is added by this amendment. Existing caller collectors must follow the Core validation calls at their new one allowed owner, without enlarging F1/F2/public API exceptions.

## 4. Required discriminating proof on the successor

Reuse `history-layer-review-r2.md` HL-01/04 as open findings; this design does not close them. Through both factories and the actual codec, retain all31 independent canonical vectors and reject all19 non-group tags; separately isolate missing versus extra keys, references, integers versus strings/bools/floats, null pairs, scalar caps/grammars and foreign enum type rejection. Correct void-arrow/private-clone assertions rather than altering Product R4. Include the valid open successor, populated current open segment, exact already-accepted publication at ordinary/MAX revisions, actual closedMAX and sealedMAX positives; actual closed/sealed1 and MAX+1 negatives; tag31 captured2/MAX−1 positives and1/MAX negatives. Retain the existing exact F2 sealed(MAX−1,MAX) binding positive separately. Add isolated mutations for each local relation in §1, including four valid ordinal/phase pairs and otherwise-consistent wrong-phase cases. Exercise every allowed endpoint matrix row through both factories and real Codec, including both recovery gaps. Add otherwise-consistent single-condition negatives for missing preparation after step1, candidate with ordinal0/1, missing candidate from ordinal2 onward, complete before ordinal2, and absent complete at ordinal3/4. Include the corresponding tag25 projection cases; retain actual-record/Grammar/replay tests separately.

The existing architecture test must reject: any eighth helper; any extra public operation/constructor/caller; a helper calling SQL/F1/F2/current trust/codec/group/record getters; callback/dynamic method dispatch; native retention before admission; missing a descriptor or relation call; a second divergent Codec key schema; restoring encode→Group.tag; replacing the parsed array/local original payload/group in the sole decode/factory flow; and omission/late placement of any one of the seven actual preload resolutions. Alias-positive fixtures must continue to resolve the allowed literal owners.

Preflight mutation controls must remove/move the raw check after JCS, admit a nested object/array, remove original boundary checks or permit a PCRE failure; they must fail for the intended pre-JCS reason, not only because NativeGroup later rejects arrays. Quoted braces/brackets and escaped quotes in an otherwise permitted I field stay positive controls. Retain synthetic sentinel tests with exception args enabled, a JCS-origin failure, all helper failure routes, no previous cause and unchanged carrier R4. No Product test seam or callback is permitted.

Root's issued verification must then run meaningful targeted tests plus actual formatter/lint/analyzer/architecture checks on a frozen successor. Later actual cold/native/full/SQL/export obligations stay where already assigned; they are not replaced by source arithmetic or this report. Do not rerun the accepted enum-only unit merely because this proposal exists.
