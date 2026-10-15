# Attributed checkpoint production contract

Status: Complete reviewed contract; worker issuance requires Root’s committed Task.
The [Task](../../tasks/P23-003F5P-attributed-checkpoint-production.md) owns current
issuance, scope, acceptance and resource assignments. These retained documents
are contract inputs, never Product/runtime results. Root must commit the complete
packet before issuing the configured worker.

`manifest.json` pins immutable copied evidence and the final Root integration
files. Four retained records (the final authority review, its input record, the
reviewed unified diff and the admission correction review) use lossless JSON-UTF8
envelopes so format-required spaces
and terminal blank lines remain exact without becoming repository whitespace
errors. Decode `content` as UTF-8 and verify originalBytes/originalSha256; manifest
originalName maps their historical filenames. All other originals are plain exact
copies. This packaging changes no reviewed content.
`allowlist.json` supplies304 exact candidate edit paths and62 reference-only
paths; it separately pins35 new and26 existing PHP test paths. No containing
directory, namespace, mentioned type or reference-only input grants an edit.

All source paths are relative to the accepted integration checkout C:
`.phpunit.cache/f2-orchestration/accepted-integration`, branch
`agent/p23-006-accepted-integration`. Commands start from repository root P at
`/home/kubotak/projects/blackops`. Original E3/E5/E13 and selected-input paths
retain their original repository-root meaning. Their historical status/base text
is preserved verbatim. A frozen candidate's “unissued” or old method count is
interpreted through only the finite corrections below, not a latest-text rule.
The current Task and adopted specifications129–132/D179 remain authoritative.

## Reading order and finite replacements

| Contract input | Binding |
| --- | --- |
| [Composition](checkpoint-issuance-map-r1.md) | Actual scheduler/ordinary/C3/F2 composition, selectors,29 DDL statements and8 SQL blocks. Includes the reviewed shared-graph delivery-failure correction. |
| [History](history-capture-issuance-map-r1.md) | Complete49 nonterminal tags+255,15 phases, native RR READ ONLY capture, bounded-memory replay and fenced exact reread. |
| [Pure replay r1](pure-checkpoint-codec-floor-delta-r1.md), [r2](pure-checkpoint-codec-floor-delta-r2.md) | Original-byte F1 data admission, three exact codec calls, raw bounds, runtime initialization and purpose-specific native holders. r2 resolves only its named r1 joins/surfaces. |
| [Installation](installation-publication-issuance-r1.md), [correction](installation-publication-correction-r2.md) | Actual lazy Console/lifecycle publication, original selected archive digest, exact pending/accepted recovery; one Candidate.native selection caller,Registry9 and HistoryReplay.replay correction. |
| [Online/delegate](online-delegate-checkpoint-issuance-r1.md), [correction](online-delegate-checkpoint-correction-r2.md) | Whole current trust, seed/delegate custody, exact phase7 root comparison and one-use authority; CIR-01/02 and PI-04 corrections below. |
| [Type closure](checkpoint-history-type-closure-r1.md), [HT correction](checkpoint-history-type-correction-r2.md), [caller correction](checkpoint-control-cleanup-caller-correction-r3.md) |25 literal type surfaces, local Readers, same-capture loans and finite25 additional resolutions, plus the exact Record native projection and failed-owner disposal amendment. |
| [Resource contract](checkpoint-production-verification-packet-r1.md), [test/CI binding](checkpoint-test-ci-bindings-r1.md) | Actual native/DB/full/strict/export resources, exact test and fixture ownership, unchanged CI quality identity and required artifacts. |
| [Verification correction](checkpoint-verification-correction-r2.md), [interruption correction](checkpoint-interruption-intro-correction-r3.md) | Literal35/26 test sets,27 bases/58 actual-owner native mappings, and seven process-interruption versus two managed-error bases. |

Apply only these explicit corrections to the retained original text:

1. Spec130's adopted three data-only F1 calls replace the original blanket no-F1
   phrase only at ArtifactReplay's three named sites. No seed, signing, current
   trust, provider, SQL, F2, Clock or nested floor is authorized under the control
   floor. A historical shape/hash result is not current authority.
2. Type closure explicitly replaces the assembly-time capture-bound Reader:
   CheckpointReplay has WorkReplay,SegmentReplay,HistoricalAuthority,ArtifactReplay;
   ControlReplay has PublicationReplay,TransitionReplay,SegmentReplay,
   HistoricalAuthority. Each has four shared collaborators. The same ArtifactReplay
   instance and its three F1 sites remain. Exactly two Replay and two first fenced
   comparison methods construct Readers from original same-capture/scope loans.
   Phase7's final already-closed comparison cannot reopen or construct a Reader.
   The existing two-call-site initializer resolves the25 additional literal types;
   nonempty Normalizer FORM_C warming remains mandatory before either floor.
3. The25 original literal type surfaces supplement the original path union with23 new paths
   and two already located facts. The descriptor-free ControlHistoryCaptureRows
   alone uses ctor+six lookups+three representation methods, with exact source
   clone rejection. Native holders/facts/loans/grants retain their four runtime
   representation controls. No other type shares that exception.
4. The HT correction's exact Record.native projection, construction/readers and
   seal/capsule/endpoint failure disposal replace only its named incomplete joins.
   Explicit success-close and failed cleanup remain distinct. No fake successful
   comparison, returned raw handle, GC-only cleanup, reopened transcript or second
   native close is admitted. Original sanitized failure and graph poisoning remain.
   The caller correction removes the stale ordinary PublicationWorkflow.rootRevision
   edge already removed by installation r1. Its workflow has9 methods,control
   fallbacks total7 and CapturedControlHistory.close has12 external callers.
   InstallationWorkflow.rootRevision and PublicationWriter.rootRevision stay.
5. The online correction fixes Document/Settings/Preflight construction and exact
   five settings-reader sites. NativeObservation copies bounded actual data in the
   real owned root and binds original Grant/scope/endpoint identities plus monotone
   root generation. Same Connection after another root is insufficient. Existing
   ApplicationAuditConfiguration admits the exact signing/control sibling shape
   before its missing-outbox early return; semantic validation stays lazy. Its one
   additional existing test file brings the bounded existing list to26.
6. Installed-policy publication selects the originally requested64-lowerhex digest
   throughout phase14 and exact already-accepted recovery. It never computes max+1
   for accepted replay, invents a new archive producer or chooses SQL latest policy.
   Published/AlreadyPublished require actual durable accepted publication. Pending
   policy events remain pending; no D acknowledgement or scheduler activation follows.
7. The migration is exactly `migrations/postgresql/Version20261012000000.php`.
   Its down method throws irreversible. The old resource draft's “reverse teardown”
   phrase is incorrect; fixture-owned cleanup does not make Product down reversible.
8. Every original28-new-test admission is replaced by the literal35 set in the
   verification correction and allowlist. There are26 existing bounded edits.
   Preserve all3986 predecessor identities with only the one literal registry
   method rename. New methods in an existing class stay in its original partition.
   A36th new test path or unassigned existing edit requires a concrete scope return.
9. All58 native rows remain literal: io14,durability18,interruption18,cold8.
   The interruption intro applies successful-syscall barriers/SIGKILL only to its
   seven named process bases. Both variants of the two scratch managed-error bases
   have zero selected interruption barrier and zero SIGKILL, proving actual error
   handling and cleanup. Original row IDs, receipts, timeouts and counts do not change.
   External installer crash fixtures and actual Framework archive recovery are
   distinct owners; separately required whole Publisher/SQL-origin proof remains.
10. Adopted Linux/glibc exclusive ownership and prior checked durability govern
    native close. Completed positive short writes are valid; actual I/O failure
    poisons further use and leaves only the owned disposal attempt. Hidden consumed
    close errors and unsupported nonconsuming counterexamples keep their separate
    measured classifications. Resource invalidation is not native-fd proof. Actual
    image/ABI/route controls remain required; old calibration is not Product PASS.

11. [History tag families](history-tag-family-amendment-r1.md) explicitly replace
    only the single50-case enum with three zero-method backed-int enums15/20/15
    and the exact closed union. Every wire code/name/schema/cap/phase and identity
    rule remains. Only Reader.next has three native-from sites after prefix/code/
    cap admission. The two literal new paths bring edit scope295 to297 and effective
    type inventory25 to27. The existing preload16 becomes18 with its same two
    callers. Exact source/caller/guard/test obligations are in the supplement; no
    helper, threshold/baseline or runtime qualification waiver is introduced.

12. [Native admission responsibilities](history-admission-responsibility-amendment-r1.md)
    adds exactly seven stateless helpers and their27 declarations, retains the
    sole31-group NativeGroup schema and removes Codec's duplicate admission and
    encode-to-tag edge. Actual closed/sealed versions admit2..MAX; preparation
    captured versions stay2..MAX-1. The complete endpoint/presence matrices retain
    both F2 recovery gaps. The existing timestamp architecture owner is explicit.
    Scope297 becomes304, effective type closure27 becomes34, and additional eager
    resolutions18 become25. Reference62, tests35/26 and native58 stay unchanged.
    Existing method/argument/complexity/privacy/caller/source-origin limits remain.
    Affected review206ba7e1 closes HAD-01..03; Root closes its sole P3 reference
    correction and adopts this exact supplement. Committed Task issuance and
    separately opened runtime leases still govern implementation.

13. [Admitted-row type flow](history-type-flow-amendment-r1.md) replaces only
    NativeGroup.__construct's literal match with one literal integer switch.
    After complete scalar admission, each fixed relation case annotates that
    same admitted local with its truthful closed primitive shape, invokes the
    same one callee and ends with a bare break. The default is one bare break.
    Exact15 calls/20 relation tags/11 scalar-only tags, sole schema, sensitive
    boundary, retention ordering and all method/caller/quality limits remain.
    Installed1.42 annotation and budget probes are retained as syntax evidence;
    actual Product gates remain required. Affected Green39a24c20 adopts
    this frozen candidate through this finite precedence; its historical
    Candidate status is retained verbatim. Scope304/62, type34, preload25,
    tests35/26 and native58 do not change. HSR3-02..05 remain source obligations;
    the successor switch guard replaces the syntax-specific HSR3-01 proof.

There is no other override. Conflicting concrete callers, missing literal types,
extra helpers, changed schemas or unachievable declared budgets return one bounded
Finding to Root before implementation expands.

## Review evidence and execution boundary

The [combined r2 review](checkpoint-combined-authority-review-r2.md) closes CIR-01,
CIR-02 and PI-04 at declaration level. The [combined r3](checkpoint-combined-authority-review-r3.md.encoded.json),9312efe1/4ec76e5b,
closes HT-01/02 and the stale caller through its finite supplement, and confirms
the exact Reader/preload authority promotion0be04105. Root adopted that unchanged
diff in130/D179 (c028a816/a50834a5). The [verification
r2](checkpoint-verification-review-r2.md) closes35/26/58 mappings except its finite
intro finding; [verification r3](checkpoint-verification-review-r3.md) closes that
finding against the retained interruption supplement. Earlier reviews remain
historical evidence, not reopened whole-review assignments.

No review is native execution, Product Acceptance or release proof. Root grants
each runtime resource lease separately. Worker owns only assigned source files;
Root owns Report/STATE, Git, integration and Acceptance. A verified partial layer
is committed when complete, while the full child's real production Goal stays open.

The named PHP/native/Node/CI/Documentation tests and final unpartitioned512M suite
are mandatory. Retain current Composer/Mago/Deptrac/architecture/package limits;
no new dependency, baseline/threshold change, skipped coverage or optional gate.
Current Stable1.2.1/roadmap1.3.0 authority remains unchanged. F4/full F5/F6 and all
later D/E/G/hardening/Documentation/Owner preview/same-SHA release/delivery remain.
