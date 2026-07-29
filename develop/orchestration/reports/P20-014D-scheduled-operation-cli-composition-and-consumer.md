# P20-014D Scheduled Operation CLI, Composition, and Consumer Report

## Summary

Added direct regression coverage for scheduled runner ordering, claimed recovery identity and terminal state, misfire/overlap aggregation, safe invocation failure, no-schedule CLI output, and scheduled actor provider/build-artifact boundaries. The consumer journey now checks canonical Journal event sequences for transactional Inline, Deferred, crash recovery, and concurrent Deferred execution, and asserts one operation/slot for the race fixture.

## Changed Files

- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationScheduledOperationRuntime.php`
- `src/Internal/Application/ApplicationScheduledOperationRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/FrameworkCommandNames.php`
- `src/Internal/Console/ScheduledOperationRunCommand.php`
- `src/Internal/Execution/DeferredWorkerRuntimeStorage.php`
- `src/Internal/Scheduling/PostgreSqlScheduleStore.php`
- `src/Internal/Scheduling/ScheduledOperationDefinitionResolver.php`
- `src/Internal/Scheduling/ScheduledOperationRunResult.php`
- `src/Internal/Scheduling/ScheduledOperationRunner.php`
- `src/Internal/Scheduling/ScheduledOperationRunService.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Application/ApplicationScheduledOperationRuntimeComposerTest.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/ScheduledOperationRunCommandTest.php`
- `tests/Internal/Scheduling/ScheduledOperationDefinitionResolverTest.php`
- `tests/Internal/Scheduling/ScheduledOperationRunnerTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/scheduled-operation.sh`
- `tests/Fixtures/ScheduledOperation/ScheduledInlineProbe.php`
- `develop/spec/16-namespace-dependencies.md`
- `deptrac.yaml`

## Decisions and Assumptions

- Kept the existing Application Schedule runtime and Maintenance Scheduler command boundaries unchanged.
- Provider tests use an application-owned `ScheduledActorProvider`; the provider may return `null`, while missing provider remains invalid for an authorized schedule.
- Consumer Journal assertions use the canonical event sequence and operation ID already persisted by the scheduled runtime; no secrets or fixture artifacts are retained.
- The race occurrence count is restricted to rows with a non-null operation ID so skip rows created at a minute boundary do not make the evidence flaky.

## Commands and Results

| Command | Result |
| --- | --- |
| `docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Scheduling/ScheduledOperationRunnerTest.php tests/Internal/Console/ScheduledOperationRunCommandTest.php tests/Internal/Console/ApplicationBuildCompileCommandTest.php tests/Internal/Application/ApplicationScheduledOperationRuntimeComposerTest.php tests/Integration/ApplicationConsoleKernelTest.php` | PASS — 18 tests, 129 assertions |
| `docker compose run --rm app mago format --check <changed PHP files>` | PASS |
| `git diff --check -- <changed files>` | PASS |
| `bash -n tests/Consumer/scheduled-operation.sh` | PASS |
| `bash tests/Consumer/scheduled-operation.sh` | PASS — CLI、Transactional Inline、Deferred Worker、crash recovery、two-process convergence、canonical Journal exactly-once |
| `docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Scheduling tests/Internal/Console tests/Internal/Application tests/Integration/ApplicationConsoleKernelTest.php` | PASS — 382 tests, 1434 assertions |
| Correction focused CLI／Kernel／resolver | PASS — 43 tests, 240 assertions |
| `docker compose run --rm app vendor/bin/phpunit --display-deprecations` | PASS — 1994 tests, 7912 assertions; existing PHP 8.5 deprecation 1 |
| `docker compose run --rm app mago format --check src tests` | PASS |
| Changed-source `mago analyze` | PASS — No issues found |
| `docker compose run --rm app mago analyze src tests` | BLOCKED — repository-wide PHPUnit symbol resolution and existing test-analysis findings, 1072 issues |
| `docker compose run --rm app vendor/bin/deptrac analyse --no-progress` | BLOCKED — existing deptrac PHP 8.5 parser error in `NikicFileReferenceVisitor.php:106` |
| `docker compose run --rm app vendor/bin/phpunit tests/Architecture tests/Scheduling` | PASS — 20 tests, 330 assertions |
| Management-ID guard／`bash -n tests/Consumer/scheduled-operation.sh`／`git diff --check` | PASS |

## Acceptance Criteria

- [x] Direct runner tests cover deterministic schedule order and claimed recovery before evaluation.
- [x] Recovered occurrence keeps its fixed operation ID and reaches terminal `completed`.
- [x] Misfire and overlap counts are asserted independently.
- [x] Invocation failure terminalizes the claimed occurrence with a safe category.
- [x] Authorized scheduled build rejects a missing provider and registers a configured provider.
- [x] Runtime composer build ID and provider boundaries are directly tested.
- [x] No-schedule human CLI output is exact and exits successfully.
- [x] Consumer script checks Inline/Deferred/crash/race Journal events and one-operation/one-slot convergence.
- [x] Full Consumer execution evidence passed after correcting the proxyability fixture.
- [x] Full PHPUnit、Mago、Deptrac、Architecture gates were executed and their results recorded.

## Remaining Issues

Broad Mago remains blocked by the repository-wide PHPUnit symbol-resolution configuration and existing test-analysis findings. Deptrac remains blocked by its existing PHP 8.5 parser error. Changed production sources and the dedicated Architecture suite pass. The first Consumer build exposed a `final` transactional fixture; the proxyable correction and the full Consumer journey both pass.

## Suggested Next Action

Proceed to P20-014E Scheduled Application Operation guide and independent documentation review. No commit, push, or deploy was performed.

## Correction Cycle: CLI Input Boundary and Definition Resolution

The scheduled command now catches Symfony Console input-binding exceptions at its `run()` boundary and emits the stable `configuration_error` shape with Exit 2. The Application Console Kernel registers this command directly instead of wrapping it in `LazyFrameworkCommand`, so unknown options are handled before an outer lazy bind can leak an exception. JSON detection uses `hasParameterOption('--json')` before binding. Direct tests cover unknown human and JSON options through `CommandTester` and the Application Kernel. `ScheduledOperationDefinitionResolver` tests now cover compiled self-handled instance reuse, constructorless definition construction, and safe rejection of required constructors.

Correction verification: focused CLI／Kernel／resolver suite PASS (30 tests, 123 assertions); complete focused P20-014D PHP set PASS (43 tests, 240 assertions); Mago format, management-ID guard, and `git diff --check` PASS. No commit／push／deploy.

## Orchestrator Acceptance

Accepted at `2026-07-29T01:48:56+09:00`.

The Orchestrator independently reviewed command composition, Build ID and actor-provider validation, deterministic claimed-first runner behavior, advisory-lock scope, Definition resolution, Inline／Deferred lifecycle reuse, Worker terminal hooks, crash recovery, concurrency convergence, and safe CLI output. The correction cycle fixed the pre-execute Symfony option boundary so unknown options return stable `configuration_error` with Exit 2 through the real Application Kernel.

Focused PHPUnit passed with 382 tests／1434 assertions, correction coverage passed with 43 tests／240 assertions, the full Consumer journey passed, and final Full PHPUnit passed with 1994 tests／7912 assertions plus one pre-existing PHP 8.5 deprecation. Format, changed-source Mago, Architecture／Scheduling 20 tests／330 assertions, shell syntax, management-ID guard, and `git diff --check` passed. Broad Mago and Deptrac were executed and remain blocked by the documented repository-wide analyzer issues. P20-014D is Accepted. Commit／Push／Deployなし。
