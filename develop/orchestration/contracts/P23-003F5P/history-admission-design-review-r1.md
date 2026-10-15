# Independent History admission design and semantic review r1

Status: **Changes Required before adoption: HAD-01, HAD-02 and HAD-03 below.**
The seven-helper architecture is coherent with these finite corrections. No
eighth helper, wider caller family, changed quality limit or unrelated redesign
is required by this review. The unchanged proposal is not Green for issuance.

Reviewer: `history_contract_review`, configured Astra/max. P is
`/home/kubotak/projects/blackops`; C is `.phpunit.cache/f2-orchestration/accepted-integration`;
E14 is `.phpunit.cache/f5-orchestration/20261014`. All commands started at P.
Reviewed proposal: `E14/history-admission-budget-options.md`, final author SHA256
`861134dd5b44e3563ac96483e78ffeaed930b8e893c76235045ec0ade4ff754a`.
Product paths below are relative to C; contract names refer to C's durable
`develop/orchestration/contracts/P23-003F5P/` originals.

## Summary

The proposal correctly separates primitive domain admission, local context,
segment lifecycle/proof, work, checkpoint endpoints and publication relations.
It keeps one closed31-key/domain schema in NativeGroup, keeps admission before
retention, removes Codec's duplicate schema without opening its callers, and
preserves the pre-JCS flat-input boundary. Each helper is stateless, has a private
constructor, and receives only a previously scalar-admitted closed shape or the
single scalar candidate in Scalar.admit. None can produce authority.

Three concrete corrections are needed: actual segment versions must not inherit
the preparation version's reserved increment; the local endpoint/presence matrix
must include both normal order and durable recovery gaps; and the existing global
timestamp architecture owner must be named in the verification inventory.
The findings below supply exact replacement rules, rather than returning these
choices to the implementation worker.

## HAD-01 — High — actual stateVersion is not captured closed_state_version

Proposal:51/71 and §7 currently apply `closed2..MAX−1` through the shared
ClosedSegmentRelations.version operation, including Segment12 and Proof14.
That rejects a valid actual closedMAX observation. MAX is9007199254740991.

Evidence:

- The original History map:94–135 types Segment12.state_version and
  Proof14.stateVersion as the actual native positive integer, while tag31's
  closed_state_version is explicitly2..9007199254740990. It expressly prohibits
  deriving the captured version from a sealed row without F2's binding.
- Accepted `Storage/PostgreSqlAuditRecordStore.php:389–393` permits closing an
  open segment at MAX−1; `closeSql:1485–1501` increments its version once. Its
  actual result can therefore be closedMAX, with no available later seal increment.
- `Checkpoint/PostgreSqlAuditSegmentRowParser.php:91–95` reads the actual version
  through MAX. `PostgreSqlAuditClosedSegmentGraph::target:59–61` and
  `PostgreSqlAuditClosedSegmentNeighbors::{previous,successor}:42–44,85–89`
  enforce the closed/sealed lower bound2 without changing that upper bound.
- The distinct operation `PostgreSqlAuditCheckpointPreparationWorkflow::create:101–106`
  rejects closedMAX as ResourceUnavailable. `AuditCheckpointPreparationRowShape:67`
  admits only2..MAX−1 for the saved preparation boundary.
- `PostgreSqlAuditCheckpointStoredBinding::stateVersion:94–103` requires captured
  in2..MAX−1 and actual=current captured for closed, captured+1 for sealed.
  `AuditCheckpointPreparationTest:151–170` already fixes the sealed(MAX−1,MAX)
  positive. This test source was inspected, not executed.

**Finite correction:** use these distinct local domains without adding a field,
flag, tag argument, helper or F2 call:

| Field / state | Local admission |
| --- | --- |
| Actual Segment12, open | int1..MAX |
| Actual Segment12, closed/sealed; actual Proof14, closed/sealed | int2..MAX |
| Tag31 captured closed_state_version | int2..MAX−1 |

Change the existing proposed `version(string state,int version):void` body
contract accordingly; keep exactly its two caller sites. State membership and
the distinction between open-capable tag18, closed/sealed17/19/24 and open23
remain fixed by the native schema. Do not decrement a sealed version or invent
a captured value in this helper. A locally admitted sealed2 still does not prove
an exact F2 retry: the unchanged captured+1 join requires actual at least3 when
it binds to an actual preparation. That join belongs to the existing F2 and
semantic owners and must remain there.

Spec125:122–135 says “Captured closed versions” in the proof discussion; record
explicitly in the adopted amendment that the reserved increment constrains the
F2 captured/preparation boundary, not every observed Segment12/Proof14 version.
If Root intends a different global cap, that is a spec/current-accepted-F2
conflict needing a separate decision; it must not be resolved by silently
tightening this History carrier. The correction above matches the current
accepted owner behavior without changing F2.

Required discriminators: actual closedMAX and sealedMAX local positives; actual
closed/sealed1 and MAX+1 negatives; tag31 captured2/MAX−1 positives and1/MAX
negatives; retain the exact F2 sealed(MAX−1,MAX) binding positive separately.

## HAD-02 — Medium — close the local CheckpointEnd and Presence matrix

Proposal:73/§7 deliberately leaves additional ordinal/presence implications
unresolved. They are now determinable from immutable History:105–113,231–243
and Composition:177–180,194–196,224–234. Ordinal1 follows actual F2 preparation;
ordinal2 durably stores the candidate before F2 persistence; ordinal3 follows
actual F2 persistence. Those steps occur in separate roots. Historical ordinal4
is readable but this child does not gain its producer.

**Finite correction:** in CheckpointEndRelations.assertValid use the following
complete local matrix after strict scalar admission and all existing null-pair
checks. P means preparation_checkpoint_id plus its unsigned hash/length; C means
the candidate hash/length; F means the persisted complete hash/length.

| Work/reservation present | last_ordinal | P | C | F |
| --- | ---: | --- | --- | --- |
| No | 0 | absent | absent | absent |
| Yes | 0 | absent or present | absent | absent |
| Yes | 1 | present | absent | absent |
| Yes | 2 | present | present | absent or present |
| Yes | 3 or4 | present | present | present |

Every other combination is locally invalid. Retain these five explicit pairs:
work_id/reservation_digest; preparation_checkpoint_id/unsigned_sha256;
unsigned_sha256/unsigned_length; candidate_sha256/candidate_length;
complete_sha256/complete_length. Retain last_ordinal0 iff last_step_digest is
null, and higher_work_ordinal_present=false.

Two positives are essential: ordinal0 with P present is the committed F2 prepare
before ordinal1 binding; ordinal2 with F present is committed F2 persistence
before ordinal3 recording. Requiring P iff ordinal≥1 or F iff ordinal≥3 would
reject valid durable recovery. Candidate presence, unlike those two gaps, is
exactly ordinal≥2 because its bytes are stored with ordinal2 itself.

Apply the same matrix projection to the already proposed
`WorkRelations::presence` for tag25: reservation=false requires count0 and no
preparation/complete; when reservation=true, count0 permits preparation but no
complete, count1 requires preparation/no complete, count2 requires preparation
and permits complete, count3/4 requires preparation and complete. Thus
step_count≥1 implies preparation, complete implies step_count≥2, and
step_count≥3 implies complete. No candidate field is added to tag25.

This is local inconsistency rejection, not authentication. Grammar must still
compare the actual observed number/ordinal sequence, tag30 exactly at ordinal2,
tag31/32 and33/34 presence, header/work/reservation identities and End digests/
lengths to the original records. Replay retains actual signed predecessor links,
F1/F2 byte equality, live scope, source-origin and phase preconditions. No enum
phase is passed to these helpers and no returned success grants permission.

Tests through both factories and real Codec must include every allowed matrix
row, both recovery-gap positives, and otherwise-consistent single-condition
negatives for missing preparation after step1, candidate with ordinal0/1,
missing candidate from ordinal2 onward, complete before ordinal2, and absent
complete at ordinal3/4. Add the corresponding tag25 projection cases. Preserve
all actual-record/Grammar/replay tests; leaf tests do not replace them.

The current manual Work11/End13 planning counts are not qualification of this
completed matrix. Actual Mago limits remain15/1.6. A per-owner literal list of
its five null pairs is ordinary cohesive endpoint validation; it need not become
a generic relation engine, callback or new helper. Source/quality review must
assess the implementation, not infer a PASS from this observation.

## HAD-03 — Medium — name the existing global timestamp guard owner

Proposal §5 calls its verification path list exact and says no other test path
is needed, but omits `tests/Architecture/AuditRecordArchitectureTest.php`.
That existing allowlisted file is directly affected by Scalar.admitString.

Its `testReplayHelperA2EdgesAndArtifactTimestampOwnersAreExact:1387–1431`
compares the complete actual source-wide timestamp caller inventory to literal
file/method/count entries. `a2DeliveryViolations:4517–4541` and
`a2C3AllowedProductReferences:4547–4625` also retain literal A2 references.
The supplement's generic sentence that collectors should follow the moved edge
does not supply this missing exact test owner and tuple.

**Finite correction:** add this existing test path to the affected verification
list. Its timestamp inventory must contain exactly:

```text
Internal/Audit/Authority/History/AuditHistoryScalar.php => admitString:1
```

Its literal A2 reference row permits AuditRecordTime only for that path; keep
all existing unrelated tuples unchanged. Remove any prior planned NativeGroup
timestamp row/reference rather than allowing both origins. Add alias/FQCN
positive and relocated-method, duplicate-call, sibling-helper and extra-A2-type
negative controls. AuthorityHistoryArchitectureTest additionally fixes the one
Scalar.admitString Core-validation caller and every helper edge. No public API,
F1/F2 helper exception or History namespace allowance follows.

This path is already in allowlist.json's editPaths and existingPhpTestEdits;
the35/26 test inventories and proposed304 edit-path total remain unchanged.

## Unaffected design conclusions and exact inventory

| Review area | Bounded conclusion |
| --- | --- |
| Coherence and methods | Seven absent/new literal paths; counts Scalar5, Context6, Lifecycle2, Closed4, Work4, End2, Publication4 =27 declarations. Constructors are private, zero-argument and have zero Product creation sites. There is no retained state or authority constructor. Context covers local partition/genesis/context relations; lifecycle handles Segment12; proof/binding/version own their separate local forms; work/end/publication retain domain-specific responsibilities. This is a responsibility split, not moving the107 decisions into one differently named class. |
| Callers | NativeGroup.__construct alone calls Scalar.admit and each fixed relation entry. The sole external helper-to-helper edge is Lifecycle.assertValid→Closed.version; Closed.proof also calls its own version. Scalar.admit alone calls its three private typed operations. Exactly the proposed caller table applies; no class-wide or namespace-wide grant. NativeGroup remains9/max2 and Record10/max3 with R4. |
| Complete31 routing | Independently compared all route groups to the frozen sole NativeGroup schema: Context5, lifecycle4, closed3, work3, endpoint1, publication4, scalar-only11 =31 distinct tags, exact set equality. All19 other tags remain rejected. The adopted three-way native tag union and frozen enum declarations are unchanged; no enum source review is repeated. |
| Single schema and primitive result | NativeGroup remains the only key/domain schema. Raw arrays stay broad pre-admission; reference/key checks precede strict Scalar.admit, whose actual primitive result builds a fresh detached array. Unknown/nullable-looking descriptors fail; null is accepted only in its finite admitted domain. All relations finish before SensitiveParameterValue retention. No false scalar annotation substitutes for these checks. Literal schema keys and semantic descriptor constants remain; field-name obfuscation does not. |
| Codec and getter reduction | Two public methods, no schema/schema() or assertScalars duplicate. An admitted immutable NativeGroup is sufficient for encode; precisely its encode→NativeGroup.tag edge is removed, reducing tag consumers4→3. Scalars consumers stay13 and Record consumers remain12/12/10. Capture same-local encode/payload/group and sole Reader.next decode/fromRead remain exact. |
| Flat preflight | The proposed anchored stripped-text expression is equivalent to the existing delimiter exclusions when both original braces and the nonempty/cap/raw-CR/LF checks remain. Original first/last braces cannot be removed by the quoted-string stripper. Exactly one outer pair plus no inner brace/array delimiter/residual quote is preserved. Null preg_replace short-circuits; preg_match must equal1. Nested input therefore cannot reach recursive JCS merely because the later scalar schema would also reject it. This is source-language reasoning, not a PCRE/runtime result. |
| Privacy | Every new data argument is SensitiveParameter. Core timestamp admission lacks that protection itself, so NativeGroup must continue catching and throwing a fresh fixed cause-free exception; helpers must not retain or expose raw Throwables. Codec keeps its separate JCS catch/rethrow boundary. Carrier R4 and no raw previous cause remain unchanged. Sentinel tests with exception args enabled and actual JCS/helper failure routes remain required. |
| Preload and scope | Seven literal helper types added immediately after NativeGroup give18→25 actual additional eager resolutions, with the original two initializer callers and nonempty Normalizer warming unchanged. No load under a floor or class-string-only readiness claim. Exact allowlist297+7=304; references62 and tests35/26 unchanged. Effective closed type inventory27→34 must be reflected where that effective count is stated. Five original verification paths plus the HAD-03 existing path give six named affected paths. No new native run or test class. |
| Quality evidence | Installed Mago schema enables Error at class cyclomatic15, Kan1.6, methods10 and ordinary parameters5. Current config retains strict baseline and only its existing constructor override. Root's preserved lint log actually reports Codec30 and NativeGroup107/Kan4.51. Private-method rearrangement does not establish compliance. The author's pinned Rust-rule analysis and all manual estimates remain planning evidence; no upstream re-fetch or Mago execution was performed here. |

The corrected design preserves the original wire/key/cap sets, no SQL/F2/current
trust/seed/provider/Clock acquisition under a floor, original F1/F2 byte identity,
native ownership and same-local source guards. PublicationEnd's exact accepted
revision case and MAX recovery remain; the helper cannot create a new revision
or establish publication authority. Whole-history, native and release contracts
are not reopened by this review.

## Changed Files, evidence and execution record

Only this report and `E14/history-admission-design-review-r1-inputs.json` were
written. The companion fingerprints the actual relied-on proposal/contracts,
accepted F2/Core source, local Mago configuration/schema/log and architecture
guards, with first/final hashes and the closed inventory checks. Root-owned
concurrent Task/Report/STATE bookkeeping is contextual and excluded from semantic
input stability. The four-file rejected carrier/test freeze remains unchanged.

Review operations were read-only cat, sed, bounded rg/nl/head, date and Python
standard-library hash/JSON/text bookkeeping. Large exploratory output was
narrowed before relying on the complete affected sections. The exact core read
commands and bookkeeping timings are recorded in the companion. The no-hit
search for the seven proposed helpers returned1; that is absence evidence, not
a Product gate. No PHP, Mago, test, Node, Docker, DB, native, network or Git
operation ran. Existing unit/lint diagnostics were inspected only; no test PASS
or current commit/remote verification is claimed by this reviewer.

## Acceptance and Next Action

This affected design review is complete with three finite corrections required.
Root can incorporate HAD-01–03 into an immutable supplemental candidate and ask
for only the changed-rule/owner closure review. No unresolved type, caller,
preload, version-domain or endpoint-matrix choice needs to be delegated to the
worker. Adoption and Product issuance remain Root-owned and are not implied by
this review. The accepted three-enum source unit stays frozen.

残り工程: corrected supplement/adoption and management checkpoint; bounded Luna
carrier/codec/test/union implementation; meaningful tests and strict quality/
architecture review on frozen inputs; later Reader/Writer/preload/cold/native/
SQL/full/export work; complete F5P/F5 and parent release/delivery obligations.
All Product test claims remain unproven here; earlier HL-01/04 remain open.

Next Action: Root freezes the seven-helper amendment with the exact actual-versus-
captured ranges, both local matrices and the named timestamp guard tuple above,
then closes this bounded review before committed implementation issuance.
