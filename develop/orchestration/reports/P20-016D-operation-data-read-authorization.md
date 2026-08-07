# P20-016D Operation Data Read Authorization — Completion Report

## Summary

Implemented tenant-aware Status authorization metadata and default-deny, typed Journal/Outcome application queries. Clear subjects are selected with the current tenant predicate before authorization; only an Allow decision permits tenant-scoped blob SELECT/decode. Unknown, cross-tenant, deny, and retention-empty reads return resource-specific Unavailable results. Authorizer, storage, protection, decode, and integrity failures remain stable safe exceptions.

Raw `CanonicalJournalReader` and `OutcomeReader` are no longer `PublicApi` contracts. Runtime containers expose only synthetic public authorized-query contracts; PostgreSQL operation/worker/command composition injects authorized query objects without raw-reader bindings.

## Changed Files

- `src/Status/**`, `src/Http/Status/**`, `src/Internal/Status/**`
- `src/OperationData/**`
- `src/Internal/OperationData/**`
- `src/Internal/Application/**`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Transport/PostgreSql/PostgreSqlStatusReader.php`
- `src/Journal/CanonicalJournalReader.php`, `src/Outcome/OutcomeReader.php`
- `deptrac.yaml`, `develop/spec/16-namespace-dependencies.md`
- Corresponding Status, OperationData, Application, DependencyInjection, Journal, Outcome, and PostgreSQL tests
- `develop/orchestration/reports/P20-016D-operation-data-read-authorization.md`, `develop/STATE.md`

## Decisions and Assumptions

- Status remains an unscoped subject projection; its authorizer receives current and origin tenant values for no-tenant, same-tenant, and cross-tenant policy decisions.
- Direct OperationData subject lookup is tenant-scoped in SQL (`IS NOT DISTINCT FROM`) and does not invoke the authorizer or blob reader for unknown/cross-tenant subjects.
- Default authorizer resolution is deny when no application binding exists.
- PostgreSQL authorized adapters separate SQL/storage failures from codec decode failures; query-level post-read validation maps metadata mismatches to integrity codes.
- Protection-specific query exceptions are preserved for the later protection adapter integration.

## Commands and Results

- Worker focused Status/HTTP/OperationData/DI/Journal/Outcome suite: PASS (114 tests, 494 assertions).
- Orchestrator focused suite: PASS (123 tests, 534 assertions).
- Full PHPUnit: PASS (2061 tests, 8254 assertions, 1 existing deprecation). The first run had one unrelated Outbox heartbeat timing failure; its isolated rerun and the stable-tree full rerun passed.
- `composer validate --strict`: PASS.
- `mago format --check src tests`: PASS.
- Task-scoped `mago analyze`: PASS with no issues. Broad analyze: exit 0, 11 warnings and 0 errors, all in existing non-P20-016D paths.
- Task-scoped `mago lint`: no P20-016D findings; the only reported item is an unchanged `RuntimeContainerCompiler` help finding. Broad lint remains the recorded baseline of 83 findings and 9 errors.
- Public API Architecture tests in the full suite: PASS. `deptrac.yaml` accepts the new `OperationData` layer, but the Deptrac run remains blocked by the existing vendor PHP 8.5 parser error at `NikicFileReferenceVisitor.php:106` before project analysis.
- Management-ID comment guard: PASS.
- `git diff --check`: PASS.

## Acceptance Criteria

- PASS: Status authorization request carries current/origin tenant and HTTP propagates trusted TenantRef attributes.
- PASS: Journal/Outcome typed Found/Unavailable contracts, bounded Purpose, default-deny authorizer, and public exception codes.
- PASS: Subject → Authorize → tenant-scoped SELECT/decode ordering with no reader call before Allow.
- PASS: Unknown, tenant mismatch, deny, and retention-empty paths are typed Unavailable without cross-tenant reads.
- PASS: Stable authorization/storage/protection/decode/integrity code paths and post-read Journal/Outcome metadata integrity validation.
- PASS: Raw reader SPI reclassification and runtime DI separation; compiler synthetic authorized-query contracts are autowire-tested.
- PASS: `OperationData` is represented in `deptrac.yaml` and the namespace dependency specification; Seeder, HTTP, operation, worker, and command runtime paths inject authorized queries. Build artifact handler registration also declares synthetic query contracts before constructor autowire.
- PASS: Existing Status HTTP projection and `operation:inspect` code paths were not expanded.
- PASS: Worker made no commit, push, or deploy.

## Remaining Issues

- Broad Mago lint still has the pre-existing 83 findings／9 errors outside P20-016D scope.
- Deptrac cannot complete until its bundled parser supports the repository's PHP 8.5 syntax.
- P20-016C and P20-016D remain together in the uncommitted working tree; no commit, push, or deploy was authorized.

## Suggested Next Action

Proceed to P20-016E after an explicitly authorized Git handoff if the accepted P20-016C／D checkpoint should be committed first.
