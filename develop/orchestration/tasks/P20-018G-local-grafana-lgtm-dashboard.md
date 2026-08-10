# P20-018G: Local Grafana LGTM Dashboard Evidence

Status: Accepted

## Goal

既存のOpenTelemetry API-only Surfaceから送信したTrace／Metricを、開発者がLocal Grafanaで再現可能かつ安全に閲覧できるConsumer Journeyとして提供する。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/decisions/136-structured-logging-and-opentelemetry.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/orchestration/tasks/P20-018E-operational-health-and-local-collector.md`
- `develop/orchestration/reports/P20-018E-operational-health-and-local-collector.md`
- `develop/orchestration/tasks/P20-018F-observability-documentation.md`
- `develop/orchestration/reports/P20-018F-observability-documentation.md`
- Grafana `docker-otel-lgtm` official documentation and image registry

## Decision Boundary

UserはLocal OpenTelemetryの閲覧BackendとしてGrafanaを選択した。D136／Specification 100がDashboard／Vendor Backendを対象外としているため、本Taskでは新しいDecisionに次を明示する。

- `grafana/otel-lgtm`はDevelopment／Demo／Test専用のApplication-owned local backendとし、Framework-owned Production SDK／Exporter／Collector／Dashboardへ昇格させない
- Imageは`grafana/otel-lgtm:0.29.2@sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c`へ固定する。このDigestはlinux/amd64とlinux/arm64を含むOCI indexである
- Host公開はloopback限定のGrafana `3000`とOTLP HTTP `4318`だけとし、Tempo／Prometheus等のBackend Portを公開しない
- Local Backend停止、Grafana状態、Dashboard状態をLiveness／Readinessへ含めない
- Credential、Remote Backend、Cloud、Production Deploy、永続Volumeは導入しない

## In Scope

- Local Grafana LGTM Development BackendのDecision／Specification境界
- Randomized Docker resourceを使うisolated Consumer journey
- Grafana health、provisioned Tempo／Prometheus datasource、OTLP HTTP ingestionの検証
- BlackOps Traceをexact Trace IDでTempoから取得し、Stable MetricをPrometheusから取得する検証
- Grafana UIでTrace／Metricを閲覧するContributor向け手順
- `4318`は閲覧PageではなくOTLP ingestion endpointであることの説明
- Failure／interrupt時を含むContainer／Network／temporary artifact cleanup
- Observability／Troubleshooting／MVP status／Roadmap／TODO／STATE／Report同期

## Out of Scope

- Production SDK／Exporter／Collector／Dashboardの所有
- Remote Grafana、Grafana Cloud、Credential、TLS、Alert、SLO、Dashboard JSONの配布
- Loki Log Pipeline、Pyroscope Profile、OBI、MCP integration
- Persistent Volume、Retention、Production Compose Default、Framework Package dependency
- Existing OpenTelemetry Adapter、Span／Metric schema、Health／Readiness contractの変更
- Public deployment、push、tag、release

## Files Allowed to Change

- `develop/decisions/138-local-grafana-lgtm-development-backend.md`
- `develop/spec/README.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P20-018G-local-grafana-lgtm-dashboard.md`
- `develop/orchestration/reports/P20-018G-local-grafana-lgtm-dashboard.md`
- `tests/Consumer/opentelemetry-grafana-lgtm.sh`
- `tests/Consumer/fixtures/opentelemetry/emit.php`（既存EmitterをLGTMでも再利用するための最小変更だけ）
- `tests/Consumer/fixtures/opentelemetry/lgtm-query.php`
- `docs/guide/observability.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/mvp-status.md`
- `docs/guide/deployment.md`
- `docs/guide/security.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- Source checkoutをread-only mountし、Main Worktreeで`composer install`を実行しない
- Container／Network名はrandomizedし、既存の`blackops-otel-lgtm`を検証対象またはcleanup対象にしない
- `3000`／`4318`はHost loopbackへだけpublishし、固定Host Port競合を避ける
- Grafana default local loginをTest Log、Report、Artifactへ出力しない
- Backend API Response全体、Trace Payload、Metric Label dump、Container EnvironmentをReportへ貼らない
- Sensitive Sentinel、Raw Actor／Tenant／Key／Payload／Outcome／Exception Detailを出力しない
- Collector／Grafana停止をPrimary OperationまたはReadiness Failureへ結び付けない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはReview前にCommit、Push、Deployしない

## Acceptance Criteria

- [x] New DecisionがLocal LGTMをDevelopment-only Application-owned backendとして確定し、D136／Specification 100のProduction ownership境界を維持する
- [x] Consumerが固定OCI index DigestのLGTMをisolated networkで起動し、Grafana／OTLP HTTPだけをloopback publishする
- [x] ConsumerがGrafana healthとTempo／Prometheus datasource provisioningを確認する
- [x] Existing BlackOps EmitterのTrace／MetricをLGTMへ送信し、exact Trace IDをTempo、`blackops.operation.duration`をPrometheusから機械検証する
- [x] Trace／MetricのSensitive／High-cardinality guardとSource checkout不変を維持する
- [x] Failure／interrupt時を含め、Container、Network、一時Artifactをcleanupする
- [x] Guideが表示された`http://127.0.0.1:<grafana-port>`を閲覧Pageとして使い、表示されたランダムOTLP Host PortおよびContainer内`4318`はUIではなくingestion endpointであること、起動／送信／検索／停止を一つのJourneyとして説明する
- [x] Default Compose、Production dependency、Readiness、Remote／Credential／DeployへScopeが広がっていない
- [x] Existing Collector Consumer、Website check／build、format／management-ID／diff guardがPASSする
- [x] Documentation ReviewerがP1／P2／P3 Findingを返し、P1／P2が0件である

## Required Commands

```bash
bash -n tests/Consumer/opentelemetry-grafana-lgtm.sh
bash tests/Consumer/opentelemetry-grafana-lgtm.sh
bash tests/Consumer/opentelemetry-observability.sh
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P20-018G-local-grafana-lgtm-dashboard.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Image／Supply-chain Evidence
- Grafana／Tempo／Prometheus Probe Matrix
- Security／Port／Cleanup Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
