# D175: Audit Checkpoint Persistence

Status: Decided — persistence-only implementation Accepted and committed; BlackOps 1.3 unreleased
Date: 2026-10-07

## Context and decision

Accepted F1 provides pure canonical/signature primitives. The next useful seam
must establish complete database provenance and crash-stable durable identity,
without inventing the still-unissued ordinary-sign authorization or trusted
control exemption. Root adopts R1–R8 and the three closed r2 findings in
[spec125](../spec/125-audit-checkpoint-persistence.md); its exact contract governs
[F2](../orchestration/tasks/P23-003F2-audit-checkpoint-persistence.md).

Preparation commits immutable UUIDv7/body/time/key fingerprint inputs; persistence
re-proves the complete segment and stores exactly matching verified complete bytes.
Both own standalone REPEATABLE READ roots. An RR-invisible winner/serialization
failure returns safe StorageUnavailable and requires explicit fresh-root recovery;
it does not prove a visible conflict. Opaque protection failures map uniformly
to ResourceUnavailable. Closed versions/neighbor chronology preserve B2 invariants.

sealedAt denotes B2 closed_at; signedAt denotes the one trusted preparation time.
These meanings preserve byte-identical physical signing retries. Later F5 owns
the actual seal after durable anchor acceptance and current trust/freshness checks.
F2 has no complete-byte release method, signer, private seed, runtime activation
or control exemption. Mandatory later authorization/provenance remains unresolved
by F2; R9's suggested wire is not adopted as executable authority.

## Issuance choices and consequences

Root reserves migrations/postgresql/Version20261001000000.php after the actual
absence/reservation scan. It adds two tables to 13 existing Framework migrations;
the Community journey has 5 Application migrations. The arithmetic becomes
14 Framework/19 combined, subject to actual tests rather than assumed success.
The reviewed preparatory 20 paths require two additional existing tests whose
current exact 13/14 migration expectations depend on that same inventory:
DatabaseMigrationCommandTest and ApplicationConsoleKernelTest. Root explicitly
adopts only those count-support amendments, giving 22 paths. No Product API expands.

Actual Mago help confirms positional paths override configured source paths.
The Task retains both configured source roots and adds the owned migration for
lint/baseline/analyze, and adds it to src/tests formatting. No threshold, baseline,
configuration, global CI scope or dependency changes. Four-type complexity limits
remain; any concrete inability requires a bounded amendment before new helpers.

Accepted inputs are captured in /tmp/blackops-f2-20261007. The existing 3833-case
full result is a predecessor baseline with equal input maps, not F2 evidence.
Implementation/source/native/migration/Consumer/static/full/privacy/package
verification and independent review are still required. The source diff will be
integrated as its own reviewed commit onto the clean remote checkpoint ecb8288;
primary mixed work and its index, published Reel and separate D5P are preserved.

## Authority evidence

Root accepted the bounded47-path implementation on2026-10-09 after final evidence
review2ccd882b and fixed-input full3915/8591759, including native18/524. The
[F2 Report](../orchestration/reports/P23-003F2-audit-checkpoint-persistence.md)
records actual gates, failed iterations, inherited diagnostics and evidence-loss
limits. Source9768edf4 and its clean committed export passed; checkpointcbec640
was pushed and its exact remote SHA verified. This
Acceptance grants no signer, release, seal, activation or R9 authority and does
not declare1.3 released.

Preparatory draft fc9ec769, Root adoption 19bd1221 and Green r2 review 353356ae
are retained under /tmp/blackops-1-3-release. The current issuance inventory
e886f0a4/059beabd establishes scope/migration/guard/package inputs; the Task and
Report record the permanent issuance delta and subsequent results. No unchanged
F1 mathematical authority is reopened. BlackOps 1.3 remains unreleased.

## Measured decomposition amendment — 2026-10-07

The initial four-file implementation failed actual lint (class cyclomatic
174/190/36 against15) and analyze (26 errors); independent source review found
TX1–TX10. Root adopts the concrete replacement in review25047d73 and its Green
parameter addendum143b715e as spec125 §11. Original proposal9053f70c is not adopted
unchanged. Eleven named internal helpers expand the original22 to33 paths
(23new/10existing), with functional19/proof10/guard4 ownership. Before-write
absence8e71513b is preserved and scope-v2.json adds exactly those paths.

The amendment fixes the complete caller/constructor graph, row validation and
monotonic once-close transaction quarantine. Sole record unprotection moves from
Reader to RecordProof; record has five parameters and verifyPersistence takes
the authoritative reloaded Preparation plus three inputs. Four entry surfaces,
two tables, DDL, privacy, P1–P12 and all unchanged quality rules remain mandatory.
The old four-type decomposition limit is superseded only by this exact inventory.
Further types/splits, callback dispatch, baselines, thresholds or suppressions are
not authorized. Package178 is arithmetic until measured. Source corrections and
actual gates must prove the division works; no Product Acceptance is inferred.

## In-place validation refinement — 2026-10-08

Measured r3 lintc2993a55 still fails11 errors/9 warnings; error diagnostic
aa341512 passes. Root adopts only the first existing-file pass in plan80d1716e,
as narrowed by independent review1f2359bf and recorded in spec125 §11. Runtime
postconditions may remove duplicate downstream shape checks; cross-value graph,
identity, chronology, version, null-hash, raw row, trust and transaction checks
remain mandatory. Scope stays33 and Product inventory15. The conditional new-type
proposal is not adopted, and no quality limit changes. This refinement is not a
quality or native Acceptance claim. Native r7 actually16/18 passes; two fixture
failures, privacy/guard corrections, final gates and separate integration remain.

## Fixed validation tables — 2026-10-08

Root adopts the exact four-file/nine-private-constant first pass in independent
reviewb07e1c57, now specified in spec125 §11. Current r4 lint has9 errors/8 warnings;
the fixed grammar/equality tables remove repetitive raw scalar checks while
retaining all semantic and effectful ordering. Existing integer leaves and the
fifteen-type graph remain. This is a smaller justified change before considering
new types. The broader plan cd95ccf2 is not adopted as a whole, and its helper
estimates confer no quality result. The proposed standalone AbortDecision type
is rejected as unnecessary. Any later method/type extraction needs its exact
reviewed ownership map; thresholds/baselines and P1–P12 remain unchanged.

The corrected binding unit actually passes1/21 on unchanged Product15. The two
changed architecture classes reach28/35108 with4 remaining failures; these are
retained as failed evidence and assigned to the guard worker. No F2 Acceptance
or commit is implied. Previously accepted work remains separately committed/pushed.

## Workflow and policy extraction — 2026-10-08

Table-pass execution01e8e6a passes unit35/497 and analyzer errors but leaves
lint11errors/7warnings and3 selected guard failures. Root adopts the exact
main proposal2fddcc9b as spec125 §12: one PreparationWorkflow, one stateless
TransactionPolicy, and Reader's private result assembly. This adds only two
absent paths, giving17 Product/35 Task paths. A second persistence workflow and
the optional RecordProof method are not adopted. Store.persist stays unchanged;
Transaction retains every completion/quarantine effect and phase/once-close
state. Root reviewed exact dependency/constructor/caller/parameter maps and
marker evaluation ordering before issuance. It is bounded implementation
authority, not an estimate-based quality waiver or Acceptance.

## Shared validation and proof boundaries — 2026-10-08

Actual r6 passes35 units/497 assertions and analyzer-error diagnostics; lint
remains10 errors/6 warnings. Six oversized validators account for most remaining
decisions; Transaction16 and Reader difficulty12.5 remain a separate bounded
correction. Root adopts cc5df7f2 as spec125 §13 after reviewing the six-class,
twelve-helper exact graph and concrete decoder provenance in38334753. This
centralizes identical integer/byte/binding operations and separates raw segment,
neighbor/endpoint, protected record and bounded page responsibilities. It removes
only proven duplicate predicates and explicitly qualified pure comparisons.
Raw ingress, cross-value checks, error ordering, current trust and transaction
semantics remain mandatory. Scope47 and Product29 are bounded; no metric waiver
or further extraction is implied. Pre-write absence and scope-v4 preserve the
primary mixed buffers and clean Accepted integration. Measurements and meaningful
P1–P12 verification remain necessary before Acceptance or a separate F2 commit.

## Existing failure-path consolidation — 2026-10-08

Root adopts read-only reviewcd207273 as spec125 §14 after confirming the exact
reachable-state invariant and existing Reader mapper. Transaction combines only
the three terminal/unadmitted abort phases through its idempotent closeOnce;
Reader sends every caught operation failure through its already-complete mapper.
No type, method, signature, policy, owner count, effectful ordering or quality
threshold changes. This addresses actual r6 Transaction16/Reader12.5 without
another helper. Source29/Task47 remain. Actual formatted metrics and unchanged
semantic cases, not the source estimates, determine gate success.

## Final grammar clarification — 2026-10-08

Actual r7 passes unit35/497 and package192/167 but leaves StateParserCC16 and
Reader difficulty12.14. Source review29cbb026 qualifies two bounded changes now
adopted as spec125 §15: one shared non-null head guard and a private Reader
identifier grammar helper at the original pre-try position. This preserves
all error/effect ordering, adds no type/public surface, and keeps exact S and
own-call maps. Recognized multiline PHPDoc restores already-specified shapes;
redundant readonly syntax removal leaves effective state unchanged. Root grants
no metric waiver or further extraction. Current source/handoffs and actual
quality, Consumer/full and Acceptance remain separate stages.


## Adopted later-F authority refinement — 2026-10-12

[D179](179-audit-signing-authority-and-production-provenance.md) and specifications129–132 provide the most-specific
adopted online/current-historical provenance and real ordinary-sign contracts.
The preliminary R9 actor choice becomes the same-envelope principal/execution
aggregate; its target and request-ref algorithm remain. Actual Console identity
grants preparation before exact-target evaluation. F2 Acceptance and its APIs,
Clock/UUID/replay/fingerprint/root ownership do not change. The real reservation,
F2-slot projection,candidate/outcome,release and seal work still need exact issuance
and native proof; they are not retroactively covered by F2's accepted results.
