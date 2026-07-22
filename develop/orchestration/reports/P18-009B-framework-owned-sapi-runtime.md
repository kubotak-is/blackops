# P18-009B Framework-owned SAPI Runtime

## Summary

Implemented the public `BlackOps\\Http\\SapiRuntime` boundary for Classic and FrankenPHP Worker execution. The runtime now owns PSR-17 request creation, SAPI response emission, fixed safe internal failures, Worker callback continuation, process-environment restoration, cycle collection, and bounded runtime evidence. Quickstart and Community Board HTTP entrypoints are thin Application bootstrap plus runtime calls.

## Changed Files

- `src/Http/SapiRuntime.php`
- `tests/Http/SapiRuntimeTest.php`
- `tests/Application/ApplicationTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `examples/quickstart/public/index.php`
- `examples/quickstart/public/worker.php`
- `examples/community-board/public/index.php`
- `examples/community-board/public/worker.php`
- `mago.toml` (Nyholm PSR-7 source included for type analysis)
- `deptrac.yaml` (Http runtime may depend on Application and framework-internal adapters)
- `docs/internal/frankenphp-runtime.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P18-009B-framework-owned-sapi-runtime.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-009B-framework-owned-sapi-runtime.md`

## Decisions and Assumptions

- Reused the existing `SuperglobalServerRequestFactory` and `SapiResponseEmitter`; no duplicate adapter or application lifecycle implementation was introduced.
- `SapiRuntime` constructs `Nyholm\\Psr7\\Factory\\Psr17Factory` directly. Public entrypoints do not import Nyholm, Laminas, or FrankenPHP APIs.
- The Worker process baseline is normalized to `array<string,string>`. Each callback restores that baseline even after request creation, handler, or emitter failure.
- Safe failures never include exception messages, request data, body data, or header values. If headers are already sent, or safe emission itself fails, the runtime emits no additional body. Before a fixed response is emitted, queued SAPI headers are removed so a partially emitted response cannot leak into the fallback.
- `environmentRestored` evidence indicates whether callback code changed `$_ENV` before restoration; `false` confirms clean callback isolation.

## Classic / Worker Failure Matrix

| Boundary | Classic | Worker |
| --- | --- | --- |
| PSR-17/request creation failure | fixed JSON 500 before headers | fixed JSON 500, callback marked failed, loop continues |
| Handler failure | fixed JSON 500 before headers | fixed JSON 500, callback marked failed, loop continues |
| Emitter/header failure | report class only, then fixed JSON 500 if headers remain unsent | report class only, fixed JSON 500 if headers remain unsent, callback cleanup, and loop continuation |
| Headers already sent | no extra output | no extra output; callback cleanup and loop continuation |
| Missing FrankenPHP function | not applicable | report fixed runtime failure and return |
| Request cleanup | Application handler lifecycle | lifecycle plus `$_ENV` restoration and `gc_collect_cycles()` |

## Commands and Results

- `docker compose run --rm app vendor/bin/phpunit` — PASS, 1,721 tests, 6,877 assertions.
- `docker compose run --rm app vendor/bin/phpunit tests/Http/SapiRuntimeTest.php` — PASS, 5 tests, 10 assertions.
- Orchestrator focused Runtime／Application／Architecture PHPUnit — PASS, 23 tests, 342 assertions.
- `docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests` — PASS.
- `docker compose run --rm app mago lint` — PASS.
- `docker compose run --rm app mago analyze` — PASS after adding the Nyholm PSR-7 include to `mago.toml`.
- `docker compose run --rm app composer validate --strict` — PASS.
- `docker compose run --rm app composer validate --strict --working-dir=examples/quickstart` — PASS.
- `docker compose run --rm app composer validate --strict --working-dir=examples/community-board` — PASS.
- `docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress` — PASS, 0 violations, 2,854 allowed, 3 uncovered.
- Management-ID guard over `src tests examples/quickstart examples/community-board` — PASS.
- `git diff --check` — PASS.
- `bash tests/Consumer/frankenphp-worker-mode.sh` — PASS. Worker bootstrap, journal flush, rejection isolation, database reconnect, multi-request isolation, restart/memory bounds, Classic fallback, and correlated failure boundary all passed.
- `bash tests/Consumer/community-board-identity.sh` — PASS after restoring lockfile-managed dependencies. Community Board PHP 49 tests／548 assertions, Frontend 43 tests, Svelte 0 errors／0 warnings, production build, Default Worker, Classic HTTP, identity lifecycle, and sensitive-surface guards passed.

## Acceptance Criteria

- [x] Public `SapiRuntime::run(Application): void` and `runWorker(Application): void`, with private constructor.
- [x] Framework-owned request creation, response emission, safe JSON 500, Worker loop, environment restore, and GC.
- [x] Request/handler/emitter failure isolation and no raw Throwable or secret output.
- [x] Quickstart and Community Board entrypoints migrated to thin runtime calls.
- [x] `Application::http()` remains unchanged and available as the PSR-15 escape hatch.
- [x] Consumer Worker/Classic/restart/memory/reconnect/failure evidence passed.

## Remaining Issues

None within P18-009B. Community Board `vendor` and `frontend/node_modules` were absent at the first Orchestrator run; they were restored from the existing lockfiles and left as ignored local dependencies after the passing consumer. Generated and runtime artifacts were cleaned by the consumer.

## Suggested Next Action

Commit the accepted P18-009B change set, then proceed to P18-009C Public UUIDv7 Generator and Consumer Adoption.

## Orchestrator Verification

- Reviewed Public API, Classic／Worker failure matrix, queued-header removal, string-only environment baseline, callback continuation, cleanup／GC, entrypoint scope, and dependency-layer change: accepted.
- Architecture guard rejects Nyholm／Laminas imports and direct FrankenPHP loop ownership in both Quickstart and Community Board public entrypoints.
- Focused Runtime／Application／Architecture PHPUnit: PASS — 23 tests, 342 assertions.
- Mago Analyze: PASS — no issues.
- Deptrac: PASS — 0 violations, 2,854 allowed, 3 uncovered.
- Community Board Identity Consumer: PASS — Default Worker and Classic HTTP plus PHP／Frontend／security journey.
- Management-ID guard and `git diff --check`: PASS.
