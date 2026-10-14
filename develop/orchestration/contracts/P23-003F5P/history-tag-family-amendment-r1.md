# Closed History tag families — finite amendment r1

Status: Candidate for independent review; effective only after Root adoption and committed Task issuance.

The original single50-case enum conflicts with the unchanged enabled Mago
1.42.0 too-many-enum-cases Error threshold20. Exact local lint evidence is
E13/history-layer-r1-runtime/lint-20261014T161942858631/output.log. The lock-pinned
rule source is1e843d2e6e95f186022b6752768f12795fec513a; the finite15/20/15
partition preserves all original values with the minimum three enum declarations.
Author investigation: E14/history-tag-contract-options.md, SHA256
514aeaa0dafe93fcdb70f3c6235510f043102c52fe3ff699321f579328f59ff8.
Root preserves the earlier D179 chronology and records the superseding count in
a new adoption entry. This proposal performs no Product/runtime qualification.

### Exact three-enum vocabulary

All types are in `BlackOps\Internal\Audit\Authority\History`, one type per file,
backed by int. Each contains only its listed case declarations: zero declared
methods, no explicit constants/alias cases, traits, interfaces, attributes or
state. The built-in enum methods are unchanged. The names describe wire families,
not production origin or permission: control capture still uses B2/checkpoint
facts where its existing phase grammar requires them.

`src/Internal/Audit/Authority/History/AuditHistoryTag.php` — existing path, 15:

```text
Header=1 AcceptedAuthorityBytes=2 ExternalAnchor=3 FloorHint=4
InstalledNextPolicy=5 EmptyInstalledSource=6
PublishedSegmentsBegin=48 PublishedSegmentNative=49 PublishedSegmentEnvelope=50
PublishedSegmentsEnd=51 StagedSegmentsBegin=52 StagedSegmentNative=53
StagedSegmentEnvelope=54 StagedSegmentsEnd=55 Trailer=255
```

`src/Internal/Audit/Authority/History/AuditHistoryCheckpointTag.php` — new, 20:

```text
PartitionNative=16 TargetSegmentNative=17 SuccessorSegmentNative=18
PredecessorSegmentNative=19 NoPredecessorSegment=20 PreviousRecordEndpoint=21
NoPreviousRecordEndpoint=22 CurrentOpenSegmentNative=23 OwnedClosedProof=24
CheckpointPresence=25 ReservationNative=26 ReservationEnvelope=27
WorkStepNative=28 WorkStepEnvelope=29 CompleteCandidateBytes=30
PreparationNative=31 PreparedUnsignedBytes=32 CompleteCheckpointNative=33
PersistedCompleteBytes=34 CheckpointEnd=35
```

`src/Internal/Audit/Authority/History/AuditHistoryControlTag.php` — new, 15:

```text
TransitionsBegin=64 TransitionNative=65 TransitionEnvelope=66 TransitionsEnd=67
TransitionAckNative=68 TransitionAckEnvelope=69 NoTransitionAcknowledgement=70
PublicationsBegin=72 PublicationNative=73 PublicationDocument=74
PublicationsEnd=75 CandidatePublicationNative=76 CandidatePublicationDocument=77
NoNextPublication=78 PublicationEnd=79
```

The three sets are disjoint and their union is exactly the prior 50 code/name
pairs. No alias facade remains on the first enum. All former case references
use the owning enum above; wire encoding still writes the unchanged `->value`.

### Exact declaration and Reader amendment

For the declarations below, T means the literal native PHP type
`AuditHistoryTag|AuditHistoryCheckpointTag|AuditHistoryControlTag`, not a Product
alias, interface, docblock-only type or new file. Expand that exact union in
each signature and each stored-shape annotation. No parameter count changes.

| Owner | Complete affected declarations | Retained budget |
| --- | --- | --- |
| AuditHistoryNativeGroup | private `__construct(T $tag,array $scalars)`; static `fromNative(T $tag,array $scalars):self`; static `fromDecoded(T $tag,array $scalars):self`; `tag():T`. Both snapshot annotations use `array{tag:T,scalars:array<string,scalar|null>}`. | Exactly9 methods/max2 args; scalars and R4 unchanged. |
| AuditHistoryRecord | private `__construct(T $tag,string $payload,?AuditHistoryNativeGroup $native)`; static `fromCapture` and `fromRead` with the same3 parameters returning self; `tag():T`. Its three snapshot annotations use `array{tag:T,payload:string,native:?AuditHistoryNativeGroup}`. | Exactly10/max3; bytes/native and R4 unchanged. |
| AuditHistoryGroupCodec | `decode(T $tag,string $bytes):AuditHistoryNativeGroup`; existing private `schema(T $tag):array`. encode and assertScalars unchanged; all existing sensitive-parameter attributes preserved. | Existing <=8/max2; no extra helper. |
| Future Reader/Writer/Grammar and semantic owners | Reader.next still returns `?AuditHistoryRecord`; Writer.append and Grammar.accept still take Record. No new tag-taking method is granted. Existing finite local tag variables infer T; any already-issued private helper carrying a tag must spell T rather than int/BackedEnum. | Reader exactly10, other existing ceilings unchanged. |

These cover every explicit `AuditHistoryTag` declaration in the current carrier
files and frozen packet (history-map:400, type-closure:34/42/43, HT-correction:17–25).
The future Reader, prerequisite, HistoryGrammarTest and History architecture
test are absent in C at investigation time; their requirements below are future
implementation scope, not findings from an executed implementation.

Only `AuditHistoryReader::next` may convert an integer to any of the three enums.
Its fixed order remains: read the complete five-byte prefix; admit the known
uint8 code and uint32 length against the exact closed cap/zero/trailer rules,
checked counters/offsets and available framing; then convert the admitted code;
then read the original payload in <=16384 chunks. The native conversion is one
fixed match expression with these three arms (the reject arm uses Reader's
existing fixed integrity-failure path, not a new helper or exception protocol):

```text
1,2,3,4,5,6,48,49,50,51,52,53,54,55,255 => AuditHistoryTag::from($wireTag)
16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35
    => AuditHistoryCheckpointTag::from($wireTag)
64,65,66,67,68,69,70,72,73,74,75,76,77,78,79
    => AuditHistoryControlTag::from($wireTag)
default => fixed rejection
```

The three native `from` source sites replace the prior sole enum target; they
do not add a second producer method. No `tryFrom` chain, dynamic class name,
callable, class-string map, reflection, catch-and-fallback or range-to-permission
dispatch is admitted. Unknown codes cannot reach any conversion or payload read.
After reading, the existing scalar set alone calls GroupCodec.decode once and
passes that same enum object, original payload and returned group to fromRead.
Other tags pass null. Native tag/group identity remains `$native->tag() === $tag`,
not integer-only comparison. Writer.finish remains common `Trailer`/48 bytes/null.

Scalar set31, empty set5, byte set13 and trailer1 remain unchanged. NativeGroup
still rejects all non-scalar tags. The exact factories/getters and same-local
encode/decode bindings in HT-correction:44–82 remain: NativeGroup.tag has4 consumers,
scalars13; Record.tag/bytes12 each, native10. All fact/loan R4 controls, source guards,
complete trailer/count/hash/physical EOF, repeated bounded reads, original F1/F2
bytes and live scope/capture/endpoint identity survive. Pure enum tags remain
non-authority data; their native representation does not replace fact R4 controls.

### Literal source/caller and preload inventory

In addition to the three enum paths and three carrier/codec paths above, the
following already-allowlisted Product paths receive only their relevant case
qualifiers, inferred/declared tag union or literal preload inventory. All History
names below expand to `src/Internal/Audit/Authority/History/<name>.php`:

- AuditHistoryReader: next's three guarded native-from sites and existing tag comparisons; checkComplete's Trailer comparison.
- AuditHistoryWriter: append's unchanged value encoding; finish's unchanged common Trailer case.
- AuditHistoryGrammar: accept and existing finite grammar-helper case comparisons.
- AuditCheckpointCaptureWriter and AuditControlCaptureWriter: capture's fixed literal cases and unchanged same-local factory flows.
- AuditCheckpointNativeGroups: partition/segment/endpoint/preparation/complete/proof; AuditWorkNativeGroups: reservation/step/presence/end; AuditControlNativeGroups: segment/transition/acknowledgement/publication/endpoint. Their existing issued case choices move namespaces only.
- AuditWorkHistoryReplay::verify; AuditSegmentHistoryReplay::{published,staged}; AuditTransitionHistoryReplay::verify; AuditPublicationHistoryReplay::verify; AuditCheckpointHistoryReplay::replay; AuditControlHistoryReplay::replay. No new getter permission for helpers.
- `src/Internal/Audit/Provenance/AuditCheckpointMutationAdmission.php::assertUnchanged` and `src/Internal/Audit/Provenance/PostgreSqlAuditControlPublicationWriter.php::recheck`: exact existing Record-tag comparisons.
- AuditCheckpointReplayRuntimePrerequisite::initialize: retain AuditHistoryTag and add the two new FQCN literals, adjacent to it, through the same actual eager class-resolution mechanism before either floor.

This is23 Product paths total:21 already in allowlist plus2 new paths. It is a
complete affected inventory for issuance, not permission to edit all files now.
Do not alter scopes, phase enums, authority constructors, SQL, native descriptors,
F1 sites, the two application composition callers or unrelated signatures.

Preload's additional History/authority set grows exactly16→18. In spec130§9.2,
append the two new FQCNs immediately after AuditHistoryTag and change that finite
count. Keep the original15 existing types,3 replay/fact types, separately built
Preflight, failure types and nonempty Normalizer FORM_C warming unchanged.
The prerequisite still has only constructor/initialize and two production callers:
ApplicationRetentionRuntime.signingTasks and ApplicationAuditControlInstallationComposer.compose.
Both new enums must actually resolve before any floor-capable construction/use;
`::class` alone is insufficient. No resolution call is permitted in Reader or
another floor-held path. Extend only the existing resolved-AST preload exception.

### Exact spec130 and D179 delta

Apply this literal change to spec130 section9.2; all surrounding statements stay
unchanged:

```diff
-The same initializer additionally resolves these16 exact history/authority types
+The same initializer additionally resolves these18 exact history/authority types
 before either floor, since their first construction can occur during replay:
@@
 BlackOps\Internal\Audit\Authority\History\AuditHistoryTag
+BlackOps\Internal\Audit\Authority\History\AuditHistoryCheckpointTag
+BlackOps\Internal\Audit\Authority\History\AuditHistoryControlTag
 BlackOps\Internal\Audit\Authority\History\AuditHistoryRecord
```

Preserve D179's earlier adoption text as historical. Append this current decision
entry, explicitly superseding its previous16-type resolution count:

```markdown
## Closed history tag family refinement — 2026-10-14

The unchanged 49 nonterminal wire tags and terminal255 use exactly three
backed-int enums: AuditHistoryTag15, AuditHistoryCheckpointTag20 and
AuditHistoryControlTag15. Each has zero declared methods. This explicitly
replaces the single50-case enum surface: the existing Mago rule limits each
enum to20, and its threshold, enabled state, baseline and suppressions remain
unchanged. The finite case partition and literal paths are fixed in the child
history-tag-family amendment; no alias facade or open tag interface is added.

NativeGroup, Record and GroupCodec use the explicit three-enum union. Only
HistoryReader.next invokes native from after complete prefix, known-code and
tag-specific length admission. Exact enum-case identity, same-local original
payload/group binding, complete grammar/count/hash/physical EOF and all source
caller/representation/method budgets remain. A tag family grants no production
classification, current authority or phase permission.

The existing initializer eagerly resolves the two added enums before either
floor, making its additional history/authority list18. Its two composition
callers, original F1 closure and nonempty Normalizer warmup are unchanged.
This changes no wire bytes, history bound, F1/F2/F3 field, trust or release
boundary. Product, cold/native/SQL and strict-quality evidence remain required;
this contract correction is not Product Acceptance or release proof.
```

### Guard and test obligations, without new test paths

The supplement must assign these cases to the already named owners; it must not
derive expected case maps from the candidate enum source or lower any prior gate.

| Existing packet owner | Positive and discriminating negative additions |
| --- | --- |
| tests/Architecture/AuditAuthorityHistoryArchitectureTest.php | Literal file/type/50 triple `(FQCN,case,code)` maps; counts15/20/15 and zero declared methods; exact union at all10 affected signatures plus storage annotations; alias/group-import and inert-comment positives. Reject omitted/extra/moved case, duplicate code across enums, reserved71, changed value/name, extra alias constant/method/trait/interface/type, broad int/BackedEnum/object signature, extra constructor/from/tryFrom/dynamic call, conversion before prefix/legality/cap admission, changed dispatch family, integer-only identity, or any changed same-local/consumer/R4/budget restriction. |
| tests/Internal/Audit/Authority/History/AuditHistoryGrammarTest.php | Literal 50-code coverage and existing15 legal phase grammars; ensure Reader returns each exact owning enum case. Exercise gaps0/7/15/36/47/56/63/71/80/254, short prefix, zero/nonzero and exact/over-cap boundaries, wrong trailer length, physical suffix/truncation/count/hash, and altered family cases through real Reader/grammar. No successful result from a mere enum conversion. |
| tests/Internal/Audit/Authority/History/AuditHistoryGroupCodecTest.php | Update all former literal owners and finite union annotations. Retain31 native-group shapes and reject19 non-scalar tags as groups; missing/foreign projection and original payload/group identity tests. Include representative common/checkpoint/control positive records and invalid same-value foreign enum (runtime type rejection), while preserving the independent worker's HL-01..04 cases. |
| tests/Fixtures/Audit/AuthorityFilesystemCase.php and existing cold cases | Pin both new enum files/FQCNs in the finite expected load closure and hashes. Both actual maintenance/installation composition positives load all18 before floor; omitted or late loading of either added enum fails. Existing missing/late/empty warmup controls and58 native run IDs/counts remain. |
| tests/Architecture/AuditArtifactArchitectureTest.php | Update only any literal initializer type/site inventory affected by the existing non-invocation resolution exception; preserve the exact three F1 replay call sites and reject a new dynamic/floor-held/extra resolution. No broad History namespace permission. |
| tests/Consumer/framework-package-export.sh and tests/Consumer/framework-package-required-inventory.sh | Add both exact enum paths to the final packet-required source inventory, deliberately update its final count and pinned export-script hash, retain missing/extra path negatives. The current older script count215 is not the final complete-packet inventory. |

The two new enums need no separate test class: retain35 new/26 existing test paths,
3986 predecessor IDs,58 native cases and the existing CI partitions/quality identity.
Source Review and the relevant History format/lint/analyze/architecture gate must
measure the actual implementation, including Reader's added match arms, under
unchanged complexity15, method10, ordinary-argument5 and PHP512M limits. This
report establishes no complexity PASS. If the exact implementation cannot fit,
return a separate concrete budget conflict; no helper/count waiver is implicit.
Root selects and leases the meaningful runtime cases after the source is frozen.
Existing full/cold/native/SQL/export/release obligations remain at their original
phase; no full gate is requested merely for adopting this management supplement.

### Exact durable amendment and ownership

Preserve all original copied evidence and existing immutable manifest entries.
Root should create `develop/orchestration/contracts/P23-003F5P/history-tag-family-amendment-r1.md`
with this finite vocabulary, declaration/caller/preload/verification delta;
independently review it; and append its hash/byte receipt to manifest.json.
README receives one explicit precedence item overriding only type-closure r1:34,
42/43 and§3/§4's single-tag type/count; history-map:400's decode type;
HT-correction:17–25/31/94's enum type/count; and corresponding old literal
inventories. Their original text/hashes stay immutable. No historical review is
rewritten as approval of this new representation.

`allowlist.json` adds only the two exact new Product paths: editPaths295→297;
referenceOnly62, newPhpTests35 and existingPhpTestEdits26 remain unchanged.
Effective type-closure inventory25→27; the original historical25-entry input
file is unchanged. README's effective counts, current Task/Report, spec130§9.2
and a new D179 decision entry receive the explicit adopted18-type delta.
Root owns those management edits, STATE, review/adoption, committed checkpoint
and fresh bounded worker issuance. Current four-path Luna correction remains
separate; do not merge it into this unadopted proposal or edit its source here.

Public release tuple/capabilities/wire format/commands are unchanged by this
internal representation correction. Inspect existing internal explanation for
literal preload/type counts when adopting; no guide/API change or release claim
is needed for the enum split itself. Full child documentation/release obligations
remain binding.

No enum partition, native union, conversion caller or preload identity choice
is left to implementation. Actual complexity and runtime behavior are unmeasured
and remain verification obligations, not permission to alter the declared budget.
