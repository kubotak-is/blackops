# P20-014A: Scheduled Operation Authoring and Manifest Report

## Summary

Implemented the first Scheduled Application Operation slice without connecting runtime evaluation, persistence, invocation, CLI, or transport. `ScheduledBy`, immutable schedule context/metadata, compiler validation, registry schedule lookup, a shared numeric five-field cron parser model, and Operation Manifest schema 3 schedule encoding/decoding are available for follow-up tasks.

## Changed Files

- `src/Core/Attribute/ScheduledBy.php`
- `src/Core/ScheduleContext.php`
- `src/Core/ExecutionContext.php`
- `src/Core/Registry/OperationScheduleMetadata.php`
- `src/Core/Registry/OperationMetadata.php`
- `src/Core/Registry/OperationRegistry.php`
- `src/Internal/Scheduling/CronField.php`
- `src/Internal/Scheduling/CronExpression.php`
- `src/Internal/Registry/OperationMetadataCompiler.php`
- `src/Internal/Registry/OperationManifestMetadataCodec.php`
- `src/Internal/Registry/OperationManifestFile.php`
- `tests/Core/Attribute/ScheduledByTest.php`
- `tests/Core/ScheduleContextTest.php`
- `tests/Core/ExecutionContextTest.php`
- `tests/Core/Registry/OperationRegistryTest.php`
- `tests/Internal/Scheduling/CronExpressionTest.php`
- `tests/Internal/Registry/OperationMetadataCompilerTest.php`
- `tests/Internal/Registry/OperationManifestFileTest.php`
- `tests/Internal/Registry/OperationManifestMetadataCodecTest.php`
- `develop/orchestration/tasks/P20-014A-scheduled-operation-authoring-and-manifest.md`
- `develop/orchestration/reports/P20-014A-scheduled-operation-authoring-and-manifest.md`
- `develop/STATE.md`
- `develop/TODO.md`

Existing Phase 20 documentation working-tree changes were preserved.

## Decisions and Assumptions

- `ScheduledBy` is class-targeted, non-repeatable, uses the existing lowercase dot-separated identity grammar, defaults timezone to `UTC`, and does not alter Inline/Deferred strategy selection.
- The parser is a compile/manifest validation and field-model component only. Calendar evaluation, DST traversal, misfire, overlap, and persistence remain out of scope.
- Compiler and Manifest Decoder both call the same `CronExpression::parse()` contract. Numeric wildcard/list/inclusive-range/step forms are accepted; a step requires `*` or an inclusive range, so `5/2` is rejected.
- Day-of-week `7` is normalized to Sunday `0` during parsing, including deduplication for `0,7`.
- Public types keep their invariants locally to avoid exposing Internal scheduling classes. Compiler/decoder use the shared Internal parser before constructing public metadata.
- Scheduled values must be instantiable with zero required constructor parameters; constructors are not executed during compilation. Scheduled Ephemeral outcomes are rejected.
- Manifest schedule shape is exactly `name`, `cron`, and `timezone`; schema versions other than `3` remain rejected.

## Cron Validation Matrix

| Input | Result |
| --- | --- |
| `0 0 * * *` | accepted |
| `*/5 0,12 1-15 1-12 1-5` | accepted |
| `0 0 1-15 * 1-5` | accepted; DOM/DOW OR model recorded |
| `5/2 * * * *` | rejected; single-value step |
| `0 0 10-1 * *` | rejected; reverse range |
| `0 0 * JAN *` | rejected; named month |
| `0 0 * * * *` | rejected; six fields |

## Manifest Compatibility

Schema `3` round-trips scheduled and unscheduled metadata. Decoder rejects old/unknown schema versions, malformed schedule objects, and invalid cron/timezone/name payloads through the shared parser and public metadata invariants. Schedule omission decodes as `null`; no implicit migration is provided.

## Commands and Results

- `docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Core tests/Internal/Registry tests/Internal/Scheduling`: PASS (420 tests, 961 assertions).
- `docker compose run --rm app vendor/bin/phpunit --display-deprecations`: final retry PASS (1912 tests, 7659 assertions) with one pre-existing PHP 8.5 `ReflectionProperty::setAccessible()` deprecation.
- The first full-suite run observed the unrelated `OutboxRelayRuntimeTest::testBlockingDeliveryReceivesPeriodicHeartbeatOnSeparateConnection` timing failure. Its isolated retry passed (1 test, 4 assertions), and the next full-suite run passed.
- `docker compose run --rm app mago format src tests` / `mago format --check src tests`: PASS; all files already formatted.
- Targeted `docker compose run --rm app mago analyze` on changed source: PASS (`INFO No issues found`).
- Broad `mago analyze src tests`: existing repository baseline reports unrelated test analysis issues.
- `docker compose run --rm app vendor/bin/deptrac analyse --no-progress`: BLOCKED by existing parser error in `NikicFileReferenceVisitor.php` line 106.
- `! rg -n 'Spec(ification)?...|D...|P...|TODO.md...' src tests --glob '*.php'`: PASS.
- `git diff --check`: PASS.

## Acceptance Criteria

- [x] Public `ScheduledBy`, UTC default, identity/cron/timezone validation.
- [x] Schedule context UTC normalization and ExecutionContext null/non-null getter.
- [x] Metadata, registry schedule-name lookup, duplicate-name rejection.
- [x] Shared five-field parser model, DOW `7` to Sunday `0` normalization, and conditional DOM/DOW OR semantics marker.
- [x] Scheduled Value constructor/instantiability and Ephemeral rejection.
- [x] Inline/Deferred strategy independence.
- [x] Manifest schema 3 schedule round-trip and malformed/legacy rejection.
- [x] Unit and manifest regression coverage.
- [x] No runtime/database/CLI/guide/dependency changes.
- [x] Report/STATE/TODO synchronization; no commit.

## Remaining Issues

- The Outbox heartbeat test remains timing-sensitive, although its isolated retry and the final full suite passed.
- Full `mago analyze` retains unrelated repository baseline findings.
- Deptrac remains blocked by its existing PHP parser compatibility error.
- Runtime evaluator, PostgreSQL state/occurrence, invocation, CLI, and documentation remain for P20-014B–E.

## Suggested Next Action

Start P20-014B for PostgreSQL schedule state/occurrence persistence, calendar evaluation, misfire, and overlap.
