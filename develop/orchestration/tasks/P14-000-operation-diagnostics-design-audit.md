# P14-000: Operation Diagnostics Design Audit

Status: Ready

## Goal

Phase 14 Operation Diagnosticsの実装前に、既存のHTTP Error、Application Log、Canonical Journal、Operation Lifecycle Store、Outcome Store、Console Command、Sensitive Projection、Retention境界を監査する。

`operation:inspect`、Development限定Local Viewer、Production Observabilityを同じOperation ID Query Modelへ接続するために、再利用できるContractと不足しているContractを証拠付きで整理し、User判断が必要な選択肢だけをDecision Draftへまとめる。

## In Scope

- HTTP Error ResponseがOperation IDを返す／返さないLifecycle境界の一覧化
- Framework/Application LogとOperation ID相関の現状確認
- PostgreSQL Canonical Journal、Lifecycle State、Attempt、Outcome、Dead LetterのQuery能力監査
- Missing／Purged／Unauthorized Operation IDを区別できる既存情報の確認
- Sensitive ProjectionとCanonical Raw Dataの責任境界監査
- Retention／Hold後にDiagnosticsへ残る情報の確認
- Console Command登録、Application Configuration、DB Connection再利用可能性の確認
- Terminal `operation:inspect`最小Vertical Sliceの候補設計
- Development Local Viewerの起動、Bind、Access、表示データ候補
- Production Log／Journal Query／Remote Observabilityの責任分界候補
- Decision 097 Draft、Phase 14 Specification／Task分割案、Report／STATE更新

## Out of Scope

- Production Code、Public API、Migration、Configurationの変更
- `operation:inspect`またはViewerの実装
- HTTP Status／Outcome APIの実装
- OpenTelemetry Adapter、Remote Collector、Dashboardの実装
- Retention PolicyまたはCanonical Journal Formatの変更
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/05-http.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/22-journal-record-schema.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/decisions/089-validation-rejection-sensitive-journal.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`

## Files Allowed to Change

- `develop/decisions/097-phase-14-operation-diagnostics.md`
- `develop/orchestration/reports/P14-000-operation-diagnostics-design-audit.md`
- `develop/STATE.md`

Source、Test、公開Guide、既存Specification、TODOは変更しない。仕様矛盾または実装前提の不足はReportとDecision Draftへ記録する。

## Audit Questions

1. Operation IDはProtocol Error、Authentication Failure、Binding Failure、Validation Rejection、Authorization Rejection、Handler Throwable、Deferred Failureのどこで作られ、Response／Log／Journalへどう露出するか。
2. 一つのOperation IDから、現在State、Attempt Timeline、Retry／Dead Letter、Terminal Error、Typed Outcomeを取得するためにどのStore／Readerが必要か。
3. Canonical Raw Value／Actor／Errorと、Terminal／Viewerへ安全に出せるProjectionの境界はどこか。
4. Missing、Purged、Unauthorizedを情報漏えいなく区別するか、同一表示へ畳むべきか。
5. `operation:inspect`のDefault出力、`--json`、Exit Code、DB接続、Outcome decode failureをどう扱うべきか。
6. Local ViewerはFramework内蔵Server、Application HTTP Route、Static Assetのどれが最小で、Local Bind／明示起動／Access制御をどう保証するか。
7. Productionでは何をFramework Logへ出し、何をApplication／Observability Adapterの責務とするか。

## Expected Decision Draft

最低限、次を選べる形でRecommendationとTrade-offを提示する。

- Phase 14の初期実装順序とLocal Viewerを同Phaseへ含めるDepth
- Diagnostics Query ModelのVisibilityとPublic API範囲
- `operation:inspect`のHuman／JSON Contract
- Sensitive／Actor／Error表示Defaultと明示Option
- Missing／Purged／Unauthorizedの表示契約
- Local Viewerの起動方式、Bind、Access境界
- Production Logging／ObservabilityのPhase 14実装範囲

## Acceptance Criteria

- [ ] HTTP Error／Log／Journal／Outcome相関の現状をLifecycle別に一覧化する
- [ ] 再利用可能なReader／Store／Console／Configuration ContractをFile単位で示す
- [ ] Terminal Inspect最小SliceのInput／Output／Failure Contract案を示す
- [ ] Local ViewerとProduction Observabilityの安全境界案を示す
- [ ] Phase 15／16／18へ送るScopeを明確にする
- [ ] Decision 097 DraftへRecommendation付き選択肢を記録する
- [ ] Phase 14のSpecification／Task Packet分割案を示す
- [ ] Production Codeを変更しない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
rg -n "OperationId|operationId|operation_id|Journal|Outcome|DeadLetter|Throwable|Logger" src tests
rg -n "add\(|Command|operation:list|outcome" src/Internal/Console src/Application tests 2>/dev/null
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Internal/Http tests/Internal/Journal tests/Internal/Outcome tests/Internal/Console tests/Transport/PostgreSql
git diff --check
```

存在しないTest DirectoryはCommandから除外し、Reportに記録する。AuditでProduction Codeの不具合を発見しても修正せず、証拠と最小再現をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P14-000-operation-diagnostics-design-audit.md`へSummary、Evidence Inventory、Lifecycle Correlation Matrix、Reusable Contracts、Gaps、Security／Retention Boundaries、Recommended Vertical Slices、Decision Questions、Commands and Results、Remaining Issues、Suggested Next Actionを記録する。
