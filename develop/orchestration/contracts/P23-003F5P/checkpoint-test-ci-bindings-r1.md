# Checkpoint prerequisite — exact test, fixture and CI bindings r1

Status: **finite candidate for Root's combined issuance review; not an execution lease, implementation or qualification**. Only this document and its input JSON are written. Root owns adoption, resource leases, the designated Luna implementation task, review, Acceptance and Git. No Product, test, workflow, fixture, migration, authority or STATE file is changed here.

P is `/home/kubotak/projects/blackops`; C is P's `.phpunit.cache/f2-orchestration/accepted-integration`; E13 is P's `.phpunit.cache/f5-orchestration/20261013`. Paths below are relative to the candidate checkout unless explicitly prefixed. All local commands run from P with the explicit C mount/paths; a CI checkout uses its normal repository root. Root reports unchanged Product base070c752. This lane did not invoke Git.

## 1. Fixed inputs and source-backed constraints

This joins composition `e39f6293`, history `4527806b`, installation `65c2e485`, its bounded review `b593fb5a`, pure-codec/native correction `a2dbcfec` and its separate independent Green `16e69b9b` to Root's `checkpoint-production-verification-packet-r1.md` SHA-256 `338d2727a7c724630a6e59b8bc65fd167108fd02ad9343a9c8adcd2bba92b2c4`. The two installation inventory corrections remain explicit inputs: PendingPublication.fromInstallation gets exactly Candidate.native once; Registry has9 methods and ControlHistoryReplay retains replay. This document supplies verification ownership only; it does not reopen their protocols or approve its author's own codec proposal.

The actual source constraints are:

- `phpunit.xml` discovers all of `tests`, bootstraps `vendor/autoload.php`, and sets512M. Keep that file's discovery and memory limit unchanged. Composer locks PHPUnit13.2.2; its actual vendor CLI supports `--list-tests-xml`, `--list-test-ids` and exact `--test-id-filter-file`. The filter processor ignores an empty filter file, which would otherwise run the full suite: the new partition tool must reject an empty selection before invoking PHPUnit.
- `.github/workflows/ci.yml` currently gives quality30min for build/install, both Composer validations, package export, version-baseline, four Mago commands, complete PHPUnit and Deptrac. Other jobs are community-board-clean-install35min, community-board-foundation40min, frontend20min, framework-update-runtime45min and website15min. Preserve those unrelated jobs and all their steps. Keep current workflow triggers, permissions and concurrency.
- Dockerfile declares php:8.5-cli and pcntl/pdo/pdo_pgsql/zip, Composer2.10 and the app UID/GID. Composer requires sodium and the existing Normalizer polyfill. It does not introduce ext-posix/fchmod/FFI. CI must calibrate its actually built Linux/glibc image; local image `ab739e1d…` is not remote proof. Missing compiler/native support fails the dedicated job; no install-on-demand or skip substitutes for it.
- `mise.toml` pins Node24.18.0 and pnpm11.12.0. Existing Audit golden scripts use node:test, node:crypto, independent JCS/framing and immutable JSON literals without a new package. Use the same mechanism for the new authority vectors.
- Native F2 tests already show unique owned schemas, fresh child connections, observed PostgreSQL barriers and cleanup diagnostics. Existing proc_open tests show independent `[PHP_BINARY, fixture, ...]` children, bounded pipes, terminate/reap and exit reconciliation. Reuse those ownership rules, not Product callbacks or the existing F2 test's incidental POSIX kill call.

The reserved migration is exactly `migrations/postgresql/Version20261012000000.php`,29 statements. This test packet neither reserves another version nor runs a migration.

## 2. Exact PHP test inventory and case ownership

Luna is the sole future source owner for the files below under Root's issued packet. Root is sole local runtime/resource owner and may explicitly delegate one sequential command within a lease. `P` means pure/in-process PHPUnit, `D` means actual PostgreSQL/application fixture in the exclusive database lane, `G` means architecture/caller/mutation guard. All are included by unchanged ordinary PHPUnit discovery; no F5 skip group or optional environment switch is added. The CI partitions in §6 execute every discovered PHP case once.

### New PHP test paths

| Exact path | Lane and discriminating cases owned here |
| --- | --- |
| tests/Internal/Application/ApplicationAuditCheckpointCompositionTest.php | P/D: ordinary compose has no F2; explicit composition creates one actual same-Connection C3/F2/protected-reader/admission graph; provider/catalog calls once; sticky duplicate/reentrant assembly; wrong Connection rejected. |
| tests/Internal/Application/ApplicationRetentionRuntimeTest.php | P/D: lazy absent-signing path; first scheduler assembly once; repeated access uses the same graph; failed/reentrant assembly remains failed; both poisoned cached entries refuse before lifecycle, provider and selection; fresh whole graph required. |
| tests/Internal/Console/AuditControlPublishCommandTest.php | P: exact lazy/resolved definition;64-lowercase-hex validation without normalization; malformed input2, owned runtime failure1, finite output channels; no fabricated successful private publication result as integration evidence. |
| tests/Internal/Application/ApplicationAuditControlInstallationIntegrationTest.php | D: actual public Application/Console construction; read-only help/list with broken build/signing prerequisites and no graph; actual two-closure lifecycle and one-use Composition; finish failure after accepted publication returns no success and poisons graph. Missing/extra arguments retain the actual inherited exception boundary. |
| tests/Internal/Audit/Control/AuditControlInstallationPublicationTest.php | P/D: exact five-way priority producing the four closed selection types; conflicting staged policy blocks accepted fallback; one-use scope/selection; phase14 retains original digest and rejects an authenticated different policy before publication I/O; no scheduler installation origin. |
| tests/Internal/Audit/Signing/OrdinaryAuditSignCompositionPostgreSqlTest.php | D: real compiled Definition, evaluator/resolver/Policy, actual envelope/HandlerInvoker and C3 request basis; wrong identity/service/alias/policy tuple rejects before reservation; permitted identity with denied checkpoint kind prepares then writes actual denial/Durable records but never signs/persists; real allowed chain stops truthfully at missing continuation. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditCheckpointProductionTest.php | D: actual ordinary/control reservation through F2 prepare, preparation_bound, held candidate, native F2 persist and persisted outcome; original F2 seven/three columns and bytes; Clock/UUID once and replay zero; interrupted phases, original key/time/bytes, current restrictions and rotation; no D ack/Required release/F4 success fabricated. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditCheckpointSelectorTest.php | P: native tenant/null pairs, exact bigint/UUID cursor and page32 boundaries, earliest-blocker and no-work result shape; no row filtering by desired origin or unsigned dispatch flag. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditCheckpointSelectorPostgreSqlTest.php | D: real pending-before-new order, ordinary/control/unattributed conflicts, complete/sealed pending work, dispatch deletion/corruption, actual READ ONLY rejection of mutation/locking reads and full proof only in separate write root. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditProvenanceSchemaTest.php | P: exact29 emitted statement bytes/order, validated configured quoting, seven immutable/two dispatch names, actual F2 keys, fixed functions/triggers/indexes and irreversible down behavior. Expected SQL is the independently frozen DDL, not generated from candidate source. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditProvenanceSchemaPostgreSqlTest.php | D: actual predecessor-to-reserved-version install under default/nondefault schemas; statement parity; all check/FK/unique violations, UPDATE/DELETE/TRUNCATE including no-op rejection, allowed dispatch-only changes, native catalog/index definitions, runtime role/owner limit, lock/statement timeouts and teardown. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditControlInstallationPostgreSqlTest.php | D: actual CLI zero-sentinel bootstrap, next-policy/stopped root/delegate replacement, exact staged/pending/accepted retry, policy selection tampering, backlog32/33, valid maximum accepted revision without+1, actual final publication success with pending D count. No active-B2 fabrication or archive/floor provisioning by the command. |
| tests/Internal/Audit/Provenance/PostgreSqlAuditControlPublicationPostgreSqlTest.php | D: normal scheduler current-head/recovery behavior; actual B2 close proof and its final reread, segment binding/advance, staged versus published lineage, first real control checkpoint; zero/currentless source unavailable and D-absent pending/failure. New-policy selection remains installation-only. |
| tests/Internal/Audit/Provenance/AuditCheckpointConcurrencyPostgreSqlTest.php | D: actual winner before RR capture, winner during observed fence wait, same/opposite-origin conflicts, snapshot-invisible uniqueness conflict, changed native endpoint before final fence and exact fresh-root winner recovery. No sleep-only race evidence. |
| tests/Internal/Audit/Provenance/AuditCheckpointAmbiguityPostgreSqlTest.php | D: real COMMIT response loss at C3 requested and unavailable delivery separately, and actual F2 preparation/persistence and F5 reservation/bound/candidate/outcome commits; zero later release/follow-up after uncertainty, exact failure precedence, poisoned entries, healthy committed pending distinct, fresh complete graph recovery. Uses the owned proxy below, not a coordinator/Connection success stub. |
| tests/Internal/Audit/Authority/AuditAuthorityDocumentCodecTest.php | P: five-field envelope closure, all three finite purposes, canonical bytes/hash, signature shape and domain/issuer separation; no trust from envelope key material. |
| tests/Internal/Audit/Authority/AuditAuthorityJsonPreflightTest.php | P: last legal/first illegal byte/node/depth/list/object bounds before recursive decode; duplicate/escaped duplicate keys, invalid UTF-8, malformed numbers and trailing data; no broadened history cap. |
| tests/Internal/Audit/Authority/AuditAuthorityProvenanceVectorTest.php | P: actual PHP document codec/verifier compare with the independent complete-envelope vectors in §4; each expected byte/hash/signature is literal input, not PHP-generated expected output. Tests the existing F1 reference bytes unchanged. The real private frame-production call is separately exercised through its actual owner in D tests, not a manufactured unit-test authority. |
| tests/Internal/Audit/Authority/History/AuditHistoryGrammarTest.php | P: all49 fixed tags/trailer255,15 legal phase orders, unknown/missing/extra/reordered/truncated frame, wrong trailer/count/EOF, phase14/15 accepted tag79 semantics, no serializable authority/phase substitution. |
| tests/Internal/Audit/Authority/History/AuditHistoryGroupCodecTest.php | P: every frozen closed native scalar group, reference rejection, safe counters/UTF-8, native absence matrices and work-step≤4 bound. |
| tests/Internal/Audit/Authority/History/AuditCheckpointArtifactReplayTest.php | P: bounded actual original unsigned/complete F2 bytes match provenance through the three exact F1 pure calls; candidate/native mismatches reject; no key/trust/seed/provider/Clock/sign/SQL permission is inferred. |
| tests/Internal/Audit/Authority/History/AuditHistoryCapturePostgreSqlTest.php | D: full uncapped real SQL histories captured before floor, complete historical issuer proof/current compromise lookup, many acknowledged transitions and policies, repeat bounded scans, exact fenced reread, changed B2/F2/work/publication endpoints, before/after phase misuse and capture cleanup before seed/F2. Historical issuer marker added after capture must still deny locked replay. |
| tests/Architecture/AuditCheckpointProductionArchitectureTest.php | G: exact ordinary/control invocation/composition/Operation/fact graph, one graph state, sticky failure, real F2 owners, no alternate activation or continuation. |
| tests/Architecture/AuditProvenanceArchitectureTest.php | G: exact schema29/row/RO-RW/fence/writer/frame ownership, same Connection and no SQL under floor; exact literal manifests, aliases-positive and semantic mutation negatives. |
| tests/Architecture/AuditControlInstallationArchitectureTest.php | G: fixed lazy Console/composer/lifecycle, selected fact origins, original digest retained in phase14, sole Candidate.native factory edge, no scheduler/bootstrap/ordinary signer bypass and exact getter/method inventory. |
| tests/Architecture/AuditAuthorityArchitectureTest.php | G: imported current-online/control acquisition, seed/delegate custody, source/archive/floor role separation and exact callers; no generic sign/get-seed callback, provider registration, dynamic construction or public marker. Exact imported leaf names remain the pending external input in §9. |
| tests/Architecture/AuditAuthorityHistoryArchitectureTest.php | G: exact grammar/scopes/one-use capture/replay, pure-F1 three sites, two pre-floor initialize sites, all R4/native explicit cleanup methods within10, purpose-specific I/O and no raw descriptor export. |
| tests/Internal/Tooling/PhpUnitPartitionTest.php | P: reject missing/duplicate/unknown predecessor IDs, undeclared new test file, omitted/disjointness failure, empty filter, dataset identity corruption and wrong JUnit/receipt SHA. Positive exact rename and all four complete partitions. This tests the meaningful coverage gate; it is not a Product test seam. |

The fixture and guard expectation manifests must retain every semantic case in these rows and Root's resource packet. New PHP cases must be explicit named methods/datasets discoverable before runtime; the worker reports their final IDs from list-tests-xml and the reviewer maps them to these obligations. Counting one trivial test in each file is insufficient. No new behavior is invented merely because a particular private crypto leaf name is still pending.

### Exact existing test paths whose assertions/call maps are in scope

| Paths | Required bounded changes; all existing cases remain |
| --- | --- |
| tests/Internal/Application/ApplicationAuditRuntimeComposerTest.php | Current compose signature/composition tests plus explicit new seam; preserve one provider/body graph and old safe failures. |
| tests/Internal/Application/ApplicationConsoleKernelTest.php; tests/Integration/ApplicationConsoleKernelTest.php; tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php | Lazy command row/actual help/registration and canonical/alias/Operation collisions. |
| tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php | New framework name participates in unchanged collision validation. |
| tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php; tests/Internal/Projection/Command/CommandProjectionProducerTest.php; tests/Internal/Projection/Command/CommandProjectionListCommandTest.php | Registry35→36, registered29→30, three-source38→39, visible fallback34→35; all formats/namespaces. Only permitted old-ID rename: testFrameworkNamesAreTheCurrentTwentyNineOnly → testFrameworkNamesAreTheCurrentThirtyOnly, in the exact registry test class. |
| tests/Internal/Scheduler/MaintenanceSchedulerTest.php | Exact task order, actual fail-fast and failed metrics; no completed result at absent continuation. |
| tests/Internal/Migration/DatabaseMigrationRunnerTest.php; tests/Internal/Migration/ConfigurablePostgreSqlMigrationFactoryTest.php; tests/Internal/Migration/PostgreSqlMigrationSchemaTest.php; tests/Internal/Console/DatabaseMigrationCommandTest.php | Reserved-version enumeration and actual configured factory/up/down/schema behavior. Current native counts of14 framework migrations become15 only where the new complete runner is exercised; application/legacy extras retain their separate arithmetic. Do not rewrite predecessor-specific migration tests. |
| tests/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStorePostgreSqlTest.php | Keep all F2 proof/replay/race/native cases. Update only complete-current migration inventory expectations that now include the reserved F5 schema; original preparation/checkpoint column/byte behavior unchanged. |
| tests/Architecture/ApplicationAuditAuthoringArchitectureTest.php; tests/Architecture/AuditDeliveryArchitectureTest.php; tests/Architecture/AuditCheckpointArchitectureTest.php | Exact new composer/catalog/assembly/fact getter/F2 callers only, actual CS-01 edge coverage; preserve current guards and mutation controls. |
| tests/Architecture/AuditArtifactArchitectureTest.php; tests/Architecture/AuditStoreArchitectureTest.php; tests/Architecture/AuditRecordArchitectureTest.php | Exact pure codec/sign/proof/record/canonical draft caller deltas, fixed APIs and actual B2/F2 boundaries; no namespace-wide exception. |
| tests/Architecture/AuditOutboxStorageArchitectureTest.php; tests/Architecture/AuditRelayArchitectureTest.php | Private composer refactor keeps existing graph counts and no signing-to-outbox/relay bypass. |
| tests/Architecture/AuditAnchorStateArchitectureTest.php; tests/Architecture/AuditPublicApiTest.php; tests/Architecture/PublicApiArchitectureTest.php | Retain F3/public/nonactivation inventory; only a concretely listed source reference may change. No F4 frame producer/public API added. |

Untouched predecessor tests, including the heavy AuditOperationRead oracle, still execute. The count partition is not permission to edit unrelated tests. Source changes outside these test paths require a concrete scoped correction, not weakening a baseline.

## 3. Exact test fixture and runner allowlist

These are proposed new files, not files created by this investigation. They are test tooling and excluded from Framework export by the existing tests boundary. Existing Product construction guards continue to scan only the approved Product ownership graph; fixture helpers never become Product callbacks/configuration.

| Exact path | Sole purpose and owner/resource use |
| --- | --- |
| tests/Fixtures/Audit/CheckpointProductionApplication.php | Shared actual Application fixture builder. Uses Application::configure/withEnvironment/withConfiguration/withOperations/withServices/create and real build:compile; writes generated configuration/container/manifests only inside its owned temporary app root. Fixed synthetic provider classes implement existing public fixture/provider contracts. It never fabricates acquired authority or substitutes a fake operations Connection/Clock/HandlerInvoker. D tests and the two cold composition cases use it. |
| tests/Fixtures/Audit/CheckpointProductionPostgreSql.php | D-only owned schema/connection/backend/barrier/process registry. Schema prefix audit_f5_ plus a per-invocation random suffix; canonical admitted schema quoting; each admin/product/probe connection recorded. Finally closes children/connections before dropping only the exact owned schemas and verifies disappearance. Never dumps environment/DSN/password/raw exception. |
| tests/Fixtures/Audit/checkpoint-production-child.php | Fresh actual Application/scheduler/installation child for concurrency/ambiguity scenarios. Fixed scenario vocabulary and inherited control pipes; no caller-provided PHP callback, seed getter or alternate production-origin flag. |
| tests/Fixtures/Audit/checkpoint-commit-response-proxy.php | D-only bounded loopback PostgreSQL response-loss fixture, one tracked listener, one actual product socket and its one upstream socket per scenario. Described below. It is never in Production or the filesystem lane. |
| tests/Fixtures/Audit/audit-authority-provenance-v1.json | Reviewed immutable independent positive/negative vectors; only synthetic publicly labelled test keys/seeds. No credentials or live seed material. |
| tests/Fixtures/Audit/audit-authority-provenance-golden.mjs | Independent Node bytes/hash/signature/canonical verifier; reads the preceding JSON and existing F1 golden reference, never PHP output or Product code. |
| tests/Fixtures/Audit/authority-filesystem-fault.c | The calibrated child-only inode/fd-scoped libc interposer. Exact fixed operation/plan/trace vocabulary, bounded trace, actual reached hit and no generic Product hook. No compiled library is committed. |
| tests/Fixtures/Audit/authority-filesystem-native.php | Executable native fixture: process ownership, calibration/control and actual issued custody/history owner case dispatch. Fixed cases in §5; actual child process, meaningful independent state observations, checked cleanup. It is not an auto-discovered Test.php that skips in the ordinary suite. |
| tests/Fixtures/Audit/AuthorityFilesystemCase.php | Fixed native case implementation/observation helper used only by the preceding fixture; private synthetic roots and no DB. Two cold cases assemble the actual declared graphs using the shared app fixture without executing the SQL lifecycle. |
| tests/Consumer/audit-authority-native.py | Host-only finite runner, derived from the Root calibration runner's resource accounting: exact image/lease/source hashes, fixed four suites, case/control containers, strict result/cleanup validation. Python standard library only; no generic command/resource callback or dependency installation. |
| tests/Fixtures/Quality/phpunit-predecessor-inventory.json | Immutable3986 predecessor IDs/class/file paths and measured class/case durations extracted from the retained F3 XML/receipt identities below. The sole explicit registry test-name rename is a separate exact mapping, never a wildcard removal allowlist. Does not auto-rebaseline from candidate tests. |
| tests/Fixtures/Quality/phpunit-partition.php | Pure CLI bookkeeping: parse current discovery XML, validate predecessor retention and exact candidate-test allowlist, write four disjoint nonempty exact-ID files and a manifest; validate selected JUnit identities and produce evidence JSON only after success. No DB/test execution within this tool. PHP DOM/XML facilities are already required by locked PHPUnit; no new extension/dependency. |
| tests/Fixtures/Quality/quality-results.mjs | Pure fixed-command receipt validator/recorder and final aggregator for required CI result names, same checkout SHA, partition manifests/receipts and native suite observations. It reads already recorded separate exits/logs, never runs arbitrary commands. Missing/cancelled/skipped/failed job, artifact or case rejects; it cannot declare a failed process successful. |
| tests/Fixtures/Quality/quality-results.test.mjs | Node negative tests: missing shard/case, duplicate or overlap, wrong SHA, nonterminal/cancelled/skipped status, forged counts/memory, unknown native result disposition, incomplete cleanup and empty evidence all fail; valid complete synthetic bookkeeping fixture passes. No Product success is mocked. |

Only `.github/workflows/ci.yml` changes among workflows. `tests/Consumer/framework-package-export.sh` and `tests/Consumer/framework-package-required-inventory.sh` must add the exact final combined Product/migration paths once the external import map is frozen. The second script pins the first's SHA and exact required count; update both deliberately, not a wildcard inventory or count-only success. Test/native libraries/fixtures remain excluded from both package archives. No change to Dockerfile, composer dependencies, phpunit.xml, mise.toml or unrelated workflow is selected by this packet.

### Actual COMMIT response-loss ownership

The D ambiguity suite must exercise the real configured operations Connection. Configure only that synthetic application's existing DB host/port parameters to the fixture's bound127.0.0.1 ephemeral port; original test environment supplies upstream PostgreSQL parameters at runtime, with no credential logging. Request sslmode=disable for this private local test connection so the fixture can inspect bounded PostgreSQL frames; this is test-only, not a production support change.

The proxy forwards startup/authentication and all normal traffic byte-for-byte with bounded buffers, without persisting authentication messages. Arm exactly the owned phase's next actual COMMIT from the observed real phase/barrier, not an arbitrary global query count. Forward COMMIT upstream, observe its actual server CommandComplete COMMIT and ReadyForQuery, withhold that terminal response from the product connection and close only its downstream socket. A distinct native observer must prove the exact expected committed C3/F2/F5 row outside that client. Product must observe uncertainty and poison the common graph. A rollback/error/no-COMMIT/unhit/extra-hit/SSL/unsupported framing result fails the case; it is not response-loss proof. Fixed successful controls forward the same server reply. No LD_PRELOAD is applied to the DB lane/supervisor/Composer/Mago.

The owning test tracks listener/product/upstream/control descriptors, actual child PIDs and backend PIDs. Use proc_open with pipes,8s handshakes and observed barriers; no inherited live DB sessions across children. Kill/reap only owned children, close all sockets, rollback/close surviving owned sessions, then remove owned gates/schema/files and assert no live backend/socket/resource. The proxy may bound one protocol frame/chunk and total captured diagnostic bytes, but must stream ordinary traffic and never cap accepted Product history. Resource IDs, source hashes, actual server commit, client failure, probe state and cleanup outcome enter the receipt. This is a proposed native fault fixture, not a claim of a currently implemented response-loss primitive.

## 4. Concrete independent byte-vector target

The new exact Node target is `tests/Fixtures/Audit/audit-authority-provenance-golden.mjs`, with shared literal data `audit-authority-provenance-v1.json` and PHP counterpart `tests/Internal/Audit/Authority/AuditAuthorityProvenanceVectorTest.php`.

Freeze reviewed literal vectors for all three five-field envelope purposes, the closed control policy/head/segment/root_revision/segment_advance/reservation/preparation_bound/artifact_candidate/artifact_persisted shapes used by this checkpoint prerequisite, and retained historical root/compromise/current-key observations. This does not add F4 frame signatures or D acknowledgement production. Include signature-domain LP32 framing, complete-envelope domain hash, raw-wrapper ordinary SHA distinction, base64url/no-padding/canonical JSON, safe-counter boundary, canonical Unicode/non-BMP ordering, null predecessor and exact accepted replay endpoints. Negative vectors alter purpose/issuer/ref/key role, signature/key length, malformed UTF-8/duplicate keys, Unicode normalization, counter, extra field or predecessor binding. Original F1 unsigned/complete bytes and F3 fixture hashes stay immutable references.

Node independently constructs Ed25519 test keys with node:crypto using known deterministic synthetic seeds, computes LP32/signatureInput/JCS/envelopeDigest directly from adopted129/130 and checks every literal; it must not run PHP, import Product parsers, consume freshly PHP-generated expectations or update the JSON on success. PHP calls the actual issued closed document codec/verifier and compares exact literals. Private phase-specific frame construction is reached through the actual production owner in D tests, with independently specified payload/byte invariants; the unit vector test cannot manufacture a current grant to invoke it. Root reviews the frozen vector file/hash before either language result is accepted. The exact online/delegate leaf caller names are supplied by the pending import map, not invented here.

Exact Node argv after files exist:

```text
node --test --test-isolation=none --test-reporter=tap tests/Fixtures/Audit/audit-authority-provenance-golden.mjs tests/Fixtures/Audit/audit-signed-artifacts-golden.mjs tests/Fixtures/Audit/audit-anchor-state-golden.mjs tests/Fixtures/Audit/audit-canonical-record-golden.mjs tests/Fixtures/Audit/audit-operation-read-foundation-golden.mjs tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs tests/Fixtures/Audit/observed-audit-record-golden.mjs tests/Fixtures/Audit/audit-event-catalog-golden.mjs tests/Fixtures/Audit/audit-postgresql-store-golden.mjs tests/Fixtures/Quality/quality-results.test.mjs
```

For local execution from P, prepend the C path to each file argument; there is no cwd change or wildcard test target. The PHP vector test is an iteration target and later remains in the complete full/disjoint CI suite; do not repeat its same-condition successful standalone run after the final full run.

## 5. Exact native suites, case variants and mounts

Root's27 base case names are partitioned as follows. Every non-cold base case has exactly two independent runs: `control` and `exercise`, each in a new disposable container. Each cold base has exactly `initialized`, `missing`, `late` and `empty-warmup`; only initialized may produce the fully checked positive observation. Thus the four suite result inventories are14,18,18 and8 runs,58 total; no unlisted extra success or absent case may satisfy them.

| Suite | Exact base cases |
| --- | --- |
| io | permission-mode; permission-erofs; scratch-enospc; durable-enospc; short-write-progress; zero-write; partial-then-write-error |
| durability | post-cast-flush-error; file-sync-error; immutable-file-sync-error; directory-sync-error; seek-error; truncate-error; unlock-error; consumed-close-error; nonconsuming-close-counterexample |
| interruption | process-control; scratch-unlink-error; scratch-rmdir-error; two-process-lock-death; anonymous-capture-death; floor-partial-write-death; floor-post-sync-death; archive-link-death; archive-directory-sync-death |
| cold | cold-unicode-maintenance; cold-unicode-installation |

All cases retain Root's required independent inode/mode/link/bytes/namespace/lock/resource observations, original primary failure, restored error handler and cleanup accounting. Permission-mode proves admission while permission-erofs proves actual OS denial; positive short write, no-progress zero and prefix-then-error stay distinct. Consumed-but-masked close is recorded as supported masked behavior, while deliberately nonconsuming-close is an unsupported counterexample; neither is a fake PHP negative PASS. Native hits must be exactly the selected reached route count; controls retain an observed real route without injection. Every selected fault includes calibration/control before protected-owner exercise; missing/unhit/extra-hit/malformed/trace-overflow fails. Existing r3/r4 diagnostic evidence does not waive calibration on CI's actual image.

The cold positive cases use fresh processes and the actual RetentionRuntime scheduler assembly or InstallationComposer.compose→InstallationComposition assembly. They invoke the exact prerequisites before either floor, then actual non-ASCII pure replay under a real held control floor, checking the finite loaded-class/Normalizer-table closure and forbidden-call boundary. Missing/late/empty are negative controls of this load/ordering observation: respectively a fresh direct replay without the composition prerequisite, a fixture acquiring its floor before the actual assembly, and a fresh finite class resolution followed only by empty Normalizer warmup. They must be distinguished from the correct actual-composition path and cannot be reported as successful Product flows. No Product source edit/test flag, replacement codec or production callback is allowed. These network-none tests may compile their synthetic app and construct the real lazy graph but never run lifecycle SQL. The separate D installation/ordinary tests prove the full live CLI/scheduler-to-floor flow. A positive fixture that can only call initialize directly and never reaches the real assembly site is insufficient.

The host runner's exact accepted argv is:

```text
python3 tests/Consumer/audit-authority-native.py --source-root . --image <inspected-image-id> --suite <io|durability|interruption|cold> --output .phpunit.cache/authority-native/<suite> --lease .phpunit.cache/authority-native/lease.json
```

`<suite>` is one of the four literal values above and is expanded to its fixed case/variant list. `<inspected-image-id>` must be the actual locally authorized/pinned Docker image ID; a tag is not accepted as proof. These placeholders are runtime resource identities recorded by Root, not an unbound test name or generic command. For local C use source-root `.phpunit.cache/f2-orchestration/accepted-integration` and the C-prefixed runner path; output/lease are Root-owned E13/native-product paths named in the later lease. CI uses source-root `.` on its own checkout and its own new fixed job lease/receipt. No current lease is reactivated.

Every emitted Docker argv retains exactly Root's profile: network none, read-only root, user1000:1000, cap-drop ALL, no-new-privileges, pids32, memory/swap768MiB; /tmp16MiB noexec/nosuid/nodev, /fault32MiB noexec/nosuid/nodev, /build64MiB exec/nosuid/nodev only for the library. Per-case mount allowlist:

```text
C/src                                                   -> /app/src                                      read-only
C/vendor                                                -> /app/vendor                                   read-only
C/tests/Fixtures/Audit/authority-filesystem-fault.c       -> /app/tests/Fixtures/Audit/authority-filesystem-fault.c       read-only
C/tests/Fixtures/Audit/authority-filesystem-native.php    -> /app/tests/Fixtures/Audit/authority-filesystem-native.php    read-only
C/tests/Fixtures/Audit/AuthorityFilesystemCase.php       -> /app/tests/Fixtures/Audit/AuthorityFilesystemCase.php       read-only
C/tests/Fixtures/Audit/CheckpointProductionApplication.php -> /app/tests/Fixtures/Audit/CheckpointProductionApplication.php read-only
C/tests/Fixtures/Audit/audit-authority-provenance-v1.json -> /app/tests/Fixtures/Audit/audit-authority-provenance-v1.json read-only
C/tests/Fixtures/Audit/audit-signed-artifacts-v1.json     -> /app/tests/Fixtures/Audit/audit-signed-artifacts-v1.json     read-only
fresh exact case output directory                      -> /evidence                                    read-write
```

Only permission-erofs additionally mounts one Root-owned synthetic regular0600 file read-only at `/fixture/readonly-floor`. No repository-root, .env, socket, DB network, live archive/seed or broad tests mount is allowed. The filesystem fixture uses no migrations or PostgreSQL helper. Synthetic compilation writes only inside its owned /fault root; no application provider is read from the host installation.

Inside each case, the fixed compiler argv is `cc -std=c11 -D_GNU_SOURCE -shared -fPIC -fno-builtin -O2 -Wall -Wextra -Werror -Wl,-z,relro,-z,now -o /build/libauthority_fault.so /app/tests/Fixtures/Audit/authority-filesystem-fault.c -ldl`. Record compiler/libc/PHP/ELF/source/library hashes. The supervisor runs `php -d memory_limit=512M /app/tests/Fixtures/Audit/authority-filesystem-native.php <case> <variant>` without LD_PRELOAD; only its selected PHP child gets the exact library and bounded fault plan. Source/fixture methods must use actual issued owners and the calibrated post-cast flush route.

Handshake8s, primitive15s and outer container60s remain containment ceilings. Each runner suite is at most18 separate60s containers before its small setup/aggregation work; CI's30min job timeout is not raised. Long history memory cases belong to D and measure actual memory/time under512M without a Product lifetime cap. Normal/error/timeout paths track and reap children, verify container absence, retain pre-cleanup post-state evidence and remove only identified synthetic artifacts. A cleanup failure prevents a successful suite receipt.

## 6. Measured complete PHPUnit partition and exact CI jobs

### Actual duration evidence and deterministic assignment

The retained F3 final receipt is `P/.phpunit.cache/f3-orchestration/20261009/run-20261012T030920550572-full/receipt.json`: Passed, exit0, wall1572.523063s, no input changes. Its full.xml SHA-256 is `efbe6ae85b2aab8734fba8877e12531611aac37448894cfccf238035639784f8`:3986 tests,8634246 assertions, no errors/failures/skips, suite1568.458437s. The exact discovery file is sibling run `run-20261012T030054341168-discovery/test-inventory.xml`, SHA-256 `f1bcd4f51df140845b6b0c1aeb8925cbe809aaf84864100eeabb2d9b8b8e7650`. Its primary tests/testClass/testMethod inventory has3986 IDs; do not double-count repeated group references.

The actual longest indivisible case is `AuditOperationReadArchitectureTest::testTokenAwareOracleRejectsArchitectureMutationsAndKeepsFalsePositives`,831.224429s. Do not claim sharding splits that method or guarantees a future CI speed. Source-only aggregation of all actual JUnit case durations gives:

| Fixed partition | Exact assignment rule | Existing cases / measured test seconds |
| --- | --- | --- |
| legacy-heavy | The actual class at tests/Architecture/AuditOperationReadArchitectureTest.php, all its current IDs |6 /842.254433 |
| legacy-architecture | Every other class/file present in the frozen predecessor inventory under tests/Architecture, all current IDs in those classes |169 /513.619481 |
| legacy-runtime | Every predecessor class/file outside tests/Architecture, all its current IDs |3811 /212.584549 |
| checkpoint-production | Every new class/file from the exact28-path new-PHP list in §2, all its current IDs | New; no fabricated duration/count |

Counts/durations above are predecessor observations, not future PASS/timing estimates. New methods added to an existing class remain in that class's original partition. A new PHP test outside the issued28-path list is an inventory failure until explicitly reviewed; no catch-all silently admits or drops it. All predecessor IDs must remain after the single exact registry-name rename. The four current partitions must be nonempty, pairwise disjoint and their union equal the entire unfiltered discovery. No test group exclusion, --filter regex selection or shortened heavy oracle is used.

Each matrix runner produces its own unfiltered discovery, validates the same predecessor and candidate-test inputs, selects its exact ID file, and records the discovery/selection hashes. Before execution it performs filtered discovery with the same exact file and proves equality with the planned selection; an empty file fails before PHPUnit. After execution it compares every actual JUnit class+method+dataset identity with the selected list, including named/numeric datasets using the locked PHPUnit13 naming rules. Duplicate/missing/extra cases, failure/error/skipped/incomplete/risky results, wrong SHA or a missing terminal log fail qualification. Inherited notices/deprecations are reported with the predecessor diagnostics, not silently called warning-free or globally waived.

The committed predecessor inventory is created from the pinned retained XML, not from candidate discovery. No new source runtime is needed merely to extract those immutable test IDs/durations. Final local unpartitioned full512M run remains mandatory under Root's packet; CI partitioning is an explicit same-SHA execution partition of the complete suite, not local full-suite evidence or an omission of it.

### CI workflow structure — candidate exact delta

Retain job id `quality`, exact check name **`Mago, PHPUnit, and Deptrac`**, ubuntu-latest and timeout30. Move its executing steps into the following finite prerequisite jobs, then make quality an `if: ${{ always() }}` aggregate. Its `needs` is exactly `[quality-static, quality-package, quality-phpunit, quality-vectors, audit-authority-native]`. Each needs result must be literal success; failure, cancelled, skipped or absent rejects. There is no continue-on-error, optional matrix row, path-based exclusion or timeout increase.

| Job id / timeout | Exact responsibility and required command entries |
| --- | --- |
| quality-static /30min | Existing checkout@v7, UID/GID setup, build/install; both Composer strict validations; unchanged four Mago commands including `format --check src tests examples`; `php vendor/bin/deptrac analyse --no-cache`; management-ID absence command. Record separate exits. No PHPUnit/export bundled here. |
| quality-package /30min | Existing checkout@v7 with fetch-depth0, UID/GID, build/install; `bash tests/Consumer/framework-package-required-inventory.sh`, then unchanged `bash tests/Consumer/framework-package-export.sh` and `bash tests/Consumer/version-baseline.sh`. Clean checkout same SHA and both archive inventories required. |
| quality-phpunit /30min per matrix row | `strategy.fail-fast:false`; exact partition matrix `[legacy-heavy,legacy-architecture,legacy-runtime,checkpoint-production]`. Each job has its own runner/Compose PostgreSQL service, build/install, full/filtered discovery, exact selected PHPUnit and JUnit/cleanup receipt. No two rows share a DB. |
| quality-vectors /20min | Existing mise-action@v4 and exact Node/pnpm version checks, then the complete literal Node argv in §4. No PHP/native/Docker dependency. |
| audit-authority-native /30min per matrix row | `strategy.fail-fast:false`; exact suite matrix `[io,durability,interruption,cold]`. Existing image build/locked Composer install, then inspect actual image ID and new finite native runner argv for that literal suite. Container fixture profile/calibration/results58-union are mandatory. No PostgreSQL service is used by the runner or its cases. |
| quality /30min unchanged identity | Checkout/mise plus download all exact producer artifacts and pure result aggregation. Check every needs result, expected4 PHP partitions and4 native suites, complete same-SHA manifest/ID/case union and all cleanup/exit states. A missing producer/artifact is failure even if a matrix expression was accidentally removed. |

Use repository-established actions `actions/upload-artifact@v7` and `actions/download-artifact@v8` (present in docs.yml). Upload after each producer with `if: always()`, `if-no-files-found:error`; an incomplete failed producer may retain diagnostics but cannot emit a qualified-success receipt. Artifact names are fixed `quality-static`, `quality-package`, `quality-vectors`, `quality-phpunit-<partition>` and `audit-authority-native-<suite>` for this workflow run only. Download each into its own named subdirectory without merging same-named receipt files. Receipts include GITHUB_SHA/checked-out HEAD, source/lock/vendor/image/tool versions, argv, exact test/case inventory hashes, separate terminal exits, duration/peak memory and cleanup. Aggregate requires all11 producer artifacts on the same actual checkout SHA, then verifies their fixed contents; no latest-run or cross-SHA download. New producer jobs using the Node recorder also install the same pinned mise toolchain; no system-Node assumption is introduced.

Keep existing `quality` name and fail-closed aggregate so required branch-check identity survives while all original gates still run. The aggregate does not replace the Parent006 full required CI/export/publication gates. If measured future new partition or CI host speed exceeds30min, that is a failing gate requiring a measured explicit repartition after review; this candidate grants no hidden timeout extension, case reduction, larger PHP memory or auto-retry-until-green.

## 7. Literal command argv and generated-output ownership

Local APP is exactly Root's reserved argv prefix:

```text
docker compose -f compose.yaml -f .phpunit.cache/f5-orchestration/20261013/compose.override.yaml run --rm -T app
```

CI APP is exactly `docker compose run --rm -T app`, with its isolated checkout/Compose environment and no local override. Build/setup preserve existing CI argv `docker compose build app`, then APP `composer install --no-interaction --prefer-dist --no-progress`; native setup uses exactly `docker compose run --rm --no-deps -T app composer install --no-interaction --prefer-dist --no-progress` and never starts a DB for its case runner. No environment/credential contents are printed.

The moved static/package commands retain these literal arguments, each separately recorded:

```text
APP composer validate --strict
APP composer validate --strict examples/quickstart/composer.json
APP mago format --check src tests examples
APP mago lint
APP mago lint --verify-baseline
APP mago analyze
APP php vendor/bin/deptrac analyse --no-cache
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO[.]md:[0-9]+' src tests --glob '*.php'
bash tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/version-baseline.sh
```

The first eight belong to quality-static; the last three to quality-package. The raw rg command succeeds as an absence gate only with actual exit1 and empty output; exit0 (hits) and exit2 (error) both fail. Every other entry requires terminal exit0. The local raw rg retains the two explicit C-prefixed paths from Root's packet. Every job that starts Compose keeps the existing always-run `docker compose down --volumes --remove-orphans`, records its actual exit and verifies its owned resources gone before a qualified receipt; native case containers additionally have the runner's per-case checked cleanup. The Node-only vector/aggregate jobs start no Compose resources. Pinned toolchain checks are exactly `test "$(node --version)" = "v24.18.0"` and `test "$(pnpm --version)" = "11.12.0"`, each requiring exit0.

Fixed matrix commands, each a separately recorded command after assigning the literal partition value:

```text
APP php -d memory_limit=512M vendor/bin/phpunit --list-tests-xml .phpunit.cache/quality/current.xml
APP php -d memory_limit=512M tests/Fixtures/Quality/phpunit-partition.php plan --inventory .phpunit.cache/quality/current.xml --baseline tests/Fixtures/Quality/phpunit-predecessor-inventory.json --source-sha <checked-out-SHA> --output .phpunit.cache/quality
APP php -d memory_limit=512M vendor/bin/phpunit --test-id-filter-file .phpunit.cache/quality/<partition>.ids --list-tests-xml .phpunit.cache/quality/<partition>-selected.xml
APP php -d memory_limit=512M tests/Fixtures/Quality/phpunit-partition.php check-selection --manifest .phpunit.cache/quality/manifest.json --partition <partition> --inventory .phpunit.cache/quality/<partition>-selected.xml
APP php -d memory_limit=512M vendor/bin/phpunit --test-id-filter-file .phpunit.cache/quality/<partition>.ids --display-all-issues --cache-directory .phpunit.cache/quality/<partition> --log-junit .phpunit.cache/quality/<partition>.xml
APP php -d memory_limit=512M tests/Fixtures/Quality/phpunit-partition.php verify --manifest .phpunit.cache/quality/manifest.json --partition <partition> --junit .phpunit.cache/quality/<partition>.xml --exit-file .phpunit.cache/quality/<partition>.exit --log .phpunit.cache/quality/<partition>.log --output .phpunit.cache/quality/<partition>-receipt.json
```

The generator creates a manifest with exactly current discovery SHA, predecessor identity/rename, literal candidate paths, partition→ID sets/hash and checked-out source SHA provided by its owning runner. It reads no Product authority/configuration and runs no PHPUnit itself. Use exact argv arrays or properly quoted shell variables for partition values; accepted vocabulary is fixed, not a generic path switch. The workflow wrapper captures actual stdout/stderr, duration and terminal exit for each separate command, writes that numeric exit before propagating failure, and uses pipefail if tee is involved. Missing terminal exit after timeout/kill denies verify; JUnit alone is insufficient. Extract actual PHPUnit peak memory from its terminal log and retain the unchanged configured512M separately; do not infer usage from the limit.

Static/package/vector producer closure uses the finite recorder argv `node tests/Fixtures/Quality/quality-results.mjs record --kind <quality-static|quality-package|quality-vectors> --commands .phpunit.cache/quality/<kind>-commands.json --source-sha <checked-out-SHA> --output .phpunit.cache/quality/<kind>-receipt.json`. It accepts only the exact separately recorded command lists in this document with their fixed expected terminal exits (raw rg1, all others0), matching actual logs/input identities and completed cleanup; no caller-provided success boolean overrides them. A default-success following step creates the receipt only after those real commands and cleanup complete. An always-run uploader may upload failure diagnostics without a success receipt. The aggregate additionally requires the GitHub job result itself to be success, so a forged/incomplete receipt cannot convert failed/cancelled/skipped execution into success.

The final aggregate's literal Node entry is `node tests/Fixtures/Quality/quality-results.mjs --artifacts .phpunit.cache/quality-results --source-sha <GITHUB_SHA> --needs-json <exact-toJSON(needs)>`. The GitHub expression is passed through an environment value/structured argv, not executable shell interpolation. Its own negative tests run in quality-vectors before the aggregate accepts data.

Local iteration uses the same APP prefix with each exact relevant PHP test path in §2. D test files run sequentially under Root's sole DB lease. The native runner is the only filesystem case runner; it cannot use the database lease. After successful final full, do not rerun an identical contained subset just for another reviewer. Root's full command, strict quality, comment-ID absence, committed export and final source hashes remain exactly as specified in verification packet338d2727. No success before the actual commands/files exist is claimed.

Output ownership: all local discovery/JUnit/cache paths are inside C/.phpunit.cache/f5 or C/.phpunit.cache/quality; Root's E13 lease/receipts refer to their exact host paths and retain copies/hashes. Native case output is a fresh distinct E13/native-product/<suite>/<case>/<variant> directory. Generated fixture application/storage paths are private temporary roots created and removed only by their owning test. CI artifacts come from the exact job-local .phpunit.cache directories. No shared generated destination is concurrently written; every CI matrix row has an independent VM and Compose DB.

## 8. Native/PostgreSQL acceptance and non-placeholder checks

The complete native D matrix is owned across the named tests, not a codec-only primitive. Required evidence includes real ordinary/control production origins and exact evaluator/C3 barriers, real bootstrap and stopped cutover, immutable reservation/step/candidate/publication bytes, actual F2 preparation/persist callbacks absent, history before floor/full issuer proof, expected endpoints after floor, actual concurrent snapshot/fence behavior and server-side committed response loss. Different data/provider flags cannot substitute for an actual production origin or native effect.

Keep original F3 fields/Accepted result boundaries. New migration must not turn F4 static contracts into implemented14-row producers. D-control/Required/receipt/seal continuations remain absent where not issued; actual checkpoint outcome and policy event remain pending/unavailable there. No test-only acknowledgement, mock release callback or fabricated success fills that boundary.

Cold preload, native fault and DB ambiguity cases are distinct from ordinary full-suite conditions. They therefore remain mandatory alongside the final full run. Peak PHP memory512M, source/binary hashes, exact selected route/hit and successful checked cleanup are actual receipt fields. A return code0 without those observations is not qualified. A native unsupported counterexample is labelled honestly and cannot replace an observable Product negative. The future worker must report any unhit/current-image incompatibility as a failed/unqualified case, not skipped.

## 9. Finite remaining external input and handoff

The only still-unbound technical source import for these fixed test paths is the separately assigned `online-delegate-checkpoint-issuance-r1.md`: exact current-online snapshot/key/seed and private purpose-specific delegate constructors/method/caller inventory. This candidate deliberately does not invent that crypto API. On its freeze, Root adds its exact identity and literal caller expectations to Authority/Artifact/Production guards, PHP vector calls and fixture construction; filenames, semantic cases, isolated resources, CI partition and commands above remain fixed unless that actual import identifies a concrete necessary correction.

Root also performs the already required governance adoption, installation IPR-01/02 correction, full combined independent review, explicit lease creation and test-source/vector expectation review. Those are issuance actions, not an unresolved new protocol or permission to execute now. The29-statement migration slot is already reserved. Native image/compiler/actual tests are intentionally future measurements, not unknown file/command choices. All new fixtures/runners/CI clauses listed here require implementation/review before execution; no existing Product source is awaited to avoid completing this inventory.

Bookkeeping only: selected source/proposal/XML bytes and hashes were read; Python parsed retained XML/JSON and summed already recorded durations. A narrow rg lookup found no memory entry for these exact artifact/task names. No PHP/Node/DB/native/compiler/Docker/network/Git/test command ran. No historical full suite was rerun. Only this document and its selected-input JSON are owned outputs; all candidate code paths remain uncreated by this lane.

Remaining work: freeze/import the external leaf map, complete combined packet review, Luna implementation, native/strict/full/CI/export qualification and the remaining F4/F5/D/Required/1.3 release work.

Next Action: Root reviews this exact test/CI binding candidate, combines the external import and installation corrections, and issues one concrete implementation/resource packet after independent approval.
