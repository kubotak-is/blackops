# History type-flow finite contract review r1

Status: **Affected contract Green**. No new blocking finding in this candidate.
Recorded: 2026-10-16T00:56:06+09:00. Reviewer: independent Transaction/Security.
This is a contract verdict; Product source Acceptance and all pending gates remain open.

## Summary and authority

The proposed change is sufficiently bounded: replace only NativeGroup's literal
match with one literal switch, using truthful case-local closed-row annotations
after complete scalar admission. It preserves the actual admitted array, all15
fixed relation call sites,20 relation-bearing tags,11 scalar-only tags, and the
existing schema and helper ownership. It addresses the type-flow limitation shown
by the installed analyzer without asserting unvalidated input or changing runtime
domains.

P is /home/kubotak/projects/blackops. C is P/.phpunit.cache/f2-orchestration/accepted-integration.
E is P/.phpunit.cache/f5-orchestration/20261015. Source paths below are relative to C;
History denotes src/Internal/Audit/Authority/History. Candidate line references
are to E/history-type-flow-candidate/history-type-flow-amendment-r1.md.

Root reports adopted management authority commit
6b9f4f82ad04fc4e957c65deb5974247f26c7f05. This reviewer did not run Git.
The original admission amendment's current bytes match its adopted hash.

| Selected authority/evidence | SHA-256 |
| --- | --- |
| Candidate amendment | 94a7e3735263924cabe17b87edd601b80082e46b4c0778409c7279ff8bf258c0 |
| Candidate syntax probe capsule | cfed93fc1b41f441854d9f69ebcb46f4c8c8d28e30ba6b321e9a2fbc5ba9a2c5 |
| Original candidate manifest | 217c7a936a270786d656c45ec95172743f81f03a3459af6a2035a5f27f6df102 |
| Supplementary budget probe capsule | 03e9e8fd44371802fc64c9ac939922271bfd9845a2271d0bdb1236161be5ede9 |
| Adopted admission amendment | 0c52ab51e8ae3272d49c040387de6727c0b9af02d534ee21c41974b4069aa3c9 |
| Current NativeGroup reference | 3f930525eb84265a9274c122023e8b9f0dc955e539c56d3173d4feb317637f6a |

The supplementary budget capsule was supplied separately during this review.
It does not modify the original candidate manifest or either original candidate
file. Root must retain it alongside the amendment, syntax probe and this review
when recording adoption. All29 selected semantic inputs remained byte-identical
before/after; Task and STATE context were separately pinned and also unchanged.

## Findings and finite reasoning

No new P1/P2/P3 contract finding. The following evidence supports this bounded
Green; it is not a review or acceptance of an implemented switch successor.

**The dispatch remains exact.** Candidate25–55 preserves fixed calls on the
actual native-int-backed enum union. All three current enum declarations are
int-backed and their50 values are disjoint. NativeGroup:282–305 first looks up
the sole schema and rejects non-native tags, lists, non-string/reference keys
and an inexact key set. Consequently PHP switch's loose comparison introduces
no scalar conversion for this subject and literal integer labels.

The following15 current calls at NativeGroup:315–329 are exactly the candidate's
required case groups. Each row's closed key set and primitive/nullability types
were compared against every applicable SCHEMAS row and actual helper @param.

| Exact tags | Required static target | Closed members |
| --- | --- | --- |
| 1 | ContextRelations.header | 9 |
| 3 | ContextRelations.externalAnchor | 13 |
| 16 | ContextRelations.partition | 8 |
| 21 | ContextRelations.recordEndpoint | 4 |
| 52 | ContextRelations.stagedBegin | 5 |
| 17,18,19,23 | SegmentLifecycle.assertValid | 12 |
| 24 | ClosedSegmentRelations.proof | 14 |
| 49,53 | ClosedSegmentRelations.binding | 9 |
| 25 | WorkRelations.presence | 4 |
| 26 | WorkRelations.reservation | 14 |
| 28 | WorkRelations.step | 8 |
| 35 | CheckpointEndRelations.assertValid | 12 |
| 65 | PublicationRelations.transition | 8 |
| 73,76 | PublicationRelations.publication | 12 |
| 79 | PublicationRelations.end | 7 |

Each class above has the AuditHistory prefix. Scalar-only tags are exactly
5,31,33,48,51,55,64,67,68,72,75. All19 other enum values fail the schema lookup;
the default is therefore only the one bare break for these11 admitted groups.
Adjacent empty literal cases may share a body, but each nonempty body has only
one corresponding call on the same admitted local followed by an unconditional
bare break. No dynamic dispatch, duplicate case, fallthrough or default side
effect is permitted.

**The shape annotations have a runtime proof.** NativeGroup:308–312 constructs
a fresh local from the actual Scalar.admit return for every schema field before
dispatch. Scalar:93–167 admits only strict bool/int/string/null values and finite
nullable/domain descriptors; the per-case annotation describes this completed
result. It does not replace a key/domain check. The actual helper rows are at
Context:14/33/46/62/72, Lifecycle:14, Closed:15/39, Work:14/39/49,
CheckpointEnd:14 and Publication:14/27/46.

The Segment12 group has identical primitive shapes even though tag18 permits
open/closed/sealed,17/19 closed/sealed and23 open; all four state entries truthfully
have primitive type string. Bindings49/53 and publications73/76 also have identical
shapes. All other mappings match independently. There is no need to project a
row, add a schema, create a helper/typed alias, broaden a helper @param, or assert
all incompatible rows at once. Candidate35–64 specifically prohibits those
shortcuts and forbids asserting relation/replay facts before their owners run.
The same admitted array is retained only after the switch. Local type truth does
not establish source origin, signed content, phase authorization or replay authority.

**The measured problem matches the proposed boundary.** The retained actual
diagnostic-r2 analyze log has exactly15 possibly-invalid-argument errors, at
lines4684,4696,4708,4720,4732,4744,4756,4768,4780,4792,4804,4816,4828,4840,4852.
Their callee set is exactly the15 entries above. The log hash matches its receipt:
b80d8fccf98ad2f30d95f4805a19d1082f386ed0707a29f8fb436b035b0cba59.
That failed run took2.042244s. It does not show an admitted-invalid-group defect.

The syntax capsule's five embedded sources/logs match all recorded hashes.
Baseline and three tested inline match annotation forms each fail with two
argument errors; statement annotations in switch cases pass with no analyzer
issues, exit0 in0.091831s. The installed binary independently hashes to
e9fceba0d32f03f606c16440f0aa4274b6bbbf1c0ad76a98022a901d48cf3962,
matching both capsules. This supports the selected syntax on installed1.42.0;
it does not claim every conceivable match syntax is impossible or that the
final15 Product errors have already disappeared.

**Quality limits are preserved.** A possible switch-case budget concern was
checked against Root's supplementary retained evidence. Its one-class/one-method
literal20-case switch plus default exits0 in0.020406461s, with only a filename
warning. The corresponding20-if positive violation control exits1 in0.014031086s
with actual class cyclomatic20 above15. Embedded source/config/log bytes and
receipt hashes all agree. C/mago.toml has no cyclomatic override. Thus the selected
dispatch form is not inherently disqualified by the20 labels in this installed
linter. These synthetic measurements do not qualify the complete NativeGroup
class or its final Kan/lint/analyzer outcome.

Candidate66–72 preserves seven helpers/27 methods, NativeGroup9/max2,
Codec2/max2, Record10/max3, sensitive-input/caller/R4 rules and strict quality
limits/baseline. It explicitly leaves the measured Scalar22/Lifecycle17 failures
for separate correction. The adopted class cyclomatic15, Kan1.6, methods10 and
ordinary-parameter5 limits remain. No dependency/configuration change, suppression,
namespace waiver, extra schema or helper follows.

**The successor AST obligation is meaningful and finite.** Candidate76–92
requires the installed Switch_/Case_/Break_ forms and both positive and mutation
controls. Installed PhpParser sources confirm Case_.cond is null for default,
Case_.stmts is the statement list, and Break_.num is null for a bare break.
Grouped literal labels therefore require empty intermediate Case_ statement
lists; a relation body must have the one call expression and bare Break_, with
its exact closed annotation attached before the call. The default has exactly
one bare Break_. The guard must check the actual subject, exact tag/callee sets,
same admitted variable and ordering relative to completed admission and retention.

The required positives include the real constructor and permitted grouped cases.
The required negatives remove/relocate annotations, change case/callee/argument,
remove a break or introduce fallthrough/default effects, retain early or add
arbitrary/dynamic statements. Candidate89–92 also requires the annotation to
match the applicable schema/helper shape; a syntactically present but incorrect
shape is not proof. Existing effective preflight, same-decoded-source/lifetime,
helper/Core caller and R4 obligations remain. HSR3-01's match-default representation
becomes historical only upon this adopted replacement; the successor switch
default/break behavior still needs source review and actual architecture tests.
HSR3-02..05 remain open independently.

**Precedence and publication boundaries remain narrow.** Only the original
literal-match representation and its syntax-specific guard are superseded.
The original50 retained records and six admitted records remain immutable; the
candidate adds evidence, not retrospective changes to them. Counts remain
304 edit/62 reference paths,34 effective types,25 additional preload resolutions,
35 new/26 existing test paths and58 native runs. The prior HAD-01..03 contract
Green is reused; version domains, recovery matrices, timestamp owner and
publication semantics are not reopened or changed. Candidate98–111 preserves
actual Reader/capture/replay/custody/preload/cold/native/SQL/full512M/export and
parent release/Owner requirements. Internal unreleased representation changes
do not establish new public capability, documentation delivery or publication.

## Commands and verification status

Only read/search and Python standard-library hash/text/JSON bookkeeping were
executed by this reviewer, always from P. The adjacent inputs JSON contains the
literal commands, tool-reported duration/exit,29 before/after hashes, separate
context hashes, full15-row comparison and retained-probe digest results.
The first AGENTS/STATE/manifest reads and bounded candidate keyword search
preceded the initial pin; Root supplied the frozen candidate hashes beforehand,
both matched, and no semantic verdict preceded that pin.

All review commands completed exit0. One diagnostic text read was truncated;
a subsequent bounded extraction verified the complete15-error callee set.
The finite schema comparison and capsule verification took0.007218s of Python
bookkeeping time; final selected-input hashing took0.024803s. Retained Mago
durations above belong to Root's earlier probes/runs and are not reviewer runtime.
No Product test, Mago, Docker, DB, Git or other runtime command was run here.
No explicit model/profile denial or fallback was reported; metadata availability
was not treated as a blocker.

Only these two assigned files were written:

- E/history-type-flow-contract-review-r1.md
- E/history-type-flow-contract-review-r1-inputs.json

## Remaining work and Next Action

Root can adopt this narrow contract after preserving the supplemental budget
capsule and recording finite precedence in the authorized management unit.
Root owns its management inventory/checks, commit and subsequent worker issuance.
This review itself authorizes no Product edit or source Acceptance.

Remaining work: implement and independently review the actual switch/annotations
and discriminating AST successor; close HSR3-02..05, existing coverage and measured
quality findings; freeze final source and run the assigned meaningful
unit/architecture/format/lint/analyze/Deptrac/identifier gates. Reuse only successful
unchanged-input evidence. No final NativeGroup, leaf, full-child or release PASS
is given. The actual Reader/custody/preload/native/SQL/full/export/publication and
higher-level Owner gates remain mandatory.

Next Action: Root records/adopts the exact reviewed amendment and all retained
evidence, commits the management unit, then issues only the finite constructor
and existing architecture-guard correction under the unchanged source ownership.
