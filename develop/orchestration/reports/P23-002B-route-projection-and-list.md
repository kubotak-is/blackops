# P23-002B Task Report

Status: Accepted — non-activated Route Projection and List

Updated At: 2026-08-27T17:56:07+09:00

Started At: 2026-08-26T21:25:10+09:00

Accepted At: 2026-08-27T17:56:07+09:00

## Summary

P23-002A1/A2はAccepted。P23-002B Route projection／`route:list` componentの
Task Packetを作成した。Initial Sol xHigh preflightはChanges Requested
`P1=3/P2=1/P3=0`。Route固有JSON status境界、command target exact30 owner、
renderingまで保持するshared-lock callback seam、Human／JSON／stderr exact wireの
4 rootだけをTask／Spec／Decisionへ限定補正した。

Fresh re-preflightはChanges Requested `P1=1/P2=1/P3=0`。29→30 rowの意図的な
golden変更と、同一exact30 inputに対するlock seam単独のbytes／exit不変条件を分離した。
あわせて`ApplicationConsoleKernelTest`をFiles Allowed／Fast PHPUnitへ追加し、Fast formatを
Tests Allowed全件へ拡張した。

Closure Sol xHigh re-preflightはexact9 aggregate
`991150cdbb60a004657c5c940639ab76f7d0d4826bd27738f6419af40fb5e1ab`へ
Green `P1=0/P2=0/P3=0`を返した。

Luna Max implementation remains complete within the exact Files Allowed. The
bounded Final-Gate contract correction preserves the Stable GET `/operations/`
composite response by separating the runtime status-dispatch matcher (including
the empty suffix) from the nonempty one-segment collision predicate. The request
handler uses the runtime matcher; compiler and route producer retain the
collision predicate. The composite regression asserts exact unavailable 404
JSON, cache header, content type, and body.

The bounded P2 fixture correction adds producer/codec duplicate, producer
record-cap, and closed-row rejection fixtures; non-selected command and schedule
corruption; non-Boolean `--json` input; and an executable manifest-class purity
sentinel. Existing exact30 canonical/alias and all-fallback output fixtures
remain in the Fast Gate. No baseline or unrelated analyze warning was changed.

The prior Sol implementation review verdict Changes Requested `P1=1/P2=2/P3=0`
and the `3ebe65581101fe1e6e732194be40c86ddb532da6dc04a95cbbfe6c0aa41cf02c`
Source/Test freeze with its Final Gate evidence are Historical/superseded because
this correction changed Source/Test. The earlier `a1c17de` and `58a3f2e` freezes
remain Historical for their recorded lint/analyze failures.

The route projection producer, fixed Status contract, disconnected route
commands, exact30 target metadata, callback-shaped lock seam, and A2 list
migration remain covered by the allowed tests. The callback seam catches absent
only while opening the lock; an Absent exception thrown by the callback
propagates and does not invoke it again. Decorated and undecorated Route Human
command output is explicitly byte identical and ANSI-free.

The current correction changes only allowed Source/Test and Management files;
production behavior remains within the existing route/status contract. The
previous `58a3f2e549fa60ecec4366a14e8720d17effdaa82021efb1e4e364f56a784c01`
freeze remains Historical/superseded because canonical `mago analyze` exposed
three Route type errors.

Fresh Sol xHigh implementation review matched exact Source/Test aggregate
`174bc94c...` and management aggregate `3b769dc8...`, returned Green
`P1=0/P2=0/P3=0`, and found no remaining P23-002B finding. The Orchestrator
accepts this non-activated component; P23-002C remains the sole activation owner.

## Changed Files

Production Source:

- `src/Http/Status/OperationStatusHttpContract.php`
- `src/Http/Status/OperationStatusRequestHandler.php`
- `src/Http/Routing/HttpRouteCompiler.php`
- `src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php`
- `src/Internal/Projection/Command/CommandProjectionListCommand.php`
- `src/Internal/Projection/Command/CommandProjectionTargetRegistry.php`
- `src/Internal/Projection/Route/RouteProjectionProducer.php`
- `src/Internal/Projection/Route/RouteProjectionRenderer.php`
- `src/Internal/Projection/Route/RouteProjectionListCommand.php`

Tests:

- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Http/Status/OperationStatusRequestHandlerTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Projection/Command/CommandProjectionListCommandTest.php`
- `tests/Internal/Projection/Command/CommandProjectionProducerTest.php`
- `tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php`
- `tests/Internal/Projection/Route/RouteProjectionProducerTest.php`
- `tests/Internal/Projection/Route/RouteProjectionRendererTest.php`
- `tests/Internal/Projection/Route/RouteProjectionListCommandTest.php`

Management checkpoint files:

- `develop/orchestration/tasks/P23-002B-route-projection-and-list.md`
- `develop/orchestration/reports/P23-002B-route-projection-and-list.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/decisions/146-projection-build-set-php-filesystem-boundary.md`

The packet's preflight had already updated `develop/spec/105`, `develop/spec/106`,
and `develop/decisions/145`; they remain untouched by this implementation
checkpoint. Unrelated dirty files remain preserved.

## Ordered Source/Test freeze proposal

The aggregate is computed as `sha256sum "${p23b_files[@]}" | sha256sum` over
the following ordered 41-file manifest:

```text
src/Http/Routing/HttpRouteCompiler.php
src/Http/Status/OperationStatusHttpContract.php
src/Http/Status/OperationStatusRequestHandler.php
src/Internal/Application/ApplicationConsoleKernel.php
src/Internal/Console/ApplicationCommandCollisionValidator.php
src/Internal/Console/ApplicationOperationListCommand.php
src/Internal/Console/FrameworkCommandNames.php
src/Internal/Projection/BuildSet/ProjectionBuildSetCodec.php
src/Internal/Projection/BuildSet/ProjectionBuildSetFilesystem.php
src/Internal/Projection/BuildSet/ProjectionBuildSetReadLimitException.php
src/Internal/Projection/BuildSet/ProjectionBuildSetReader.php
src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryResult.php
src/Internal/Projection/BuildSet/ProjectionBuildSetRecoveryStatus.php
src/Internal/Projection/BuildSet/ProjectionBuildSetValidationException.php
src/Internal/Projection/BuildSet/ProjectionBuildSetValidationReason.php
src/Internal/Projection/Command/CommandProjectionListCommand.php
src/Internal/Projection/Command/CommandProjectionMarkdownCellEncoder.php
src/Internal/Projection/Command/CommandProjectionNamespaceResolver.php
src/Internal/Projection/Command/CommandProjectionProducer.php
src/Internal/Projection/Command/CommandProjectionRenderer.php
src/Internal/Projection/Command/CommandProjectionReservationRegistry.php
src/Internal/Projection/Command/CommandProjectionTarget.php
src/Internal/Projection/Command/CommandProjectionTargetRegistry.php
src/Internal/Projection/Route/RouteProjectionListCommand.php
src/Internal/Projection/Route/RouteProjectionProducer.php
src/Internal/Projection/Route/RouteProjectionRenderer.php
tests/Http/OperationRequestHandlerTest.php
tests/Http/Status/OperationStatusRequestHandlerTest.php
tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php
tests/Internal/Application/ApplicationConsoleKernelTest.php
tests/Internal/Console/ApplicationCommandCollisionValidatorTest.php
tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php
tests/Internal/Projection/Command/ApplicationOperationListCommandTest.php
tests/Internal/Projection/Command/CommandProjectionListCommandTest.php
tests/Internal/Projection/Command/CommandProjectionNamespaceResolverTest.php
tests/Internal/Projection/Command/CommandProjectionProducerTest.php
tests/Internal/Projection/Command/CommandProjectionRendererTest.php
tests/Internal/Projection/Command/CommandProjectionTargetRegistryTest.php
tests/Internal/Projection/Route/RouteProjectionListCommandTest.php
tests/Internal/Projection/Route/RouteProjectionProducerTest.php
tests/Internal/Projection/Route/RouteProjectionRendererTest.php
```

Current accepted aggregate: `174bc94c42b733a7611885f2edc4ab78aad1628c02a3bdd4ed5a48eb2d3df6fa`.
The Orchestrator Final Gate is bound to this exact Source/Test freeze. Any
Source/Test edit requires a new aggregate and a fresh Fast/Final Gate sequence.

## Decisions and Assumptions

- Bは非接続route componentだけを閉じ、Cが唯一のatomic activation ownerである。
- Route成功JSONはstatusなしのclosed v1 shape。Command recovery statusとは混同しない。
- Bはtarget metadataを29→30へ進めるが、current runtime Framework namesは25のまま。
- 29→30 rowではcommand projectionとBlackOps-owned listのgoldenを更新する。同一exact30
  inputに対するlock seam単独の移行だけがbytes／exit不変である。
- Reader callbackがrender/write完了までshared lockを保持する。
- Fixed Status route method/path/collisionは`OperationStatusHttpContract`を単一Sourceとする。
- Absent recovery is handled only for lock-open absence; callback exceptions,
  including `ProjectionBuildSetAbsentException`, propagate after exactly one
  unlock/close sequence.
- Route Human rendering is raw output from the command, so Symfony decoration
  cannot add ANSI bytes; the command fixture asserts decorated/undecorated parity.

## Commands and Results

- Focused lock/decorated fixture: `docker compose run --rm app vendor/bin/phpunit
  tests/Internal/Projection/Route/RouteProjectionListCommandTest.php` — `6 tests /
  76 assertions` PASS.
- Second correction focused Route PHPUnit: `docker compose run --rm app
  vendor/bin/phpunit tests/Internal/Projection/Route` — `19 tests / 115 assertions`
  PASS.
- Final-Gate correction focused PHPUnit: `docker compose run --rm app
  vendor/bin/phpunit tests/Internal/Projection/Command/CommandProjectionListCommandTest.php
  tests/Internal/Projection/Route tests/Internal/Projection/BuildSet/ProjectionBuildSetReaderStatusTest.php`
  — `31 tests / 563 assertions` PASS.
- Latest correction-focused HTTP/Route PHPUnit — `80 tests / 396 assertions` PASS.
- Task Fast Gate PHPUnit: exact packet command — `128 tests / 1221 assertions` PASS.
- Task Fast Gate Mago format check over all allowed Source/Test paths — PASS,
  `INFO All files are already formatted.`
- Historical/superseded pre-correction canonical lint at `a1c17de`: `docker compose
  run --rm app mago lint` — failed with 7 issues (6 warnings, 1 help message);
  this is not Green evidence and the candidate is superseded.
- Canonical CI lint after correction: `docker compose run --rm app mago lint` —
  PASS, `INFO Filtered out 186 issues based on the baseline file` and `INFO No
  issues found.`
- Canonical CI baseline verification: `docker compose run --rm app mago lint
  --verify-baseline` — PASS, `INFO Baseline is up to date.` No baseline was
  generated or updated.
- Historical/superseded canonical analyze at `58a3f2e`: `docker compose run
  --rm app mago analyze` — failed with 3 Route type errors (115 total issues:
  3 errors, 78 warnings, 34 help messages); this is not Green evidence.
- Canonical analyze after correction: `docker compose run --rm app mago analyze`
  — exit 0 with 112 issues: 0 errors, 78 warnings, and 34 help messages. The
  existing non-error diagnostics were not changed.
- Task Fast Gate management-ID guard — PASS (no output).
- `git diff --check` — PASS.
- `test -z "$(git diff --cached --name-only)"` — PASS (`0` staged paths).
- Historical/superseded Orchestrator Final Gate on aggregate `3ebe6558...`:
  format PASS; Composer
  strict PASS; canonical lint PASS with 186 baseline-filtered issues and no
  current issues; baseline verification PASS; analyze exit 0 with 0 errors,
  78 warnings, and 34 help messages; PHPUnit `2428 tests / 10710 assertions`
  PASS with existing Deprecations 1, PHPUnit Deprecations 2, and Notices 13;
  Deptrac PASS with violations 0, skipped 0, uncovered 0, allowed 4955;
  version-baseline syntax and execution PASS (`published=1.2.0`,
  `historical=1.1.0`); canonical `pnpm --dir docs/website run
  release:check:source` PASS; management-ID, diff check, and staged0 PASS. This
  evidence is not reusable for the current `174bc94c...` freeze.
- Current Orchestrator Final Gate on aggregate `174bc94c...`: format PASS;
  Composer strict PASS; canonical lint PASS after 186 baseline-filtered issues;
  baseline verification PASS; analyze exit0 with errors `0`, warnings `78`,
  help `34`; PHPUnit `2435 tests / 10739 assertions` PASS with existing
  Deprecations `1`, PHPUnit Deprecations `2`, and Notices `13`; Deptrac PASS with
  violations/skipped/uncovered/warnings/errors `0`, allowed `4955`; version
  syntax/baseline PASS (`published=1.2.0`, `historical=1.1.0`); canonical Website
  release-source guard PASS; management-ID, diff, staged0, and ending 41-file
  freeze PASS.
- Historical environment-command correction: `docker compose run --rm website
  pnpm release:check:source` failed before the guard with `no such service:
  website`. `docker compose config --services` exposes only `postgres` and
  `app`; `.github/workflows/ci.yml` owns the successful canonical pnpm command
  above. No Source/Test bytes changed.
- Sol xHigh closure re-preflight: Green `P1=0/P2=0/P3=0`。
- Fresh Sol xHigh implementation review: Green `P1=0/P2=0/P3=0`; exact
  Source/Test and management hashes matched; no tests were rerun by the reviewer.
- Website full test／build／Browser／Network／CI: Not Run。public docs不変かつTask外。
- Commit／Stage／Push／Deploy／Publication／Tag／Release: Not Run／未承認。

## Acceptance Criteria

- [x] First Sol xHigh re-preflight Changes Requested `P1=1/P2=1/P3=0` recorded
- [x] Closure Sol xHigh re-preflight Green
- [x] Luna Max implementation complete within exact Files Allowed
- [x] Final-Gate analyze correction complete and Fast Gate Green
- [x] Source/Test freeze accepted
- [x] Post-correction Final Gate Green
- [x] Sol xHigh implementation review Green
- [x] Orchestrator Acceptance

## Remaining Issues

None within P23-002B. Stable `1.2.0` is current; BlackOps `1.3` and the accepted
B component remain unreleased/non-activated. The higher atomic slice still
requires P23-002C schedule/final activation.

## Suggested Next Action

Begin the P23-002C Task Packet and Sol preflight while preserving B as
non-activated. P23-002C owns schedule and the only all-three atomic activation.
Commit, stage, push, CI, deploy, publication, tag, and release remain
unapproved and unexecuted.
