# Checkpoint verification contract — independent review r1

Outcome: **Needs correction: CVR-01 and CVR-02.** This freezes the independent review of the test/CI binding and Root resource contract. The two findings are finite issuance requirements. No test, native process, database, CI job or Product implementation was executed or accepted by this review.

Owner: `/root/f5_native_contract_plan`. Root-reported accepted checkout C is `/home/kubotak/projects/blackops/.phpunit.cache/f2-orchestration/accepted-integration`, base `ba026679c258a48414e5dd39d3551d9c1913cb51`; Git was not independently queried. All shell work used primary root `/home/kubotak/projects/blackops`. Only this ignored Markdown and its selected-input JSON are changed by this lane.

## 1. Review inputs and independence

The concrete review inputs are:

| Input under E13 | SHA-256 | Use |
| --- | --- | --- |
| checkpoint-test-ci-bindings-r1.md | bd77b117d437aba0d804c9e5cc3d280c3b0cb44f4fbb60ef7276ea747ea811f5 | Other author's exact tests, fixtures, commands, 58 native runs and CI contract. |
| checkpoint-test-ci-bindings-r1-inputs.json | 2ec28fd1d722c634911237e9b60acd05ad5da7c894b92f830c9905e2fab267ce | Literal source, file, case and job inventories. |
| checkpoint-production-verification-packet-r1.md | 338d2727a7c724630a6e59b8bc65fd167108fd02ad9343a9c8adcd2bba92b2c4 | Root's resource lanes and required production observations. |
| checkpoint-combined-issuance-candidate-r1.md | cbf6641e46cdf5da4f5e42b764993305ca07e9cd53567313ceefc5b1fde6d6fb | Exact overrides and independent-review boundary. |
| checkpoint-combined-path-inventory-r1.md | ff20b23aa9705a0b826839deb6d1546f8c0a9c7a24a163d308d6d3b87102f3d5 | Final mechanical path collation, particularly B17/PI-01. |
| checkpoint-combined-path-inventory-r1.json | 83d0b5d813189f0b5455024202170bc2cfd54f6704b09f1867d20af9c00d11c5 | Exact `newPhpTestUnion.completeLiteral35`. |

The online/delegate map `4b3d173075ccc83bd462dc09e60a65954c5c40cc1fa6be06acb7d57dddff1c4b` is this reviewer's own earlier design. Its seven literal new test paths are counted here as a dependency; its crypto, custody, phase7 and online protocol are **not self-approved**. `/root/f3_authority_review` owns their independent review. Likewise the final collation's PI-02–PI-05 type/parser/documentation closure issues remain with Root and the authority reviewer; this review neither resolves nor conceals them.

The companion manifest pins the actual accepted source, immutable predecessor XML/receipt/log, assigned maps and final path union. It distinguishes the two governance changes between the test author's snapshot and this review's first pin (C STATE and current Task) from changes during this review. At the final check, Root had updated both C and P STATE to its02:45:34 checkpoint, adding the concrete findings and next correction step; the other93 of95 selected inputs remained unchanged. Those two exact before/after governance hashes are retained rather than claiming all95 were stable. The updated checkpoint preserves the assignment, Product boundary and review requirements; it does not resolve either finding.

## 2. CVR-01 — the 28-path admission excludes seven required tests

**Finding.** Binder §6, particularly its partition table and following paragraph at lines176–185, assigns `checkpoint-production` to the exact28 new paths in §2 and rejects every other new test. The frozen online import §10, lines282–290, adds seven distinct paths. Combined override5 binds the imported names but does not replace the binder's literal28 admission rule. Implementing both contracts literally therefore rejects the complete candidate or omits required new cases. This is not a theoretical count difference.

The seven additional paths are:

```text
tests/Internal/Audit/Signing/AuditOnlineConfigurationTest.php
tests/Internal/Audit/Signing/AuditOnlineAcquisitionTest.php
tests/Internal/Audit/Signing/AuditCheckpointOnlineChecksTest.php
tests/Internal/Audit/Authority/AuditCheckpointCandidateSignerTest.php
tests/Internal/Audit/Authority/AuditCheckpointPhaseAcquisitionTest.php
tests/Internal/Audit/Authority/Custody/AuditControlDelegateCustodyTest.php
tests/Internal/Audit/Authority/AuditSigningImportNativeIntegrationTest.php
```

Static comparison confirms original28 plus disjoint7 equals35, with all35 absent from C. The final inventory independently supplies precisely the same sorted union in `newPhpTestUnion.completeLiteral35`, identifies B17/PI-01 and explicitly does not amend the frozen binder. The review manifest also preserves all35 literal paths. No wildcard or inferred extra path is needed.

**Required correction.** The test/CI author must supply a bounded explicit override replacing every operative28-path admission/count with that exact35-path list in the partition planner, emitted manifest and current-discovery checks. All35 classes and their discovered cases belong to `checkpoint-production`, including the seven import classes. Preserve unfiltered discovery, the three legacy rules, all predecessor IDs, the sole named registry-test rename, existing-class additions in their old partition and the512M limit. Merely changing a prose count, adding an optional seven-test job or broadening to any path under an Audit namespace does not close this finding.

**Discriminating acceptance.** The candidate inventory is admitted only when the literal35 list agrees across the packet, planner and manifest; the four current partitions are nonempty, pairwise disjoint and exactly cover unfiltered discovery. Exercise omission/misassignment of each added path, unknown36th new path, duplicate ID, dropped predecessor ID and an empty selected-ID file as failures. The exact requested case set must equal both filtered discovery and actual JUnit results. No automatic current-source rebaseline or reduced semantic coverage is allowed. This correction does not approve the underlying imported Product protocol.

## 3. CVR-02 — native cases lack an exact executable owner binding

**Finding.** Binder §5 fixes27 base case names and58 run IDs, an exact fixture allowlist and mandatory observations. It imports the reviewed descriptor method inventories and says to exercise actual issued owners. It does **not** give a literal case-to-Product-owner/method-to-fixture/consumer-to-native-route/result mapping for those runs. `AuthorityFilesystemCase.php` plus the instruction to dispatch an actual owner is not a closed executable binding. The original author confirmed that this table is absent; the path collation does not claim to fill it.

This matters at the already selected boundaries: ordinary file versus floor file versus immutable archive replay, directory synchronization, anonymous transcript namespace removal, actual same-inode replacement, post-cast flush, lock/unlock/close and cold composition. Calling a neighboring primitive, reaching only a raw PHP/libc calibration route, or directly warming a helper can produce a trace without proving the intended Production method and semantic consumer ran. A source import of all native methods does not identify which method a particular case must reach.

**Required correction.** Bind the existing58 run IDs, without adding a general callback, Product hook or new protocol, to an explicit finite qualification map. Each base case and its control/exercise or cold variant must identify:

1. Exact Product path, type and method, plus the fixed semantic consumer/call edge being exercised. Use the already reviewed holder surfaces; do not invent a generic file owner or interchange archive/floor/private-history behavior.
2. Exact executable fixture entry/method and fixed scenario, including whether this is holder-level native evidence, cold actual-assembly evidence or a separately named full-production/DB test. Bind the actual two cold assembly edges and each negative observation path.
3. Exact native operation/plan and independently observed inode/fd/namespace/barrier identity, required reached route and hit count. The non-fault control must prove the same real route without injection. Wrong method/route, missing or extra hits, malformed/overflowed trace and unreached faults are unqualified.
4. Expected control and exercise result, original failure precedence, pre-cleanup durable bytes/mode/link/lock/resource state and checked cleanup. Separate positive short-write completion, observable failures, supported consumed-but-masked close behavior and the unsupported nonconsuming-close counterexample. PHP success or resource invalidation cannot erase the adopted native limitation.
5. Receipt row and owning suite for every run, with exact source/image/fixture identity, duration and memory. The aggregation must reject an absent, duplicate, unknown, failed or wrongly mapped observation.

The correction must retain the independent **whole-production/DB** cases already assigned to the named ordinary, installation, checkpoint, concurrency and ambiguity tests. Holder fault evidence proves a holder's actual route/failure contract. Whole-production cases prove real Application/Console/scheduler/Operation/evaluator/C3/F2/SQL origin and current phase behavior. Neither substitutes for the other. The network-none filesystem lane cannot certify a database mutation; a successful database path cannot waive an unreached native fault. No synthetic grant, successful callback, fake origin or codec-only stand-in closes either obligation.

**Discriminating acceptance.** All58 current run IDs have exactly one complete, coherent executable mapping: io14, durability18, interruption18 and cold8. Preserve every required semantic observation and all resource limits. The mapping must make a primitive-only substitute, wrong holder/consumer, direct-initialize-only cold positive and incorrectly reported masked-close negative visibly fail qualification. Independently retain actual zero-bootstrap/root-revision/recovery, ordinary denied/allowed production, native F2 and server-committed response-loss proofs in their named D cases. Review the correction's affected rows and guards; do not reopen the already reviewed protocol or run the native matrix merely to review its specification.

## 4. Predecessor identities and partitioning verified

The retained predecessor discovery XML has namespace `https://xml.phpunit.de/testSuite`; only primary `tests/testClass/testMethod` entries count, not the repeated group views. Read-only Python XML bookkeeping reconstructed the exact3986 unique IDs across478 classes. JUnit reconstruction follows the locked PHPUnit source: `class::method#dataset`, including the different numeric and quoted named-dataset renderings. Both identity sets agree exactly; no duplicate, omitted or extra case was found. Assertions are8634246; retained JUnit has zero failure/error/skip.

| Future partition | Retained predecessor cases | Sum of retained testcase seconds | SHA-256 of sorted IDs, LF terminated |
| --- | ---: | ---: | --- |
| legacy-heavy | 6 | 842.254433 | 70a7eef1c7ae34802c96c97b578b0bf5f82b40abc1905d0b3976d29db334a3ed |
| legacy-architecture | 169 | 513.619481 | 1503e980e8bd3189a65663e275166274a3f2ef2f1c7775fe0faaaf124e84b257 |
| legacy-runtime | 3811 | 212.584549 | 29b2308b95a75d6b585081d7a9ea05df9f66c936b2cdc7a3f8e0768f70341b10 |
| checkpoint-production | New; unmeasured | Unmeasured | Determined from complete future discovery after CVR-01 |

These are three measured predecessor partitions and one required new partition, not four executed future jobs. The heavy class is the actual `tests/Architecture/AuditOperationReadArchitectureTest.php`; its longest indivisible testcase took831.224429 seconds. The total of rounded testcase values is1568.458463 seconds, while JUnit's suite value is1568.458437; this26-microsecond aggregation difference is harmless rounding, not missing coverage. The retained terminal receipt records exit0 and1572.523063 wall seconds. Its terminal log reports307.00MB, one deprecation, two PHPUnit deprecations and13 PHPUnit notices. This is inherited evidence, not a new run or a warning-free claim.

Retained discovery hash is `f1bcd4f51df140845b6b0c1aeb8925cbe809aaf84864100eeabb2d9b8b8e7650`; JUnit hash is `efbe6ae85b2aab8734fba8877e12531611aac37448894cfccf238035639784f8`; terminal log hash is `45d0095571cad13fcd2537c69c803fc568e4cbe31c63b0817097a42034e645ad`.

The only permitted predecessor identity rename is `BlackOps\Tests\Internal\Projection\Command\CommandProjectionTargetRegistryTest::testFrameworkNamesAreTheCurrentTwentyNineOnly` to the same class's `testFrameworkNamesAreTheCurrentThirtyOnly`. It is not a general rename allowance. New methods in predecessor classes stay in their original partitions; current unfiltered discovery remains the coverage denominator.

Actual accepted `TextUI/TestSuiteFilterProcessor.php` ignores an empty ID-filter list. Therefore the planner's empty-file rejection must happen **before** PHPUnit; otherwise an empty partition could accidentally run the full suite. Actual `TextUI/Application.php` applies the filter for list/XML commands, and `Runner/Filter/TestIdFilterIterator.php` compares exact event IDs. The proposed unfiltered discovery → fixed plan → filtered discovery comparison → selected execution → JUnit/terminal-exit verification is supported by the locked source. An actual terminal failure, skip, incomplete/risky result or missing log cannot be rescued by a matching XML subset. Final local unpartitioned full512M qualification remains mandatory.

## 5. CI identity and complete aggregation retained

The existing `.github/workflows/ci.yml` has job id `quality`, check name `Mago, PHPUnit, and Deptrac`, and30-minute timeout. The candidate preserves that exact required-check identity as an always-running aggregate and keeps all original quality commands in explicit producer jobs. Existing checkout@v7, strict root/example Composer validation, all four Mago commands, Deptrac, package export, version baseline and checked Compose cleanup remain. The native/vector additions do not remove them. The accepted docs workflow supplies the proposed upload-artifact@v7/download-artifact@v8 precedent.

The five exact `needs` are `quality-static`, `quality-package`, `quality-phpunit`, `quality-vectors` and `audit-authority-native`. They must each be literal success. All eleven exact producer artifacts are required from this same workflow run and checked-out SHA:

```text
quality-static
quality-package
quality-vectors
quality-phpunit-legacy-heavy
quality-phpunit-legacy-architecture
quality-phpunit-legacy-runtime
quality-phpunit-checkpoint-production
audit-authority-native-io
audit-authority-native-durability
audit-authority-native-interruption
audit-authority-native-cold
```

The contract rejects failure, cancellation, skipped/missing/nonterminal jobs, artifacts, cases or exits; wrong SHA, wrong inventory, duplicates and missing cleanup likewise fail. Always-uploaded failure diagnostics do not constitute a qualified receipt. Download directories remain separate; no latest-run lookup or receipt-file merge is allowed. Matrix fail-fast is false, but aggregate success still requires all rows. The fixed result recorder reads actual separate command exits/logs; it does not execute arbitrary caller commands or accept a caller success boolean. Raw management-ID `rg` qualifies only on exit1 plus empty output, all ordinary successful command entries require0, and a wrapper using `tee` retains `pipefail`.

The unchanged unrelated jobs, triggers, permissions and concurrency remain outside this change. Node has ten literal targets under the pinned24.18.0/pnpm11.12.0 environment; independently specified vectors cannot derive expected results from current PHP output. The static check reproduced five needs, eleven artifacts and ten targets. These are verified contract/source relationships, not remote same-SHA CI proof. Parent006's wider release/export requirements remain.

## 6. Actual production, native faults and resource limits retained

Binder §§2–4 and8 assign concrete P/D/G test paths and fixtures rather than a mock-success harness. Its28 original new PHP paths,25 bounded existing test paths,14 new fixtures/tools and3 existing tooling changes are literal; CVR-01 makes the new-PHP set35. The fixture's Application is actually configured and compiled, and real Operation metadata/policy/evaluator/HandlerInvoker/C3/F2 paths remain required. Selection, same-Connection ownership, denied preparation, held unavailable continuation, bootstrap/root cutover/replay, native constraints and immutability, bounded-memory uncapped history, exact current endpoint checks, concurrent winners and ambiguous commits all retain named owners. This review does not turn those future tests into implemented evidence.

The D response-loss fixture has a meaningful native contract: configure the synthetic application's existing DB host/port seam to its owned loopback proxy, forward the actual selected COMMIT, observe server `CommandComplete COMMIT` and `ReadyForQuery`, withhold the reply from the product client, and independently prove the precise committed row through another native observer. Fixed barriers select the phase; sleep-only races, arbitrary query counts, fake Connection success, rollback/error, unsupported framing or unhit/extra injection fail. C3 requested and unavailable delivery uncertainties are separate cases, along with F2/F5 commits. Original failure, graph poisoning, no later follow-up and fresh-graph recovery remain required. No Product proxy hook is introduced.

The two resource lanes remain distinct. Root alone authorizes the future exclusive local PostgreSQL lane; CI's four PHP rows each have an independent runner and database. No child inherits a live connection. Each fixture owns actual listeners, sockets, child/backend PIDs, handshakes, rollback/close and exact schema/gate cleanup. Observations are collected before deletion, and cleanup failure denies qualification. A deadline is not permission to leave a child or backend running.

The native inventory is exactly:

| Suite | Bases | Variants per base | Runs |
| --- | ---: | --- | ---: |
| io | 7 | control/exercise | 14 |
| durability | 9 | control/exercise | 18 |
| interruption | 9 | control/exercise | 18 |
| cold | 2 | initialized/missing/late/empty-warmup | 8 |
| Total | 27 | Fixed, no extra-success fallback | 58 |

Every run uses a fresh disposable container pinned to the actual inspected CI/local image; historical calibration is not a substitute. The contract retains network-none, read-only root, uid/gid1000, no new privileges, all capabilities dropped, pids32 and768MiB container memory/swap while PHP remains512M. It mounts only the enumerated read-only source/vendor/fixture inputs plus owned output, with separate bounded private tmpfs and a dedicated executable build tmpfs. No repository root, `.env`, DB socket/credentials or live seed mount is permitted. Missing compiler, incompatible image, loader failure or an unreached native route fails qualification rather than skipping. `LD_PRELOAD` belongs only to the owned fault child, not Composer, Mago, the supervisor or D lane. Explicit close/destructor and supported versus unsupported masked-close semantics remain intact.

The fixed8-second handshakes,15-second primitive bound and60-second outer native case bound fit a maximum18-case row into1080 seconds before setup. This is a finite planning bound, not evidence that future setup or the new PHP partition will finish on a CI host. Static/package/PHP/native/aggregate jobs retain30 minutes; vectors20. The longest measured predecessor case remains intact,512M is unchanged, and no retry-until-green or larger timeout is authorized. A future over-budget job fails and requires measured, separately reviewed repartitioning, not omissions.

Combined candidate override4 correctly replaces the resource draft's phrase “reverse teardown”: the reserved production migration's `down()` is **irreversible**, as composition §7 requires. Fixture-owned schema teardown is a separate responsibility, not a reversible Product migration. That concrete contradiction is already resolved by the combined override and is not a third finding.

## 7. Checks, acceptance boundary and next action

Checks performed here were read-only `rg`/targeted source reads, Python standard-library JSON/XML/path-set bookkeeping, and SHA-256 before/after recording. Actual PHPUnit filter/list/JUnit implementation and current workflow/Compose/Docker sources were inspected, not executed. The initial bookkeeping lookup omitted the XML namespace and found zero classes; it was corrected to the declared namespace before any coverage conclusion. The first final hash assertion also correctly stopped on Root's concurrent two-STATE update, before writing the freeze; the new checkpoint was then read and its exact deltas recorded. These are bookkeeping diagnostics, not failing Product tests. The final XML/partition check is retained in the input JSON.

This lane ran no Git, PHP, Node, test runner, database, native child, compiler, Docker/container or network command, opened no lease, and wrote no Product/test/workflow/migration/governance/STATE/public document. The two ignored review files are the complete changed-file set. The final full path inventory was received before this freeze and its35-test union was compared, but its unresolved authority/type/parser issues are not accepted by this verification review.

Review acceptance is limited to: (a) CVR-01 and CVR-02 are open, concrete correction requirements; (b) the source-backed predecessor identity, CI identity/aggregation, actual-production fixture intent and containment conclusions above remain unchanged; (c) tests and native qualification remain **Not Run**. No Green issuance recommendation, F5 Acceptance, commit, push, export, deployment, Required accessed release, D acknowledgement, F4 completion or release readiness is claimed.

Release-documentation impact: these two ignored review artifacts alter no public API, command, capability, route, generated Search/LLM artifact or release tuple. Experimental Stable1.2.1 remains current and1.3 unreleased. The eventual Product packet still carries its named guide/internal-documentation and whole-public-source checks; review completion does not waive them.

Remaining work: close both verification findings and the separate authority/inventory findings; Root adopts and preserves the complete child packet; Luna implements the real prerequisite; actual strict/PHP/PostgreSQL/native/cold/CI/export qualification follows under fresh owned leases. F5 AC2–6, real F4/Required/current-trust/seal/D-control continuations and the remaining BlackOps1.3 release/documentation work stay open.

Next Action: Root assigns the original test/CI author a finite correction for CVR-01/CVR-02, preserving the frozen originals and all retained cases/limits. Return only the corrected path-admission and58-run executable mapping with input hashes for affected independent re-review. Combine that result with the authority review before issuing the complete worker packet.
