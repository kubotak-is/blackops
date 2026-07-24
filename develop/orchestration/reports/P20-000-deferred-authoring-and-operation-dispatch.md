# P20-000 Deferred Authoring and Operation Dispatch

Status: Accepted

## Summary

Implemented canonical `#[Deferred]` authoring and the public `BlackOps\Execution\Operations::dispatch()` transactional child-operation facade. The facade validates compiled metadata and value type, reuses existing outbox persistence and context construction, and returns a receipt that exposes only child Operation ID and UTC dispatch time.

## Changed Files

- Added `src/Core/Attribute/Deferred.php`, `src/Execution/Operations.php`, and `src/Execution/DispatchReceipt.php`.
- Updated metadata compilation, outbox runtime, runtime DI bindings, HTTP/Worker/Console composition, and related tests.
- Migrated Quickstart, Community Board, and frontend fixture Deferred declarations; migrated Community Board `AddComment` to `Operations::dispatch()`.
- Updated Guide/Internal Reference, Website checks, Changelog, and this report/STATE checkpoint.

## Deferred Attribute Contract

`#[Deferred]` is a class-only, argument-free public marker normalized to `BlackOps\Core\Execution\Deferred::class`. No marker remains Inline. Existing `#[ExecuteWith(...)]` remains compatible. Repeated or combined Deferred/ExecuteWith metadata is rejected before handler compilation; strategy identity, Manifest, Journal, Transport, and Ray.Aop behavior are unchanged.

## Operations Dispatch Contract

`Operations::dispatch(class-string, OperationValue, ?availableAt, ?executionActor)` accepts registered Deferred metadata only and rejects unknown definitions, Inline strategy, and mismatched values before persistence. It uses a metadata/value-only internal path: child Definition constructors are not invoked during registration. The active Operation Context and Framework-owned root transaction checks are the same as `TransactionalOutbox`; no direct-transport fallback is introduced. `DispatchReceipt` exposes `operationId()` and UTC `dispatchedAt()` only. `TransactionalOutbox::register()` remains compatible.

## Transaction / Context / Identity Evidence

Existing context factory and outbox store paths remain shared. Focused outbox tests cover root/nested/rollback/manual/different-connection guards and the new non-construction dispatch path (14 tests, 45 assertions). Full Framework PHPUnit passed 1,879 tests and 7,583 assertions (one existing PHP 8.5 deprecation). Deptrac reported zero violations.

## Compatibility and Migration

Community Board `NotifyPostOwner`, Digest, Quickstart Report, and the frontend fixture use `#[Deferred]`. `AddComment` injects `Operations` and dispatches `NotifyPostOwner::class` with its Value; no Application code constructs the child Definition. Runtime synthetic DI binding is installed for HTTP, Worker, and Console container composition.

## Commands and Results

- `docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app` — PASS.
- `docker compose run --rm app mago lint` — PASS (existing informational notes/help only).
- `docker compose run --rm app mago analyze` — PASS, no issues.
- `docker compose run --rm app vendor/bin/phpunit --display-deprecations` — PASS, 1,879 tests / 7,583 assertions / 1 accepted deprecation.
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` — PASS, 0 violations.
- Focused metadata/DI/outbox suite — PASS, 61 tests / 132 assertions; outbox rerun 14 / 45.
- `bash tests/Consumer/quickstart-e2e.sh` — PASS.
- `bash tests/Consumer/framework-package-export.sh` — PASS.
- `bash tests/Consumer/community-board-clean-install.sh` — PASS (frontend 46 tests, check/build).
- `bash tests/Consumer/community-board-post-comment.sh` — PASS (55 tests / 582 assertions).
- `CI=true bash tests/Consumer/community-board-product-journey.sh` — Orchestrator rerun PASS.
- `CI=true bash tests/Consumer/community-board-digest.sh` — Orchestrator rerun PASS; Community Board digest journey passed.
- `mise exec -- pnpm --dir docs/website run test` — PASS, 42 tests.
- `mise exec -- pnpm --dir docs/website run check` — PASS, 0 errors/warnings/hints.
- `mise exec -- pnpm --dir docs/website run build` — PASS, 33 pages / 32 site checks.
- Management-ID guard and `git diff --check` — PASS.

## Acceptance Criteria

- [x] Canonical `#[Deferred]` and compatibility/conflict guards.
- [x] `Operations::dispatch()` Class + Value metadata/value path and public receipt.
- [x] Existing Outbox atomicity/context/identity behavior reused.
- [x] Quickstart, Community Board, fixture, Guide, Internal Reference, and Website sync.
- [x] Framework, quickstart, package export, clean install, post-comment, and Website gates passed.
- [x] Product Journey and Digest full consumer rerun.
- [x] ScheduledBy, generic Bus, Direct arbitrary-context acceptance, Manifest/Migration, Ray.Aop, and external publication remain out of scope.
- [x] Worker made no commit.

## Remaining Issues

None.

## Suggested Next Action

Commit the accepted P20-000 implementation and run the GitHub Actions acceptance loop. `ScheduledBy` remains a separate future design task.

## Orchestrator Review Corrections

- Corrected Internal Outbox documentation to state that `Operations::dispatch()` validates compiled metadata, class-string, and Value, persists the envelope without constructing the child Definition, and defers Definition resolution to Worker execution.
- Clarified the Core API `TransactionalOutbox` row as a low-level compatibility API; new Application Coordination should inject `Operations`.
- Added permanent compiler coverage for repeated `#[Deferred]` rejection, separate from the Deferred/ExecuteWith conflict test.
- Focused compiler/outbox suite: 42 tests / 103 assertions PASS. Mago format check, management-ID guard, and `git diff --check` PASS. Final Accepted/STATE closeout remains with Orchestrator.

## CI Follow-up

GitHub Run `30111599275` reported one Mago format failure in `examples/community-board/tests/Board/PostOperationContractTest.php`. The tracked diff was independently verified to contain only the import ordering correction (`BlackOps\Execution\DispatchReceipt`／`Operations` moved before `BlackOps\Http` imports); no runtime or vendor traversal changes are present. A single-file Docker Mago format check passed (`All files are already formatted`), and the Management-ID guard plus `git diff --check` passed. No commit was created.
