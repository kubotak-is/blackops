# P16-007 Consumer Experience and Phase Closeout Report

Status: Paused by Orchestrator due to out-of-scope Production blocker

## Summary

P16-007のQuickstart Status Authorizer、Frontend Type Narrowing、Generated `.status()`／`.wait()`のReal HTTP Journey、Skeleton／Publication／Framework Update Guard、主要Guide同期を途中まで実装した。

Real HTTP E2Eで、受付直後の`accepted`は200で取得できる一方、第一Worker Attempt後のDB Stateが`retry_scheduled`になった時点でPublic Status Resourceが500 `internal_error`になることを発見した。Generated `.wait()`も同じ500を受け、仕様どおり即時停止して`internal` Resultを返す。

原因は`OperationStatusJournalValidator`がJournal Identityとしてexecution Actorの全Record同一性を要求していることにある。Quickstartの正規LifecycleではHTTP受付Recordのexecution Actorは`quickstart-user`、Worker Recordは`quickstart-worker-1`であり、origin／authorizationを維持しながらexecutionだけが変わる。このためJournal検証がIntegrity Failureになる。

Production Code修正はP16-007の変更許可範囲外なので実装を広げず、Orchestrator指示により現在の差分を保持して一時停止した。Status Actor Continuity修正は別Task Packet／別Commitで先に扱う。

## Changed Files

### Installed Quickstart

- `examples/quickstart/app/ApplicationServiceProvider.php`
- `examples/quickstart/app/Security/SampleOperationStatusAuthorizer.php`
- `examples/quickstart/tests/.gitignore`
- `examples/quickstart/tests/Frontend/typecheck.ts`
- `examples/quickstart/tests/Frontend/real-http.ts`
- `examples/quickstart/tests/Frontend/wait-signal.ts`
- `examples/quickstart/README.md`

### Consumer／Skeleton／Publication

- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `tests/Consumer/framework-update-generators.sh`

### Guide

- `docs/guide/configuration.md`
- `docs/guide/core-api.md`
- `docs/guide/deployment.md`
- `docs/guide/directory-structure.md`
- `docs/guide/execution.md`
- `docs/guide/first-operation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/security.md`
- `docs/guide/testing.md`
- `docs/guide/troubleshooting.md`

### Orchestration

- `develop/STATE.md`
- `develop/orchestration/reports/P16-007-consumer-experience-and-closeout.md`

## Decisions and Assumptions

- Existing `ReportGenerated` Public Contractの`reportName`／`location`を正本とした。Task Packetの`downloadPath`は誤記としてOrchestratorが訂正し、Outcome Renameは行っていない。
- Missing Sample TokenはAnonymousとなるため、Status AuthorizerのDenyを経てUnknownと同じ404 `unavailable`になる。不正TokenだけがSubject読取前の401 `authentication`になる。
- Framework予約Status GETは空でないBodyをQuery前400にする。Consumerのaccepted確認はBodyを送らず、無関係Headerだけを付与する。
- Structural Abort Signal HelperはApplication APIにせず、DOMなしNode Test専用の`tests/Frontend/wait-signal.ts`へ置いた。Browser向けGuideはnative `AbortController`を使う。
- Production Status Actor Continuityの修正はP16-007へ混ぜない。

## Commands and Results

```text
bash -n tests/Consumer/quickstart-e2e.sh \
  tests/Consumer/skeleton-create-project.sh \
  tests/Consumer/skeleton-publication.sh \
  tests/Consumer/skeleton-publication-workflow.sh \
  tests/Consumer/framework-update-generators.sh
Result: success。

git diff --check
Result: success。

bash tests/Consumer/quickstart-e2e.sh
Result: failed as intended evidence for the discovered blocker。
  - Composer install、Frontend generate／check、DOMなしTypeScript typecheckは成功。
  - Generated `.fetch()`の202と直後`.status()`のaccepted 200は成功。
  - DB Stateは第一Attempt後にretry_scheduledへ到達。
  - 同時点のPublic Status curlは500 {"status":"error","code":"internal_error"}。
  - Generated `.wait()`はinternal:internal_errorで即時停止。
```

Full PHP／Frontend／Consumer／Skeleton／Publication／Website GateはBlocker修正前のため未実行である。Website Publication／Deploy／Cloudflare変更は行っていない。

## Acceptance Criteria

- [x] QuickstartがApplication所有Status Authorizerと明示Bindingを持つ
- [x] Same-origin Actor一致だけAllowし、欠落／不一致をDenyする実装を持つ
- [x] Frontend Typecheckが`.status()`の7 State／Failureと`.wait()`のTerminal／FailureをNarrowingする
- [x] `.fetch()`一回、直後`.status()`一回、有限`.wait()`、短DeadlineのTest Journeyを実装した
- [x] Skeleton／Publication／Framework Update GuardへAuthorizerとFrontend Test Sourceを追加した
- [ ] Generated `.wait()`がWorker Retry後のCompleted Typed Outcomeを返す
- [ ] Real HTTP 401／404／Retry／Terminal／Sensitive Evidenceを完走する
- [ ] Guide／Website Source／Internal Documentationをすべて同期する
- [ ] Full Quality Gateを完走する
- [ ] Phase 16をCloseする
- [x] WorkerはCommitしていない

## Remaining Issues

1. `OperationStatusJournalValidator`がexecution Actorの正規変化をIdentity不整合として拒否する。
2. Production修正受理後にP16-007 Real HTTP E2Eを再実行する必要がある。
3. Quickstart README／Guideは途中まで同期済みだが、Website Source生成、Internal Documentation、Phase Acceptance／TODO／STATE Closeoutは未完了である。
4. Skeleton／Publication／Framework UpdateとFull Gateは未実行である。

## Suggested Next Action

1. OrchestratorがStatus Actor Continuity修正の別Task Packetを作成する。
2. WorkerがJournal Identityではorigin／authorizationの継続性を検証し、Record生成主体であるexecution ActorをIdentityから分離する修正とRegression Testを実装する。
3. Orchestrator受理／別Commit後、P16-007を再開してReal HTTP E2E、残りDocumentation、全Gate、Phase 16 Closeoutを完走する。
