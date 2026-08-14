# P20-018E Completion Report

Status: Accepted

## Package / Image Assessment

- Framework `composer.json` already has the required production dependency boundary: `open-telemetry/api:^1.10`; no SDK, OTLP exporter, HTTP client, credential, or Collector dependency is present.
- Production `require` remains API-only: `open-telemetry/api:^1.10`, with bounded transitive `open-telemetry/context` `1.5.0`; the production archive does not include SDK/exporter/client packages. The repository `require-dev`/lock intentionally adds the Consumer-only SDK/exporter/client set below (20 installs, 0 updates/removals), so the lock contains those packages without changing the Framework production boundary.
- Existing Framework autoload is PSR-4 `BlackOps\\` -> `src/`; no plugin, binary, exporter, or HTTP client is introduced by this Task's Production `require` surface. The only configured Composer plugin remains the existing Mago plugin.
- Consumer Collector candidate is pinned to `otel/opentelemetry-collector:0.158.0`, official image digest `sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd`, image user `10001:10001`, entrypoint `/otelcol`; the image was not cached at Task start. Consumer uses OTLP HTTP `4318` and the Collector `debug` exporter. Remote credentials and production Compose defaults remain out of scope.
- Supply-chain dry-run for the Consumer/`require-dev` candidate (`open-telemetry/sdk:^1.15`, `open-telemetry/exporter-otlp:^1.4`, `php-http/guzzle7-adapter:^1.1`) resolves 20 installs, 0 updates, 0 removals, advisories 0; representative versions are SDK 1.15.0, exporter 1.4.0, gen-otlp-protobuf 1.10.0, google/protobuf 5.35.1, Guzzle 7.15.3, and adapter 1.1.0. SDK/exporter are Apache-2.0; adapter is MIT; PSR-4/file autoload and registration files were reviewed.
- Transitive `php-http/discovery` 1.20.0 (MIT) and `tbachert/spi` 1.0.5 (Apache-2.0) are Composer-plugin packages with `extra.plugin-optional=true`; neither is added to `config.allow-plugins`. Their optional discovery/provider generation is not required by the explicit Consumer adapter path. No package declares a new binary; SDK/exporter registration/compatibility autoload files remain Consumer-owned.
- Supply-chain diff is intentionally empty for Framework production dependencies: SDK/exporter/HTTP client supply is Consumer/`require-dev` only, with source/license/autoload/plugin/binary/advisory/transitive review recorded here; the 20-lock-package addition has no new trusted plugin or binary.

## Summary

Implemented the safe Version 1 Operational Health query/report, explicit PSR-15 and CLI adapters, public callback composition, and a Consumer-only Docker Collector journey. No automatic route/command registration or production SDK/exporter dependency was added.

## Changed Files

- `src/Observability/**`, `src/Internal/Observability/**`, `src/Http/Observability/**`, `src/Console/Observability/**`
- `tests/Observability/**`, `tests/Http/Observability/**`, `tests/Console/Observability/**`
- `tests/Consumer/opentelemetry-observability.sh` and `tests/Consumer/fixtures/opentelemetry/**`
- `composer.json`, `composer.lock`, `deptrac.yaml`, `develop/spec/16-namespace-dependencies.md`, `develop/spec/100-structured-logging-and-opentelemetry.md`
- Task Packet, Report, `develop/STATE.md`, and `develop/TODO.md`

## Probe Matrix

Liveness returns pass without evaluating providers. Readiness emits the six public bounded categories (`compiled_artifact`, `runtime_configuration`, `database`, `migration_compatibility`, `storage_key_provider`, `runtime_services`) with finite safe codes. Throwing providers/queries normalize to `query_failed` or `check.invalid`; HTTP returns 503/no-store and CLI returns exit 1 without details.

## Collector Span / Metric / Redaction / Outage / Cleanup Evidence

Consumer evidence passed against pinned Collector `0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd`: server→inline child, deferred producer→two worker consumers (retry), outbox producer→relay, schedule, maintenance, redaction masking, structured JSONL correlation, all ten metric name/type/unit entries, finite metric attributes, and no sentinel leakage. Collector stop left readiness passing and a bounded OTLP wrapper call exited successfully; named containers/network/temp cleanup passed.

## Commands and Results

- Source-of-truth reread: `AGENTS.md`, `develop/STATE.md`, this Task Packet, `develop/spec/README.md`, Specs 09/48/51/99/100, and Decision 136 — completed.
- Composer/package assessment: `composer.json`/`composer.lock` inspected; no Framework dependency change authorized or required at start.
- Collector image assessment: official fixed tag/digest/user/entrypoint recorded from Orchestrator inspection; local image cache was absent at the initial local check because Docker API access was unavailable in this worker shell.
- `docker compose run --rm app vendor/bin/phpunit tests/Observability tests/Http/Observability tests/Console/Observability` — PASS, 11 tests / 33 assertions.
- Task exact focused command `docker compose run --rm app vendor/bin/phpunit tests/Observability tests/Internal/Observability tests/Http tests/Internal/Application` — PASS, 390 tests / 1,488 assertions (existing PHPUnit notices only).
- `bash -n tests/Consumer/opentelemetry-observability.sh` — PASS.
- `bash tests/Consumer/opentelemetry-observability.sh` — PASS (pinned Collector, OTLP HTTP 4318, debug exporter, outage and cleanup evidence).
- Independent final `docker compose run --rm app vendor/bin/phpunit` — PASS, 2,176 tests / 9,025 assertions (1 deprecation, 2 PHPUnit deprecations, and 13 existing notices). An earlier isolated full run had one heartbeat timing assertion; the exact failing test passed without assertion changes and two clean full reruns passed.
- Independent final `bash tests/Consumer/quickstart-e2e.sh` — PASS.
- Independent pre-commit `bash tests/Consumer/framework-package-export.sh` — PASS for the working-tree Composer archive; committed-head Git archive proof remains the post-commit gate.
- Post-commit exact `bash tests/Consumer/framework-package-export.sh` at `f8ebbf0` — PASS; Git and Composer package archives contain the accepted Operational Health source and export contract.
- `docker compose run --rm app composer validate --strict` — PASS.
- `docker compose run --rm app composer audit --locked` and `--locked --no-dev` — PASS, no advisories.
- `docker compose run --rm app mago format src tests` and `mago format --check src tests` — PASS.
- Changed-source Mago lint — PASS (`No issues found`).
- Changed-source Mago analyze — PASS (`No issues found`).
- Broad Mago lint — unchanged baseline of 75 findings (7 errors, 25 warnings, 29 notes, 14 help); broad Mago analyze — unchanged baseline of 24 warnings after the callback normalization correction.
- `docker compose run --rm app vendor/bin/deptrac` — BLOCKED by the existing PHP 8.5 vendor parser error in `NikicFileReferenceVisitor.php:106`; the Observability layer and declared dependency edges were reviewed separately.
- Management-ID guard and `git diff --check` — PASS.

## Acceptance Criteria

- [x] Liveness/Readiness safe Version 1 query, report, and exact checks.
- [x] Explicit PSR-15 HTTP and CLI adapters with no automatic registration.
- [x] API-only Framework package dependency boundary retained; SDK/exporter/client remain require-dev.
- [x] Pinned Collector OTLP HTTP Consumer journey and span/metric/redaction evidence.
- [x] Collector outage isolation and deterministic cleanup.
- [x] Full Suite, Quickstart Consumer, and pre-commit Package Export passed under independent Orchestrator review.

## Remaining Issues

Deptrac remains blocked by the recorded PHP 8.5 vendor parser incompatibility. The committed-head package export passed. No Worker commit/push/deploy was performed.

## Suggested Next Action

Continue to P20-018F documentation and read-only documentation review.
