# P20-014E: Scheduled Operation Guide and Documentation Review

Status: Accepted

## Goal

Repository `main`で実装済みのScheduled Application Operationを、利用者がAuthoring、Build、Migration、一回実行、外部Supervisor、Journal／Occurrence確認、Failure対応まで補完せず進められる公開Guideへ統合する。

`#[ScheduledBy]`とExecution Strategy、Application ScheduleとFramework Maintenance Scheduler、Stable `1.1.0`とRepository `main`の境界を明確にし、Read-only Documentation ReviewerによるAccuracy／Journey／Browser Reviewまで完了する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/03-execution.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/31-deferred-claim-and-attempt.md`
- `develop/spec/32-worker-crash-recovery.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/40-project-cli.md`
- `develop/spec/59-documentation-reader-experience.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/84-documentation-learning-journey.md`
- `develop/spec/90-documentation-third-review-accuracy.md`
- `develop/spec/92-documentation-review-agent.md`
- Accepted P20-014A〜D Task、Report、Source、Test、Consumer Evidence
- Current `docs/guide/**`、`docs/website/**`
- Stable tag `1.1.0` when a Stable capability is claimed

Documentationと実装が矛盾する場合はDocumentationだけで推測補正せず、ReportのBlockerとしてOrchestratorへ返す。

## In Scope

- New public Scheduled Application Operation guide and discoverable navigation
- `#[ScheduledBy]` canonical authoring with `#[OperationType]`
- Inline default and `#[Deferred]` strategy independence
- Schedule name、5-field Cron、IANA timezone、DST、Value shape
- `ExecutionContext::schedule()` and nullable behavior outside Scheduled Root
- `ScheduledActorProvider` implementation and Service Provider registration for `#[Authorize]`
- `build:compile`、`database:migrate`、`operation:schedule:run [--json]`
- Human／JSON counts and Exit 0／1／2
- First evaluation、misfire、overlap、crash recovery、fixed Operation ID、at-least-once
- Inline completion、Deferred acceptance、Worker completion
- Safe PostgreSQL occurrence inspection and Canonical Journal correlation
- External cron／systemd timer／Kubernetes CronJob responsibility without inventing generated manifests
- Application Schedule process and Maintenance `scheduler:*` process separation
- Deployment／Troubleshooting／CLI／Attributes／Core API／Journal／Releases synchronization
- Existing “Scheduled Application Operation is not implemented” claims removal
- Website content map、sidebar、links、search／artifact regression
- Changed-page Desktop 1440px Light／Dark and Mobile 390px browser verification
- Read-only Documentation Reviewer and evidence-based correction cycle
- Report／STATE／TODO synchronization

## Out of Scope

- Framework `src/**`、PHP Test、Migration、Example、Consumerの変更
- Public API、Command、Option、Exit Code、Config Key、Database Schemaの変更
- Application Schedule Daemon
- Supervisor／Kubernetes／systemd manifest generator
- Arbitrary Misfire／Overlap Policy、Catch-up、Rate Limit
- Exactly-once guarantee
- Schedule payload／credential／actor metadata
- Schedule-specific Retention implementation
- Landing、Header、Theme、unrelated Page editorial rewrite
- Stable `1.1.0` tag、Package、Commit、Push、PR、External Deploy

## Required Reader Journey

1. Repository `main`限定の試験的Capabilityで、Stable `1.1.0`にはないことを確認する。
2. 引数なしで構築できるValueと、Scheduleごとに一意な名前を持つOperationを書く。
3. InlineはAttribute不要、Deferredは`#[Deferred]`を追加する。
4. `#[Authorize]`を使う場合だけApplication-owned `ScheduledActorProvider`をService Providerで登録する。
5. `database:migrate`と`build:compile`を実行する。
6. Project Rootで`php blackops operation:schedule:run --json`を一回実行する。
7. Inlineは同Processで完了し、Deferredは受理後に`worker:run`で完了することを確認する。
8. `evaluated`、`accepted`、`skipped_misfire`、`skipped_overlap`、`failed`とExit Codeを読む。
9. Safe occurrence columnsとOperation IDを確認し、`operation:inspect`／Journalへ同じIDで相関する。
10. Productionでは外部Supervisorからone-shot Commandを起動し、Maintenance `scheduler:*`と別Processとして監督する。

## Files Allowed to Change

- `docs/guide/scheduled-operation.md`
- `docs/guide/operations.md`
- `docs/guide/execution.md`
- `docs/guide/execution-context.md`
- `docs/guide/authorization.md`
- `docs/guide/journal.md`
- `docs/guide/deployment.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/project-cli.md`
- `docs/guide/attributes.md`
- `docs/guide/core-api.md`
- `docs/guide/mvp-status.md`
- `docs/guide/outbox.md`
- `docs/guide/glossary.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/tests/**`
- `docs/website/scripts/**`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-014E-scheduled-operation-guide-and-documentation-review.md`

上記以外が必要なら変更を広げずReportのBlockerとして返す。

## Implementation Constraints

- Repository `main`の実装をStable `1.1.0`へ混入させない
- `ScheduledBy`をExecution Strategyとして説明しない
- Inlineへ`#[ExecuteWith(...Inline)]`を復活させない
- `ConsoleCommand`手動実行をScheduled Occurrenceとして説明しない
- Maintenance `scheduler:run`／`scheduler:daemon`でApplication Operationが起動すると説明しない
- `operation:schedule:run`をDaemonまたはProcess Supervisorとして説明しない
- FrameworkがExternal Side EffectをExactly Onceにすると説明しない
- Skip OccurrenceへOperation IDがあると説明しない
- Attribute、Value、Manifest、JournalへCredentialやRaw Actor IDを保存する例を出さない
- `ScheduledActorProvider`を`ConsoleActorProvider`と共用しない
- Schedule Valueへ`scheduledAt`や任意Payloadを注入せず、`ExecutionContext::schedule()`を使う
- Direct SQLはFramework StorageのRead-only診断例と明示し、Canonical Value／Outcome／Credentialを取得しない
- Existing Public Slug、Landing、Header、Theme、Search、Redirectを壊さない
- Generated Contentと`dist`を直接編集しない
- Website `check`と`build`を並列実行しない
- Documentation ReviewerはRead-onlyで本文を修正しない
- Existing Phase 20 Working Tree差分を保持する
- WorkerはCommitしない

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app vendor/bin/phpunit tests/Core/Attribute/ScheduledByTest.php tests/Core/ScheduleContextTest.php tests/Scheduling/ScheduledActorProviderTest.php tests/Internal/Console/ScheduledOperationRunCommandTest.php
bash tests/Consumer/scheduled-operation.sh
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Documentation Reviewerは最新ArtifactまたはLocal Dev Serverを使い、changed routesをDesktop 1440px Light／DarkとMobile 390pxで実測する。Write-producing website commandsはOrchestratorが実行または明示許可する。

## Acceptance Criteria

- [x] Scheduled Operation GuideがRequired Reader Journeyを順に完走できる
- [x] Stable `1.1.0`とRepository `main`の境界が明示される
- [x] `ScheduledBy`、Strategy、Cron、Timezone、DST、Value shapeが実装と一致する
- [x] Schedule ContextとAuthorized ScheduleのProvider登録例が実装可能である
- [x] Build／Migration／one-shot CLI／Workerの順序と期待結果が明示される
- [x] Human／JSON counts、Exit 0／1／2、安全なError境界が実装と一致する
- [x] Misfire／Overlap／Crash Recovery／at-least-onceの保証境界が正確である
- [x] Occurrence／Operation ID／Journalの相関と安全な診断手順が示される
- [x] Application ScheduleとMaintenance SchedulerがNavigation／CLI／Deploymentで区別される
- [x] Attributes／Core API／Journal／Releases／Troubleshootingが新Capabilityへ同期する
- [x] 旧い未実装Claimが残らない
- [x] Existing Public Slug、Landing、Header、Theme、Search、Redirectを維持する
- [x] Website Test／Check／Build、Focused PHP、Consumer、Format、Guardが成功する
- [x] Desktop Light／DarkとMobileでNavigation、Code、Table、CalloutにPage-wide overflowがない
- [x] Documentation ReviewerのP1／P2 Findingが解消され、P3をAcceptance判断へ記録する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-014E-scheduled-operation-guide-and-documentation-review.md`へSummary、Changed Files、Reader Journey Matrix、Stable／main Matrix、Command／Exit Matrix、Process Boundary Matrix、Accuracy Evidence、Commands and Results、Browser Evidence、Documentation Reviewer Findings、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
