# P18-009D1: SAPI Location Status Correction Report

## Summary

Corrected the Framework SAPI response emission order so validated headers, including `Location`, are emitted before the explicit PSR-7 status. This prevents PHP SAPI from converting a `202 Accepted` response into an implicit `302 Found`. Added an ordered regression test covering `Location`, status, and body emission. No Public API, Application Composer, Distribution, or existing P18-009D application/documentation change was modified.

## Failure Cause and Reproduction Evidence

P18-009D Quickstart E2E reproduced `POST /reports expected 202, got 302`. The Application response was already `202` and contained the expected accepted body, `Location`, and `Retry-After`. `SapiResponseEmitter` previously called `http_response_code(202)` before `header('Location: ...')`; PHP's SAPI then applied the redirect header's implicit 302 status.

## Header／Status Ordering Contract

1. Validate every response header before any emitter callback runs.
2. Emit every header value, preserving duplicate header values.
3. Emit the explicit PSR-7 status after headers and before reading or emitting the body.
4. For `HEAD`, emit headers and status but never emit body bytes.
5. Status, header, and body failures continue to propagate to the existing `SapiRuntime` safe-failure boundary.

## Changed Files

- `src/Internal/Runtime/FrankenPhp/SapiResponseEmitter.php`
- `tests/Internal/Runtime/FrankenPhp/SapiResponseEmitterTest.php`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P18-009D1-sapi-location-status-correction.md`
- `develop/orchestration/reports/P18-009D1-sapi-location-status-correction.md`
- `develop/orchestration/reports/P18-009D-runtime-distribution-dependency-closeout.md`

## Commands and Results

- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Runtime/FrankenPhp/SapiResponseEmitterTest.php tests/Http/SapiRuntimeTest.php` — PASS, 12 tests／25 assertions.
- `bash tests/Consumer/quickstart-e2e.sh` — PASS, including real HTTP `POST /reports` status 202 and `Quickstart consumer E2E passed.`
- `docker compose run --rm app vendor/bin/phpunit` — PASS, 1,727 tests／6,898 assertions.
- `docker compose run --rm app mago format --check src tests` — PASS.
- `docker compose run --rm app mago lint` — PASS; existing SAPI empty-loop note and UUID compiler else help remain informational.
- `docker compose run --rm app mago analyze` — PASS.
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress` — PASS, 0 violations／2,860 allowed.
- `! rg -n 'Spec(ification)?...|D[0-9]{3}|P[0-9]+-[0-9]+|TODO.md:' src tests --glob '*.php'` — PASS.
- `git diff --check` — PASS.

## Acceptance Criteria

- [x] `Location`付き202 Responseの最終Statusを順序付き回帰Testで固定した。
- [x] StatusなしRedirect Responseの既存PSR-7 Statusを維持した。
- [x] Header Injection時のNo Partial Emissionを維持した。
- [x] HEAD／Body／複数Header／Failure Testが成功した。
- [x] Quickstart E2Eが実HTTPで202を確認して成功した。
- [x] Focused／Full PHPUnit、Mago、Deptrac、管理ID Guard、diff checkが成功した。
- [x] P18-009D Application／Distribution／Documentation差分、Public API、External Publication／Deployを変更していない。
- [x] Worker Commitなし。

## Remaining Issues

No D1 implementation blocker remains. P18-009D has resumed; its remaining Community Board and other consumer gates are still pending.

## Orchestrator Review

- Reviewed the two-line production ordering change and ordered `Location`／202 regression independently; no scope or contract issue found.
- Reran focused PHPUnit independently: PASS, 12 tests／25 assertions.
- Reran the complete Quickstart E2E independently: PASS, including real HTTP `POST /reports` status 202.
- Confirmed management-ID and diff guards remained clean. P18-009D1 is Accepted.

## Suggested Next Action

Commit P18-009D1, then complete P18-009D's remaining Consumer／Frontend／Browser gates. Worker has not committed.
