# P23-006C: Accepted Audit integration through native Replay outcomes

Status: Accepted locally — isolated through-O1 integration verified
Parent: [P23-006](P23-006-blackops-1-3-release.md)

## Goal and Authority

Integrate the six reviewed units through locally Accepted O1 into a separate WSL
checkout, preserving the primary mixed worktree, index and branch. This executes
parent006's authorized commit separation; it does not release1.3, activate Audit
composition or qualify the later O2/F1 implementation. Specification109 and the
parent release gates apply. The reviewed granularity decision b1fe7a47 preserves
child attribution without inventing unavailable standalone historical endpoints.
006A recovered the two exact D5H guards. Accepted006B supplies newly constructed
and independently reviewed97-path/48-control package scripts for the first unit.
Do not describe those two files as historical byte recovery.

Root owns Git, import, management reconciliation, all checks, Report/STATE and
Acceptance. No new Product implementation is authorized. Any required semantic
Product/test repair returns to a separately scoped Luna assignment. An independent
read-only reviewer checks exact final convergence, attribution and preservation.
The three existing O2/F1 Luna owners keep their primary-only scopes and resources.

## Frozen inputs and isolation

Base: `8106c3556248dca1f9719b9d55cf1f647255a422`.
Published Reel: `a419f375bdc60a2836ea8742fc45d67e38bde27f`.
Remote main was confirmed at that object during issuance; check it again before
integration. A changed remote input requires bounded diff review and an explicit
Task amendment, never a silent substitution.

Use branch `agent/p23-006-accepted-integration` and worktree
`/tmp/blackops-integration-20261001/candidate`. Do not reuse or clean the existing
Reel checkout. Merge the published object before Audit imports. Preserve its
player, render tools, assets, package lock, tests and presentation specifications;
primary's old Documentation is not an overlay source. Resolve only management
conflicts by retaining both current framework authority and published Reel facts.
Any non-management merge conflict requires inspection and bounded assignment.
For STATE conflict resolution, select the intact HEAD checkpoint first, then
write the current summary through the CAS tool. Preserve raw conflicted merge
bytes as temporary evidence outside the deliverable; they are not valid STATE
history. No committed archive may be edited or removed.

Exact Product provenance is the adopted manifest
`/tmp/blackops-1-3-release/integration-import-manifest-ready.json`, SHA256
`e323e929ad558c1290cf70724a7905dac12d65eecaf23cbff846942dc514e79c`.
All94 unit endpoints over81 distinct paths were rehashed at issuance. Management
inputs are captured separately in `/tmp/blackops-integration-20261001/issuance-inputs.json`,
SHA256 `81b5a6d3d5e231f11e801fe0893522ef5ac0f330f55c3bf86ea6763a5667e582`:
40 child documents and six shared input files. Rehash every payload and mode at
copy; preserve the source evidence. Reject concurrent changes or unexpected paths.
No old STATE or broad mixed patch is replayed.

## Exact per-unit files

Each numbered unit is a separate Product commit. Its four-per-child Task,
Report, specification and Decision documents may be in that commit or a preceding
explicit authority commit. Shared summary reconciliation is separate from D5P.
The following Product/test/configuration paths are exhaustive; only the selected
per-unit payload, hash and mode in the manifest may be imported.

### 1. D2J+D2X (25 paths)

- `src/Internal/OperationData/AuditedOperationJournalQuery.php`
- `src/Internal/OperationData/AuditedOperationOutcomeQuery.php`
- `src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php`
- `src/Internal/OperationData/OperationJournalReadExecutionSource.php`
- `src/Internal/OperationData/OperationOutcomeReadExecutionSource.php`
- `src/Internal/OperationData/DefaultOperationJournalQuery.php`
- `src/Internal/OperationData/DefaultOperationOutcomeQuery.php`
- `src/Internal/OperationData/PostgreSqlTenantScopedCanonicalJournalReader.php`
- `src/Internal/OperationData/PostgreSqlTenantScopedOutcomeReader.php`
- `src/Transport/PostgreSql/PostgreSqlOutcomeCodec.php`
- `tests/Internal/OperationData/AuditedOperationDataQueryTest.php`
- `tests/Internal/OperationData/OperationDataAuditTenantAuthorizerTest.php`
- `tests/Internal/OperationData/OperationDataReadExecutionSourceTest.php`
- `tests/Architecture/AuditedOperationDataArchitectureTest.php`
- `tests/Architecture/AuditOperationReadArchitectureTest.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedOperationDataQueryTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`
- `phpunit.xml`
- `src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php`
- `src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php`
- `tests/Internal/Diagnostics/AuditedOperationDiagnosticsQueryTest.php`
- `tests/Internal/Diagnostics/OperationDiagnosticsReadExecutionSourceTest.php`
- `tests/Architecture/AuditedOperationDiagnosticsArchitectureTest.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedOperationDiagnosticsQueryTest.php`

### 2. D3+D4A+D4B+D5H (35 paths)

- `src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php`
- `src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php`
- `src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php`
- `tests/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriterTest.php`
- `tests/Internal/Audit/Authentication/AuditedHttpAuthenticatorTest.php`
- `tests/Architecture/AuthenticationAuditArchitectureTest.php`
- `tests/Architecture/AuditDeliveryArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`
- `tests/Transport/PostgreSql/PostgreSqlAuthenticationAuditTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`
- `src/Internal/Audit/Authorization/AuthorizationAuditPolicyBinding.php`
- `src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php`
- `src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php`
- `src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php`
- `tests/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistryTest.php`
- `tests/Internal/Audit/Authorization/AuthorizationAuditEvaluatorTest.php`
- `tests/Architecture/AuthorizationAuditArchitectureTest.php`
- `src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php`
- `src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php`
- `src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php`
- `src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php`
- `src/Internal/Transaction/OperationTransactionCoordinator.php`
- `tests/Internal/Audit/Authorization/AuthorizationAuditEventFactoryTest.php`
- `tests/Internal/Audit/Authorization/AuthorizationAuditCoordinatorTest.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Execution/DeferredAcceptanceOrchestrator.php`
- `src/Internal/Execution/DeferredWorkerRuntime.php`
- `tests/Transport/PostgreSql/PostgreSqlAuthorizationAuditRuntimeIntegrationTest.php`
- `mago-lint-baseline.toml`
- `src/Internal/Audit/Retention/RetentionHoldAuditWriter.php`
- `tests/Internal/Audit/Retention/RetentionHoldAuditWriterTest.php`
- `tests/Architecture/RetentionHoldAuditArchitectureTest.php`
- `src/Internal/Retention/AuditedRetentionHoldPort.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedRetentionHoldPortTest.php`

### 3. D5P (11 paths)

- `src/Internal/Audit/Retention/RetentionPurgeAuditWriter.php`
- `tests/Internal/Audit/Retention/RetentionPurgeAuditWriterTest.php`
- `tests/Architecture/RetentionPurgeAuditArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`
- `tests/Architecture/AuditDeliveryArchitectureTest.php`
- `tests/Architecture/RetentionHoldAuditArchitectureTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`
- `src/Internal/Retention/AuditedRetentionPurgeAuditPort.php`
- `src/Internal/Retention/AuditedRetentionPurgeService.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedRetentionPurgeServiceTest.php`

### 4. D5R1 (3 paths)

- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php`
- `tests/Transport/PostgreSql/PostgreSqlStorageProtectionRotationTransactionOutcomeTest.php`

### 5. D5R2 (17 paths)

- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotation.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationEngine.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationBatch.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowExecutor.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationRowTransaction.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationAudit.php`
- `src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationPhaseHook.php`
- `src/Internal/Audit/Rotation/StorageRotationAuditHook.php`
- `src/Internal/Audit/Rotation/StorageRotationAuditWriter.php`
- `tests/Internal/Audit/Rotation/StorageRotationAuditHookTest.php`
- `tests/Internal/Audit/Rotation/StorageRotationAuditWriterTest.php`
- `tests/Transport/PostgreSql/PostgreSqlAuditedStorageProtectionRotationTest.php`
- `tests/Architecture/StorageRotationAuditArchitectureTest.php`
- `tests/Architecture/AuditRecordArchitectureTest.php`
- `tests/Architecture/AuditDeliveryArchitectureTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-package-required-inventory.sh`

### 6. D5O1 (3 paths)

- `src/Transport/PostgreSql/PostgreSqlObserverReplayStore.php`
- `src/Internal/Replay/ObserverReplayRuntime.php`
- `tests/Transport/PostgreSql/PostgreSqlObserverReplayTransactionOutcomeTest.php`

## Exact management ownership

Primary changes are limited to this Task/Report, parent006 Task/Report, the two
active O2/F1 Task/Reports to record parallel ownership, and STATE plus newly
created CAS archives. Root adds only this Task/Report to the existing primary
scope-checker governance list; the52 Product candidates stay fixed. Temporary
integration scripts/manifests/receipts stay under the declared `/tmp` directory.

In the isolated checkout the exact per-child management files are:

- `develop/orchestration/tasks/P23-003D2J-operation-data-audit-adapters.md`
- `develop/orchestration/reports/P23-003D2J-operation-data-audit-adapters.md`
- `develop/spec/111-operation-data-read-audit-adapters.md`
- `develop/decisions/160-operation-data-read-audit-adapters.md`
- `develop/orchestration/tasks/P23-003D2X-operation-diagnostics-audit-adapter.md`
- `develop/orchestration/reports/P23-003D2X-operation-diagnostics-audit-adapter.md`
- `develop/spec/112-operation-diagnostics-audit-adapter.md`
- `develop/decisions/161-operation-diagnostics-audit-adapter.md`
- `develop/orchestration/tasks/P23-003D3-http-authentication-audit-adapter.md`
- `develop/orchestration/reports/P23-003D3-http-authentication-audit-adapter.md`
- `develop/spec/113-http-authentication-audit-adapter.md`
- `develop/decisions/162-http-authentication-audit-adapter.md`
- `develop/orchestration/tasks/P23-003D4A-authorization-audit-evaluation.md`
- `develop/orchestration/reports/P23-003D4A-authorization-audit-evaluation.md`
- `develop/spec/114-authorization-audit-evaluation-foundation.md`
- `develop/decisions/163-authorization-audit-evaluation-foundation.md`
- `develop/orchestration/tasks/P23-003D4B-authorization-audit-delivery.md`
- `develop/orchestration/reports/P23-003D4B-authorization-audit-delivery.md`
- `develop/spec/115-authorization-audit-delivery.md`
- `develop/decisions/164-authorization-audit-delivery.md`
- `develop/orchestration/tasks/P23-003D5H-retention-hold-audit-adapter.md`
- `develop/orchestration/reports/P23-003D5H-retention-hold-audit-adapter.md`
- `develop/spec/116-retention-hold-audit-adapter.md`
- `develop/decisions/165-retention-hold-audit-adapter.md`
- `develop/orchestration/tasks/P23-003D5P-retention-purge-audit-adapter.md`
- `develop/orchestration/reports/P23-003D5P-retention-purge-audit-adapter.md`
- `develop/spec/117-retention-purge-audit-adapter.md`
- `develop/decisions/166-retention-purge-audit-adapter.md`
- `develop/orchestration/tasks/P23-003D5R1-storage-rotation-transaction-outcomes.md`
- `develop/orchestration/reports/P23-003D5R1-storage-rotation-transaction-outcomes.md`
- `develop/spec/118-storage-rotation-transaction-outcomes.md`
- `develop/decisions/168-storage-rotation-transaction-outcomes.md`
- `develop/orchestration/tasks/P23-003D5R2-storage-rotation-audit-adapter.md`
- `develop/orchestration/reports/P23-003D5R2-storage-rotation-audit-adapter.md`
- `develop/spec/119-storage-rotation-audit-adapter.md`
- `develop/decisions/169-storage-rotation-audit-adapter.md`
- `develop/orchestration/tasks/P23-003D5O1-observer-replay-transaction-outcomes.md`
- `develop/orchestration/reports/P23-003D5O1-observer-replay-transaction-outcomes.md`
- `develop/spec/120-observer-replay-transaction-outcomes.md`
- `develop/decisions/170-observer-replay-transaction-outcomes.md`

Additional isolated management ownership is limited to:

- `develop/decisions/155-operation-read-audit-session-foundation.md`: D2J/D2X additions.
- `develop/spec/109-ai-development-workflow.md`: the accepted512M paragraph only.
- `develop/spec/06-auth-and-middleware.md`: the accepted D4A Received-order correction.
- `develop/decisions/154-framework-operational-audit-catalog-and-adapter-split.md`: named child pointers through O1; exclude the later O2 amendment.
- `develop/spec/README.md`: only these child specification/Decision rows, retaining published Reel rows.
- `develop/TODO.md`: current integration status and retained child/source attribution.
- This Task/Report, parent006 Task/Report and Accepted006A/006B Task/Reports.
- `develop/STATE.md` and exact newly generated CAS archives: a current checkout summary linking existing Task/Report paths.

Do not copy primary public Source, release-authority.json, older Reel management,
old STATE snapshots, unrelated chmod changes, O2/F1 code or later proposed scopes.
Primary's all1693 selected runtime-file bytes can be composed from the base and
these endpoint payloads. Twelve pre-existing mode-only differences outside the
81 paths remain primary residuals; do not import them or claim whole-mode equality.
The final series must retain exact recorded modes for all81 imported paths.

## Verification Plan

Root records literal commands, times, elapsed seconds, input hashes/modes,
exit/results and logs under `/tmp/blackops-integration-20261001/`.

1. Capture primary HEAD/branch, index stage/flags/patch and whole tracked/untracked
   file maps. Existing O2/F1 candidate and explicit governance changes are allowed;
   no other bytes/modes may change. Validate the adopted manifests and all94
   payloads. Check both R1 original native hashes against the actual base objects.
2. Check current remote/main identity, create the isolated worktree and merge the
   immutable Reel ref. Inspect staged conflicts/diff; preserve published Source
   bytes and its35-path merge contribution. Current STATE uses the CAS tool and
   is never replaced by an old child summary. Stage only explicit paths.
3. For each unit, compare its preimage with the previous unit/base, copy only its
   immutable payloads/modes, review complete staged diff and attribution, check
   expected file list and `git diff --check`, then commit with an accurate scope.
   No commit message claims a missing historical endpoint or independent full.
   D5P must contain no `docs/` or public Documentation/Release Authority change.
4. After the series, compare every final81 destination byte/mode to the selected
   endpoints, every remaining runtime byte to base/O1 input, and all published
   Reel Source bytes to the merge ref. Identify mode-only inherited residuals
   separately. Reuse unchanged child source checks and Accepted006B inventory
   evidence by matching their inputs; a commit alone does not invalidate them.
5. Run actual final package inventory in the isolated checkout, `bash -n` for
   both scripts, `git diff --check`, STATE check and primary preservation check.
   The selected final R2 package contract is115 ordered unique paths/66 negative
   controls; verify the precise existing contract before execution. This is an
   integration execution, separate from historical artifact validation.
   Do not run archive/export from a dirty tree or pretend local copies are a
   committed-SHA package. Parent006 owns final same-SHA export and CI.
6. Independent read-only review checks the six actual commits, management/source
   attribution, new construction provenance, final convergence and preservation.
   Root closes findings and records refs. Unchanged PHP source/test/config bytes
   retain their earlier local evidence. A new semantic Product/test/merge change
   requires a bounded Luna Task plus affected checks, not a silent repair or
   waiver. No PHP test, DB, cache, generation or Docker resource runs concurrently
   with the primary owners. Final release qualification remains parent006's full
   mandatory gate set against the completed candidate.

## Acceptance Criteria

- [x] All94 payload endpoints are verified; the series has the six declared units
  and each final81 path equals its selected accepted payload and recorded mode.
- [x] Published Reel is merged and retained; D5P and every public Documentation
  change remain separate; no old STATE or stale player is replayed.
- [x] All management additions preserve child attribution and release boundaries;
  the97/48 construction is identified honestly and no prior full is fabricated.
- [x] Actual isolated final inventory/syntax, whitespace/STATE and primary
  preservation checks pass; no unassigned path or index/HEAD mutation occurs.
- [x] Independent convergence review is Green, isolated checkout is clean,
  resulting refs/evidence and parent remaining work are recorded.

## Release Documentation Impact

No new public capability/API/command/configuration or release is introduced by
this import. Existing Experimental Stable1.2.1 authority remains;1.3 is unreleased.
The merge retains the already delivered Reel. No new Source/Search/LLM artifact,
CI, export, publication or deploy is claimed here. Primary residual Documentation
and all-public review, Owner final local preview, immutable publication and live
proof remain separate parent005/006 work.

## Remaining Work and Next Action

Complete at reviewed commit edb12f16e9961c9f10f7cebd61eaa1dd764ba9c3, with six
source units through18ce42c5 and separate Reel merge70f6193. Final reviewe0a9dd84
closes both editorial findings and verifies clean checkout/primary preservation.
Root records only a management Acceptance checkpoint afterward; Product and
prior evidence stay unchanged. Remaining child work: none. Next Action: continue
parent006's O2/F1, later implementation, Documentation/Owner preview and release.
