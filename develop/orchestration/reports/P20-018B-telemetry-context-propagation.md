# P20-018B: Telemetry Context Propagation Report

Status: Accepted

## Package Assessment

- Candidate: `open-telemetry/api:^1.10`
- Resolved dry-run: `open-telemetry/api` 1.10.0、`open-telemetry/context` 1.5.0、`symfony/polyfill-php82` 1.38.1
- Identity／provenance: OpenTelemetry PHP公式subtree split、Apache-2.0。API source `7c029c4a6fd457094a20569bf98f93d95e9a7559`、Context source `3c414b246e0dabb7d6145404e6a5e4536ca18d07`
- Compatibility: PHP `^8.1`、`psr/log ^1.1|^2|^3`でRepositoryのPHP `>=8.5`／`psr/log ^3.0`と整合する。APIはSDK `<=1.11`とconflictするが、本TaskはSDKをProduction Dependencyへ追加しない
- Solver impact: 3 install、0 update、0 remove。既存Production Packageとの機能重複はなく、既存`psr/log`を共有する
- Executable surface: Composer plugin／binaryなし。APIの`Trace/functions.php`はSpan scope helper functionだけを定義する。Contextの`fiber/initialize_fiber_handler.php`はZend Observer Fiberが有効な場合だけhandlerを初期化する。PolyfillはPHP compatibility shimである
- Security: Composer dry-runはsecurity vulnerability advisory 0。Raw Git source、Package metadata、autoload filesを導入前に確認した
- Adoption／maintenance: OpenTelemetry公式PHP API release、Packagistの広い利用実績、継続Releaseを確認した
- Decision: Accept。Production `require`は`open-telemetry/api`だけとし、SDK／Exporter／Collectorを追加しない

## Summary

Implemented API-only W3C trace context propagation from HTTP/direct roots through immutable ExecutionContext, protected deferred/outbox context codecs, worker retry context reuse, and safe journal/JSONL correlation projection. No SDK, exporter, collector, span, metric, or health surface was added.

## Changed Files

- `composer.json`, `composer.lock`: `open-telemetry/api:^1.10` with resolved API 1.10.0, context 1.5.0, polyfill 1.38.1.
- `src/Telemetry/TelemetryContext.php`, `TelemetryCorrelation.php`, `src/Internal/Http/TelemetryContextExtractor.php`.
- `ExecutionContext`, factory, JSON codec, dispatcher/HTTP acceptor plumbing, journal builder/projector/JSONL and PostgreSQL canonical codec.
- Focused telemetry, codec, HTTP, journal, protected deferred, and compatibility tests.
- `develop/spec/100-structured-logging-and-opentelemetry.md` contract clarification.
- `deptrac.yaml`, `develop/spec/16-namespace-dependencies.md`: explicit Telemetry/OpenTelemetry layers and narrowly documented Core↔Telemetry marker cycle.

## Propagation Matrix

| Boundary | Evidence | Result |
| --- | --- | --- |
| Direct root / HTTP | `TelemetryContextTest`, `TelemetryContextExtractorTest`, `OperationRequestHandlerTest` | Valid carrier reaches direct Dispatcher and Deferred acceptor; malformed/multiple carrier becomes root-safe `null`, and validation rejection records inherit the valid parent. |
| Child / attempt | `ExecutionContextFactoryTest`, `DeferredWorkerRuntimeTest` | Child, attempt, and retry attempt preserve immutable telemetry context. |
| Deferred / outbox protected context | `ExecutionContextJsonCodecTest`, `PostgreSqlDeferredAcceptanceOrchestratorTest`, `TransactionalOutboxRuntimeTest`, existing protected Outbox suites | Optional field decrypts through existing BOPD context; ciphertext has no raw sentinel and schema has no clear telemetry columns. |
| Worker retry / replay | Backward-compatible context and canonical PostgreSQL journal codec tests | Persisted context/correlation is reused; legacy payloads decode without telemetry. |

## Protection and Redaction Evidence

- Invalid carrier exceptions omit the supplied raw value; extractor catches all validation failures and returns `null`.
- TraceState is validated locally without OpenTelemetry warning paths that echo untrusted input; duplicate, >32-member, >512-byte, control/newline, zero and malformed matrices are covered.
- Execution context telemetry is inside the existing protected context payload; journal and JSONL expose only `traceId`, `spanId`, and `sampled` as top-level safe correlation. Actor/tenant/value/baggage/raw carrier fields are not added.

## Decisions and Assumptions

- Local Docker Collectorのactual E2EはConsumer-owned SDK／Exporterを扱うP20-018Eで実施する。
- Traceparent policy is intentionally W3C version `00` only for this serializable boundary; `ff`, future-version carriers, malformed flags, zero IDs, and newline variants are rejected without raw echo. Future-version support remains a later compatibility decision.

## Commands and Results

- `docker compose run --rm app composer show open-telemetry/api 1.10.0 --all`: PASS
- `docker compose run --rm app composer show open-telemetry/context 1.5.0 --all`: PASS
- `docker compose run --rm app composer show symfony/polyfill-php82 1.38.1 --all`: PASS
- `docker compose run --rm app composer require open-telemetry/api:^1.10 --dry-run --no-interaction`: PASS。3 install、0 update、0 remove、advisory 0。Working Treeは変更なし
- Official source `composer.json`／autoload files inspection: PASS
- `docker compose run --rm app composer require open-telemetry/api:^1.10 --no-interaction`: PASS; 3 installs, 0 updates/removals, no advisories.
- Focused PHPUnit (`tests/Telemetry`, Core ExecutionContext, Codec, Internal HTTP, PostgreSQL journal/deferred): PASS (52 tests / 139 assertions in final propagation/protected batch; earlier Core/HTTP batch 116 tests / 463 assertions).
- `docker compose run --rm app vendor/bin/phpunit`: Orchestrator final rerun PASS (2,129 tests / 8,742 assertions; 1 pre-existing deprecation).
- Orchestrator focused gate PASS (921 tests / 3,802 assertions), Quickstart Consumer E2E PASS, and pre-commit package export PASS. Post-commit package export remains pending the Orchestrator commit.
- `docker compose run --rm app composer validate --strict`: PASS.
- `docker compose run --rm app mago format --check src tests`: PASS after formatting changed files.
- Changed-source `mago lint`: no P20-018B errors; two existing halstead warnings remain in `InlineDispatcher::dispatchEnvelope` and `StructuredJsonlFormatter::format`.
- Changed-source `mago analyze`: PASS, `INFO No issues found.` after local exact ID validation, integer flag casts, optional telemetry caching/null guards, and PostgreSQL decoder annotations.
- `git diff --check`: PASS; management-ID guard: PASS.
- `docker compose run --rm app composer audit --no-interaction`: PASS; no security vulnerability advisories.
- `tests/Internal/Outbox/TransactionalOutboxRuntimeTest.php`: PASS (14 tests / 47 assertions), including child telemetry inheritance from the transactional outbox parent.
- `tests/Internal/Execution/DeferredWorkerRuntimeTest.php`: PASS (34 tests / 414 assertions), including persisted trace context equality across retry attempt 1 and 2.
- `tests/Internal/Logging/MonologJsonlLoggerFactoryTest.php`: PASS (14 tests / 83 assertions), actual application/framework JSONL top-level telemetry and null omission.
- `tests/Http/HttpValidationLifecycleTest.php`: PASS (10 tests / 95 assertions), binding/value rejected Journal operations retain the exact incoming correlation.
- `docker compose run --rm app vendor/bin/deptrac`: BLOCKED by existing PHP 8.5 parser incompatibility at `vendor/deptrac/deptrac/src/DefaultBehavior/Ast/Parser/Helpers/NikicFileReferenceVisitor.php:106`; no new boundary finding was evaluated.

## Acceptance Criteria

All Task Packet criteria are checked in the Task Packet. Package Export remains an Orchestrator post-commit gate; Deptrac is blocked only by the recorded vendor PHP 8.5 parser incompatibility.

## Remaining Issues

- Broad Mago lint retains repository baseline findings (including existing complexity/kan-defect findings); Deptrac PHP 8.5 vendor parser limitation remains the known baseline. Full PHPUnit reports one pre-existing deprecation.

## Suggested Next Action

Commit the accepted P20-018B change, rerun the exact package export against committed `HEAD`, and proceed to P20-018C. Local Collector actual E2E remains P20-018E.
