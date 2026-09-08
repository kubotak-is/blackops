# P23-003D2S1: Tenant-scoped Status Source

Status: Accepted — tenant-scoped source prerequisite only; D2S adapter remains separate.

## Goal

Deliver a latent tenant-scoped Status-source path before the D2S audit adapter.
Preserve existing public API and legacy unscoped runtime behavior. All new
subject/detail SQL must bind exact Operation ID and nullable tenant identity;
no foreign data is decrypted or decoded. The user approved this separate
prerequisite with continuation after the D2S scope-expansion question.

## Authority Preparation

Specification108 and D157 will fix the source API, SQL, snapshot, failure and
test contract. The earlier spec107/D156 three-type adapter proposal remains
incomplete and is not implementation authority. No worker may implement this
Task until its full packet is independently reviewed and the Orchestrator
explicitly dispatches the configured Luna worker.

Orchestrator may prepare this Task/Report, spec108/D157, STATE, spec/README,
TODO and update only the workflow headers in the existing D2S Task/Report,
spec107/D156. Production scope and exact commands will be sealed below before
dispatch. No push, runtime activation, public release or deployment.

## Next Action

Implementation and the amended Final coverage are Accepted. Complete the
reviewed local checkpoint and clean final-SHA export, then prepare the separate
D2S adapter authority. Current user-prioritized documentation work is in STATE.

## Reviewed Recovery Amendment — 2026-09-08

This amendment implements the user's development-efficiency direction and
spec109 after P23-007 Acceptance. It supersedes the old exact19/worker-STATE
ownership, blanket invalidation, repeated Iteration at Final, and all-lane
restart rules below for this bounded recovery only. No Product contract,
fixture outcome, required Final command coverage, or same-SHA export is waived.
The old gate text remains the historical command inventory.

The full PHPUnit run failed at the 128MiB limit. A read-only probe measured
13 retained reads of all1048 Production PHP files: 48,103,128 additional bytes,
versus 4,748,360 bytes when sharing one baseline with separate copy-on-write
candidate maps. This establishes avoidable retention; the full suite still
must demonstrate that the correction resolves the actual failure.

Luna owns only `tests/Architecture/TenantScopedStatusArchitectureTest.php`.
Reuse one method-local Production source baseline, preserve independent
mutation candidates and every existing negative/positive assertion. Reduce
redundant parsing only by reusing equivalent local parse results; do not
filter the source inventory, memoize across test runs, skip tests, change the
oracle's accepted language, or raise the memory limit. Preserve private helper
behavior used by existing independent probes. No dependency/config change.
Other Product/Test9 and all authority except this explicit Task amendment
remain unchanged. Root owns this Report, Task, STATE/archive and verification.
Worker returns a chat handoff and never writes STATE, stages or commits.

The current workspace is the union of the original D2S1 reviewed scope and
the independently Accepted P23-007 scope in its Task/Report. Before dispatch,
Root records every dirty path and hash (including existing archives), HEAD,
branch, staged0, Product10, authority10 and tool/config/lock inputs in an
external receipt. During worker work only the single Test may change.
At Root checkpoints only the D2S1 Task/Report, STATE and the exact old-STATE
archive created by state.mjs may additionally change. All other P23-007 bytes
are preserved, including its tooling, history and profiles. Guards compare
these actual inventories instead of silently accepting an arbitrary count.

Root is the sole Docker/PostgreSQL verification owner; no concurrent suite.
Luna may run syntax checks and must report them. General independently reviews
this amendment, the Test delta, fixture preservation and results. Existing R5
Transaction and Documentation Greens carry forward only after Root verifies
unchanged Product9, public sources, release boundary and relevant authority;
any semantic change there reopens the affected lane. General's original
actual-oracle probes are re-executed against the corrected Test hash.

| Evidence | Recovery action and invalidation rule |
| --- | --- |
| Architecture Test | Run the five-test file once at 128MiB for bounded correction feedback; run the existing 7/8/13 independent controls with the new hash. Changed oracle/fixtures invalidate these results. |
| PHP format / forbidden IDs | Run original global format and ID commands on the corrected PHP inputs. |
| Mago lint / baseline / analyze; Deptrac | Reuse Final steps5..8 only after source, included dependency, config, lock and runtime/image inputs match; otherwise rerun the affected command. This Test is outside their configured source scope. |
| Inventory / version / shell syntax / Composer strict | Reuse Final steps9..12 and15 with matching scripts, release inputs, Composer manifests/lock and tools; rerun if those inputs or proof are missing. |
| Full PHPUnit | Run the original Final command once on the corrected candidate at the unchanged 128MiB limit. This covers the same-condition Status105 subset; do not repeat that subset at Final. The prior full run remains Failed. |
| Six Audit goldens / community clean install | Run every previously unrun literal Final command below. Recheck consumer cleanup targets before executing. |
| Preservation / completion | Run diff, source/authority/scope guards and STATE check at relevant boundaries. Keep clean final-SHA export after the authorized reviewed local commit. |

Record literal commands, exits/counters, duration, input hashes, environment,
logs, reuse sources and any diagnostics in the Report. Failure resumes from
the affected command and dependent checks after correction, with successful
unchanged evidence retained. Acceptance requires the union of current and
explicitly reused evidence for every original required check. No push/deploy.

### Completion-only management handoff

After all checks and review closure, Root may synchronize only current Status,
handoff, links and next actions in this Task/Report, STATE/archive, spec README,
TODO, D154/D155, the D2S Task/Report, spec107/D156 and P23-007 Task/Report.
Keep the historical bodies and Product contracts unchanged; label the old
adapter proposal as still incomplete and require a new reviewed adapter packet.
This is a completion exception to the recovery freeze above, reviewed on its
actual diff before committing; it does not reopen source verification.

Root may include the independently Accepted P23-007 changes in a separate
reviewed local workflow checkpoint before the accepted D2S1 candidate commit,
so the final package export has a clean source tree. P23-007's earlier
commit exclusion described that implementation Task's boundary; this explicit
closeout covers the local checkpoint only. Preserve the reviewed combined
scope, run final-SHA Git/Composer export, and do not push/deploy/release.

## Source of Truth and Model

Read AGENTS -> STATE -> this Task -> spec/README -> spec108 -> spec99/105/106
relevant Status/tenant/release boundaries -> D157/D154/D155 and the prerequisite
D2 Report. Spec108 is the most-specific source contract. Profile files remain
authoritative: worker gpt-5.6-luna/max, General/Transaction gpt-6-astra/max,
Documentation gpt-6-astra/max. Explicit rejected/unavailable/fallback profile
is a blocker; metadata absence alone is not.

## User-approved Resume Reconciliation — 2026-09-07

The user approved adopting the concurrent AGENTS Astra/max change, aligning
the actual profiles, and synchronizing the three stale handoff documents.
Orchestrator-only metadata scope is exactly these seven paths in addition to
the original worker12; it does not expand Product/Test10:

```text
AGENTS.md
.codex/config.toml
.codex/agents/documentation-reviewer.toml
develop/orchestration/tasks/P23-003D2S1-tenant-scoped-status-source.md
develop/spec/README.md
develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md
develop/decisions/155-operation-read-audit-session-foundation.md
```

Only model/profile bookkeeping and current/historical handoff labels may
change; source contracts, acceptance requirements and literal gate commands
remain fixed. The original clean authority preflight already occurred at
8f8674011f22a99f230431e47606a489f1c06abc; this is an in-place correction
resume, not initial implementation dispatch. Before further worker changes,
independently review this reconciliation and supply fresh authority10 plus
metadata7 hashes. Those bytes then become immutable during worker work.
Until a reviewed metadata checkpoint is committed, gate scope is exact19
(added6/modified13), staged0, with original worker12 plus the exact seven
paths above. This explicit exception supersedes only the old exact12 scope
guard; all other before/after invariants remain required. Rebuild guards
after any checkpoint commit and label older whole-tree evidence Historical.

New reviewers must start with Astra/max; earlier Sol reviews are historical
and cannot be relabeled. Specification92's read-only review contract remains
unchanged; its older model/effort labels are superseded by the repository
profiles and this approved handoff, not evidence of a running-thread upgrade.
Worker remains Luna/max. No push, deployment or runtime activation.

## Product Prerequisites

- D2 final SHA `0cd699e5e1d031228bc4a05767164a8eb268a004` is an ancestor of
  the Orchestrator-supplied authority checkpoint; branch remains
  `agent/p23-001-blackops-1-3-feasibility`.
- General, Transaction and Documentation authority reviews separately Green,
  and the Orchestrator explicitly dispatches implementation.
- The eleven-path management preparation is reviewed before any local
  authority checkpoint commit. No unreviewed management bytes enter that commit.
- Worker starts from the supplied clean HEAD/staged0, exact individual hashes
  for existing Product paths, and the ordered authority hash inventory below.
  Hashes are supplied out of band to avoid self-reference. Drift stops work.
- Stable1.2.0; unreleased1.3; Framework13/Community5 migrations; StoragePurpose9;
  required package inventory85 before this Task.

## Files Allowed — Product/Test Exact10

```text
src/Internal/Status/TenantScopedOperationStatusSource.php
src/Internal/Status/TenantBoundOperationStatusSource.php
src/Internal/Status/PostgreSqlOperationStatusSource.php
src/Transport/PostgreSql/PostgreSqlStatusReader.php
tests/Internal/Status/TenantBoundOperationStatusSourceTest.php
tests/Transport/PostgreSql/PostgreSqlTenantStatusReaderTest.php
tests/Transport/PostgreSql/PostgreSqlTenantStatusQueryIntegrationTest.php
tests/Architecture/TenantScopedStatusArchitectureTest.php
tests/Consumer/framework-package-export.sh
tests/Consumer/framework-package-required-inventory.sh
```

Added6/modified4. Worker management paths are exactly:

```text
develop/orchestration/reports/P23-003D2S1-tenant-scoped-status-source.md
develop/STATE.md
```

Worker scope exact12. No new helper file, test fixture file, migration,
Dependency/config/baseline/threshold change or public source is allowed.
Helper test classes belong in the permitted new test files. If scope is
insufficient, return a bounded blocker; do not edit another path.

## Immutable Authority Inventory

```text
develop/orchestration/tasks/P23-003D2S1-tenant-scoped-status-source.md
develop/spec/108-tenant-scoped-status-source.md
develop/decisions/157-tenant-scoped-status-source.md
develop/spec/99-tenant-isolation-and-protected-operation-data.md
develop/spec/105-blackops-1-3-capability-contract.md
develop/spec/106-phase-23-delivery-plan.md
develop/decisions/147-audit-catalog-and-safe-projection-boundary.md
develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md
develop/decisions/155-operation-read-audit-session-foundation.md
develop/orchestration/reports/P23-003D2-operation-read-audit-session-foundation.md
```

All other existing paths are immutable during worker implementation as well.
In particular keep Audit Product/Test, existing Status tests, public
Status/HTTP, Default Query, Journal/Outcome Stores, schemas, and composers
unchanged. Reuse their APIs instead of repairing them in this Task.

## Implementation Requirements

Follow spec108's exact ports, marker, scoped SQL and snapshot protocol. Unit
tests must preserve original results/exceptions through the bound view. Real
PostgreSQL tests must observe query parameters, not only inspect source text.
No plain `?TenantRef=null` sentinel for unscoped private calls; use the explicit
closed marker. Thread scope through deferredOutcome and its hidden Outcome
read. No mutable per-query state or duplicated lifecycle algorithm.

SQL and decode fixtures must cover all7 source-read paths: subject, deferred
state, Journal, purge evidence, Outcome existence, dead-letter existence,
Outcome fetch. Tests distinguish scoped-null, named scope and legacy-unscoped.
No own-scope result may include a foreign-row decode or foreign existence probe.

For package85->87, preserve old85 order; insert the two new type paths after
UnavailableOperationDiagnosticsAuthorizer in interface/view order. Existing31
negative controls remain; add two individual new-path omissions, reverse-new-
block and duplicate-new-entry negatives, making35 total. Independently verify
the two inventories before updating the exact export-script SHA pin.

## Iteration Gate

Run from the Repository root and record literal commands, exits and counters:

```bash
git diff --check
docker compose run --rm app mago format --check src tests
docker compose run --rm app php vendor/bin/phpunit tests/Internal/Status tests/Status tests/Http/Status tests/Transport/PostgreSql/PostgreSqlStatusReaderTest.php tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php tests/Transport/PostgreSql/PostgreSqlTenantStatusReaderTest.php tests/Transport/PostgreSql/PostgreSqlTenantStatusQueryIntegrationTest.php tests/Architecture/TenantScopedStatusArchitectureTest.php
docker compose run --rm app mago lint
docker compose run --rm app mago lint --verify-baseline
docker compose run --rm app mago analyze --minimum-fail-level error --minimum-report-level error --reporting-format count
docker compose run --rm app php vendor/bin/deptrac analyse --config-file=deptrac.yaml --report-uncovered --fail-on-uncovered
bash tests/Consumer/framework-package-required-inventory.sh
bash tests/Consumer/version-baseline.sh
bash -n tests/Consumer/framework-package-required-inventory.sh
bash -n tests/Consumer/framework-package-export.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Before/after: exact12 scope with added6/modified6 (including Report/STATE),
staged0, unchanged HEAD/branch, immutable authority and non-scope bytes, exact
public-source no-diff, package87/35, migrations13+5, StoragePurpose9 and zero
Production view/entry activation. A management-only checkpoint does not
invalidate unchanged Product tests; changed Product/Test bytes invalidate prior
formal evidence and restart the Gate after correction.

## Complete Final Gate

After fresh General/Transaction/Documentation implementation reviews separately
Green, freeze unchanged Product/Test and repeat every Iteration command from
the beginning, plus all following commands before final preservation checks:

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app php vendor/bin/phpunit --display-warnings
node tests/Fixtures/Audit/audit-canonical-record-golden.mjs
node tests/Fixtures/Audit/audit-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-operational-event-catalog-golden.mjs
node tests/Fixtures/Audit/audit-postgresql-store-golden.mjs
node tests/Fixtures/Audit/observed-audit-record-golden.mjs
node tests/Fixtures/Audit/audit-operation-read-foundation-golden.mjs
bash tests/Consumer/community-board-clean-install.sh
```

Validate each command path before dispatch; missing command is an authority
error, not permission to silently omit a gate. No package export while dirty.
Record non-failing PHPUnit notices/deprecations without claiming zero issues.
No browser/generated documentation/CI proof can be inferred from these tests.

## Acceptance

- All scoped reads enforce Operation+tenant predicates including explicit-null.
- Full snapshot/race/before-decode and result/error/legacy matrix Green.
- Exact source/API/mutation/no-activation/package87/35 evidence Green.
- Iteration, three independent reviews and Complete Final Green on unchanged
  Product bytes; Report/STATE accurate and scope/authority preserved.
- Orchestrator accepts this prerequisite alone, not D2S or parent D.

Worker does not stage/commit/push. After Acceptance the Orchestrator may commit
only the reviewed candidate, confirm clean same-SHA and run
`bash tests/Consumer/framework-package-export.sh`. Record Git/Composer inventory
and autoload proof; no push/deploy/release. Completion-only bookkeeping may
be folded into its own unpublished commit followed by final amended-SHA export;
never describe pre-amend proof as final-SHA evidence.

Remaining after Acceptance: revise the D2S adapter authority around this source,
then adapter implementation/gates/Acceptance, other reader children and parent
D integration. D-control remains dependent on Accepted F.
