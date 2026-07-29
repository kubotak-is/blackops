# P20-010: Task-oriented Operations Documentation

Status: Accepted

## Goal

Testing、Deployment、ConsoleCommand、Outbox、BlackOps CLIを、利用者が前提、実行場所、Command、期待結果、Failure境界を補完せず進められるTask-oriented Guideへ増強する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/131-task-oriented-operations-documentation.md`
- `develop/spec/95-task-oriented-operations-documentation.md`
- `develop/decisions/117-documentation-learning-journey.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/92-documentation-review-agent.md`
- Current `src/Internal/Console/**`
- Current `tests/Consumer/**`
- Current `examples/quickstart/**`
- Current `examples/community-board/**`
- Tag `1.1.0` when a Stable capability is claimed

Documentationと実装が矛盾する場合はDocumentationだけで推測補正せず、ReportのBlockerとしてOrchestratorへ返す。

## In Scope

- TestingのLayer選択、実行入口、Negative Path、期待結果
- DeploymentのRelease順、Process Matrix、Smoke、Shutdown、Recovery、Rollback判断
- ConsoleCommandのAttribute、Build、Help、Human／JSON、Exit、Authorization Journey
- OutboxのDispatch、Commit、Relay、Worker、Status／Journal、Retry／Dead Letter Journey
- BlackOps CLIのTask-oriented Command Matrix、主要Option、変更有無、Runtime、Exit／Output、詳細Guide
- 必要最小限のReference／Troubleshooting相互Link
- Source／Link／Content／Artifact Regression
- Decision／Specification／TODO／STATE／Report同期

## Out of Scope

- Framework `src/**`、Test、Generator Stub、Example、Migrationの変更
- Public API、Command、Option、Exit Code、Config Keyの追加または変更
- Authentication、Authorization、Frontend、Database Guideの全面改稿
- New Public Page、Public Slug、Sidebar、Landing、Header、Theme、Search、Redirect
- Scheduled Application Operation
- External Broker、Exactly Once、OpenTelemetry Adapter
- P20-011以降のSite UX、Typography、全Page文章編集
- Stable Tag、Package、Commit、Push、PR、External Deploy

## Files Allowed to Change

- `docs/guide/testing.md`
- `docs/guide/deployment.md`
- `docs/guide/console-command.md`
- `docs/guide/outbox.md`
- `docs/guide/project-cli.md`
- `docs/guide/troubleshooting.md`（既存SectionへのLinkまたは最小限のFailure受け皿のみ）
- `docs/guide/core-api.md`（入口Linkのみ）
- `docs/guide/attributes.md`（入口Linkのみ）
- `docs/guide/configuration.md`（入口Linkのみ）
- `docs/website/tests/**`
- `docs/website/scripts/**`
- `develop/decisions/131-task-oriented-operations-documentation.md`
- `develop/spec/95-task-oriented-operations-documentation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-010-task-oriented-operations-documentation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Stable `1.1.0`へRepository `main`限定Capabilityを混入させない
- Framework専用Testing APIやTest Runnerを創作しない
- Repositoryの`tests/Consumer/*.sh`をInstalled ApplicationのCommandとして案内しない
- Host／Container、Project Root／Repository Rootを無説明に切り替えない
- HTTP、Deferred Worker、Outbox Relay、Maintenance Schedulerを同じProcessとして扱わない
- Maintenance SchedulerをScheduled Application Operationとして説明しない
- Relay完了をchild Handler完了として説明しない
- `--json`、Exit Code、主要OptionをCurrent Source／Testへ照合する
- Raw Credential、Canonical Payload、Throwable、SQLを公開Outputへ追加しない
- Existing Landing、Navigation、Public Slug、Themeを変更しない
- Generated Contentと`dist`を直接編集しない
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
bash tests/Consumer/quickstart-e2e.sh
CI=true bash tests/Consumer/community-board-digest.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Websiteの`check`と`build`は同時実行しない。Consumer Testが環境または時間制約で実行できない場合、未実行理由と代替EvidenceをReportへ明記し、PASSとして扱わない。

## Acceptance Criteria

- [x] TestingがOperation、HTTP、Deferred、Frontend、Browserの5 Layerを選べる
- [x] Testingが主要Negative Pathと期待結果を実行入口へ接続する
- [x] DeploymentがRelease準備からMigration、Build、Process起動、Smoke、停止、Rollback判断までを順に示す
- [x] HTTP Worker、Deferred Worker、Outbox Relay、Maintenance SchedulerのProcess責務をTableで区別する
- [x] ConsoleCommandがAttributeからBuild、Help、Human／JSON、Exit、Authorizationまで完走できる
- [x] OutboxがDispatch、Commit、Relay、Worker、確認、Retry／Dead Letterを区別する
- [x] BlackOps CLIがTask、変更有無、Runtime、主要Option、Exit／Output、GuideからCommandを探せる
- [x] Stable／`main`、Sensitive、未実装、Process境界が実装と一致する
- [x] Existing Public Slug、Navigation、Landing、Theme、Search、Redirectを維持する
- [x] Documentation RegressionとRequired Commandsが成功する
- [x] WorkerはReport／STATE／TODOを同期し、Commitしない

## Completion Report

`develop/orchestration/reports/P20-010-task-oriented-operations-documentation.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
