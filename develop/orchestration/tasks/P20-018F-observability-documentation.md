# P20-018F: Observability Documentation and Review

Status: Ready

## Goal

Structured JSONL、OpenTelemetry Provider Composition、W3C Propagation、Trace／Metric、Health／Readiness、Local Collector検証を利用者が安全に導入／運用できるPublic／Internal Documentationへ反映し、Read-only Documentation Reviewerで受入する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/92-documentation-review-agent.md`
- `develop/spec/97-documentation-editorial-style.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- P20-018A〜E Accepted Source、Tests、Reports

## Dependencies

- P20-018A〜E Accepted

## In Scope

- Structured Record Version 1とMigration Notice
- Application／Framework／Journal／Audit Field Table
- Safe Actor／Tenant／Failure Projection
- Application-owned SDK／Exporter／Collector Composition
- W3C HTTP／Deferred／Worker／Outbox Trace Journey
- Span／Metric Name／Kind／Unit／Cardinality Reference
- Liveness／Readinessの明示Route／CLI Composition
- Local Docker Collector Runbook
- Collector／Exporter Failure、Long-running Scope、Troubleshooting
- Security／Deployment／Reference／Release Status同期
- Website Test／Check／Build、Responsive Browser Review
- Read-only Documentation Reviewer FindingとOrchestrator Acceptance

## Out of Scope

- Production Code／Dependency／Migration変更
- Vendor固有Backend／Dashboard／Alert手順
- Production Collector Deploy
- External Website Publication／Deploy

## Files Allowed to Change

- `docs/guide/**`
- `docs/internal/**`
- `docs/documentation-review.md`
- `docs/website/src/**`
- `docs/website/tests/**`
- Documentation-related Consumer fixtures/tests only
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/94-journal-documentation.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-018F-observability-documentation.md`
- `develop/orchestration/reports/P20-018F-documentation-review.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Public Guideは実装済みAPI、Package、Command、Option、Pathだけを記載する
- Framework API-only DependencyとApplication SDK／Exporter責任を混同しない
- CollectorはLocal VerificationでありFramework Default／Production保証とClaimしない
- `latest` Image、実Credential、Vendor TokenをExampleへ置かない
- Raw Tenant／Actor／Key／Payload／Outcome／Exception DetailをScreenshot／Fixtureへ置かない
- Metric Labelへ個別Identityを追加する例を書かない
- Health／Readiness Routeが自動登録されると書かない
- Exporter FailureがReadiness／Operation Failureになると書かない
- Stable 1.1.0とRepository `main`のExperimental Surfaceを混同しない
- Documentation ReviewerはRead-onlyで本文／Production Codeを修正しない
- External Publication／Deployを行わない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [ ] ReaderがStructured JSONLのCurrent WireをParseできる
- [ ] API-only FrameworkとApplication SDK／Exporterの構成例が実装可能である
- [ ] HTTP→Deferred Worker／Retry／OutboxのTrace相関を再現できる
- [ ] Span／Metric Referenceが実装のName／Kind／Unit／属性へ一致する
- [ ] 高Cardinality／Sensitive禁止Fieldが明確である
- [ ] Liveness／Readinessの明示Route／CLI Journeyが実装と一致する
- [ ] Pinned Local Collector RunbookがConsumerと同じ手順で完走する
- [ ] Collector停止／Invalid Context／Provider Failure TroubleshootingがSafeである
- [ ] Security／Deployment／Reference／Release Statusが同期する
- [ ] Website Test／Check／BuildとFocused Consumerが成功する
- [ ] Desktop Light／DarkとMobileでTable／Code／DiagramにPage-wide overflowがない
- [ ] Documentation ReviewerのP1／P2 Findingが解消され、P3をAcceptance判断へ記録する
- [ ] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
bash tests/Consumer/opentelemetry-observability.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Project CLI|ExecuteWith\([^)]*Inline' docs/guide docs/internal
git diff --check
```

## Completion Report

Workerは`develop/orchestration/reports/P20-018F-observability-documentation.md`へ必須項目とBrowser Evidenceを記録する。Documentation Reviewerは`develop/orchestration/reports/P20-018F-documentation-review.md`へRead-only Findingを記録し、OrchestratorがAcceptanceする。
