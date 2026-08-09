# P20-018F Observability Documentation Report

Status: Accepted

## Summary

Repository `main`の試験的なOpenTelemetry API-only Surface、Structured Record Version 1（Application／Framework／Journal／Audit）のCurrent WireとMigration Notice、Telemetry Correlation、W3C Trace Context、Span／Metric、Operational Health、固定DigestのLocal Docker Collector手順をPublic／Internal Documentationへ反映しました。Stable `1.1.0`との境界、Application-owned SDK／Exporter／Route／CLI責務、Collector停止時のFailure Isolationを明記しています。Security／TroubleshootingにもSignal allowlist、Mask、High-cardinality禁止、Collector Failure切り分けを追加しました。WorkerはCommit／Push／Deployしていません。

## Changed Files

- `docs/guide/observability.md`
- `docs/guide/journal.md`
- `docs/guide/deployment.md`
- `docs/guide/mvp-status.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/core-api.md`
- `docs/internal/production-observability.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/94-journal-documentation.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `develop/orchestration/tasks/P20-018F-observability-documentation.md`
- `develop/orchestration/reports/P20-018F-observability-documentation.md`
- `develop/orchestration/reports/P20-018F-documentation-review.md`
- `develop/STATE.md`
- `develop/TODO.md`

Generated `docs/website/src/content/docs/**`と`docs/website/dist/**`は直接変更していません。Website生成が新しいSourceを生成します。

## Decisions and Assumptions

- Public Guideの新規ページを`Reference / Observability`（`/reference/observability/`）へ一度だけ配置し、content-map、sidebar、H1を同期しました。
- Framework Production Dependencyは`open-telemetry/api`、SDK／OTLP Exporter／CollectorはApplication／Consumer-ownedです。
- CollectorはOfficial Image `otel/opentelemetry-collector:0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd`、OTLP HTTP `4318`、Local Networkに固定しました。Remote Backend、Production Compose Default、Credentialは扱っていません。
- Existing Public API inventory grew from 201 to 215 after P20-018E; `core-api.md`とWebsite testの期待値をSourceへ同期しました。
- Documentation ReviewerのRead-only Report本文は変更していません。Orchestratorが別途Browser／Read-only Reviewを再実施し、P1／P2／P3のAcceptance判断を行います。

## Review Finding Resolution

- P1-1: Host／Container両laneのMount元を`otel-collector-config.yaml`へ統一し、Container側のTargetは`/etc/otelcol/config.yaml`であることをSnippetと回帰Testで固定しました。
- P1-2: Host laneは`127.0.0.1:4318:4318`だけを公開し、Container laneは`-p`を使わない構成へ分離しました。
- P1-3: Host (`http://127.0.0.1:4318`)／Container (`http://collector:4318`)のEndpoint、Application／Emitter実行境界、Fresh checkoutでの`docker compose build app`→Consumer script、Emitter Flush／Shutdownを明記しました。Provider例は解決済み全Environment Snapshotを`Environment`と`withEnvironment()`へ同一入力で渡します。
- P1-4: Structured Recordのkind別`operation`／`attempt`／`telemetry` Optional境界、Journalだけの`operation.schemaVersion`、Auditの除外Field、Stable 1.1.0とmainのWire差分をGuideとSpecへ同期しました。
- P2-1: Specification 10／94の「OpenTelemetry未実装／Framework非対応」記述を、mainのAPI-only境界とStable 1.1.0除外、Application-owned SDK／Exporter／Collector責務へ更新しました。
- P2-2: Manual Docker laneはRun ID付きResource、`set -Eeuo pipefail`、`trap cleanup EXIT INT TERM`、再実行前cleanup、Config存在確認を持ち、固定名Resourceの手動削除を不要にしました。Consumer laneはScript内trap cleanupを正本とします。

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（77 tests） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（content、diagram、Blume type check 0 errors／warnings／hints） |
| `mise exec -- pnpm --dir docs/website run build` | PASS（41 public pages、artifact／site checks pass; existing Vite chunk warning and route conflict warning only） |
| `bash tests/Consumer/opentelemetry-observability.sh` | PASS（pinned Collector、Trace／Metric、redaction、health isolation、cleanup） |
| `docker compose run --rm app mago format --check src tests` | PASS（All files are already formatted） |
| `if rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'; then exit 1; else echo pass; fi` | PASS（No forbidden management markers in src/tests PHP） |
| `git diff --check` | PASS |
| Playwright Chromium Browser Review | PASS（7 Routes × Desktop 1440 Light／Dark・Mobile 390 Light／Dark = 28 cases、0 failures） |
| Manual Host Collector boundary | PASS（Collector Ready、`docker port`は`127.0.0.1:4318`のみ、検証用Container／Network cleanup確認） |
| Documentation Reviewer correction re-review | PASS（P1=0、P2=0、P3=0、Acceptance permitted） |

## Browser Evidence

Orchestratorは更新後のStatic Artifactを`mcr.microsoft.com/playwright:v1.61.1-noble`のChromiumで再検証した。Observability、Journal、Security、Troubleshooting、Deployment、Releases、Core APIの7 RoutesをDesktop 1440px Light／DarkとMobile 390px Light／Darkで合計28 cases確認し、HTTP 200、H1、current navigation、theme、keyboard focus、page-wide overflow、heading jump、unnamed visible link、console error、failed requestをすべてPASSした。`/tmp/p20-018f-browser-output/evidence.json`（2026-08-09T11:15:46.927Z）と28 screenshotsを生成し、Observabilityの代表4 screenshotsを目視確認した。外部WebsiteのPublication／Deployは行っていない。

## Acceptance Criteria

- [x] Structured JSONL Version 1 and safe Telemetry projection are documented.
- [x] API-only Framework and Application SDK／Exporter composition is implementable.
- [x] HTTP→Deferred Worker／Retry／Outbox Trace correlation is documented and the focused Consumer passes.
- [x] Span／Metric names, kinds, units, fixed attributes, and cardinality restrictions match Source.
- [x] Sensitive and high-cardinality fields are explicitly prohibited or masked.
- [x] Liveness／Readiness explicit HTTP／CLI composition matches the Public API.
- [x] Pinned Local Collector runbook and failure isolation match the focused Consumer.
- [x] Collector stop／Invalid Context／Provider Failure troubleshooting is safe and bounded.
- [x] Security／Deployment／Reference／Release documentation is synchronized.
- [x] Website test／check／build and focused Consumer pass.
- [x] Desktop Light／Dark and Mobile browser overflow review passed in 28 Chromium cases.
- [x] Documentation Reviewer resolved the initial P1=4／P2=2 findings and returned final P1=0／P2=0／P3=0 with Acceptance permitted.
- [x] Report／STATE／TODO synchronized; no Worker Commit.

## Orchestrator Acceptance

2026-08-09T22:10:24+09:00にOrchestratorはP20-018FをAcceptedとした。初回Documentation ReviewのP1=4／P2=2はHost／Container lane、loopback-only publish、Config名、fresh Contributor prerequisite、cleanup、Structured Record wire、Specification 10／94同期で全件解消し、Correction Re-reviewはP1=0／P2=0／P3=0、Acceptance permittedを返した。OrchestratorはWebsite 77 tests、check、build、exact OpenTelemetry Consumer、Mago format、management-ID guard、diff check、更新ArtifactのChromium 28 casesを独立再実行した。さらに固定Digest CollectorをHost loopbackへ起動し、Readyと`127.0.0.1:4318`限定Publishを確認して対象Container／Networkをcleanupした。Commit前のWorking Treeだけを受入対象とし、Push／Deployは行っていない。

## Remaining Issues

- Existing build emits non-blocking Vite chunk-size and root route-conflict warnings; content、artifact、site checksは失敗していない。
- Remote Collector、Dashboard、Alert、Production DeployはTask scope外である。

## Suggested Next Action

P20-018FをCommitし、次のReady TaskをPhase 21のSource of Truthから開始する。External Publication／Push／Deployは別指示まで行わない。
