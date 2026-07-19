# P16-007 Consumer Experience and Phase Closeout Report

Status: Paused by Orchestrator due to second out-of-scope Production blocker

## Summary

P16-007のQuickstart Status Authorizer、Frontend Type Narrowing、Generated `.status()`／`.wait()`のReal HTTP Journey、Skeleton／Publication／Framework Update Guard、主要Guide同期を途中まで実装した。

P16-003Aでexecution Actor Continuity修正がAcceptedされた後、Quickstartの明示Status Authorizer登録に合わせて既存Application Runtime TestをStatus 200契約へ同期し、Real HTTP E2Eを再開した。Application Runtime Testは成功し、Real HTTPでも受付直後の`accepted`は200で取得できた。

第一Worker Attempt後のDB Stateは正しく`retry_scheduled`へ到達し、P16-003A修正後のInternal DiagnosticsもFoundを返した。しかしPublic Status Resourceは引き続き500 `internal_error`になった。第二の原因は、PostgreSQL Journal Data CodecがRetry予定時刻を`DATE_ATOM`でEncodeしてマイクロ秒を失う一方、Operations Rowの`available_at`はマイクロ秒を保持することにある。

実EvidenceではOperations Rowが`2026-07-19 14:22:56.143069+00`、Journalの`AttemptRetryScheduledData.scheduledAt`が`2026-07-19T14:22:56.000000Z`だった。Status Sourceは正しく両時刻の同一性を検証するためIntegrity Failureになり、安全なHTTP 500へ畳まれる。

Production Codec修正はP16-007の変更許可範囲外なので実装を広げず、Orchestrator指示により現在の差分を保持して再度一時停止した。Canonical Retry時刻の精度修正を別Task Packet／別Commitで先に扱う。

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
- `tests/Integration/ApplicationHttpRuntimeTest.php`
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
- Production Status Actor ContinuityとCanonical Retry時刻精度の修正はP16-007へ混ぜない。
- Quickstart `ApplicationServiceProvider`は明示Status Authorizerを登録するため、同ProviderをCompileするApplication Runtime Testはsame-origin ActorのStatus 200を期待する。Framework既定Denyは専用Resolver／Public Authorization Testで維持する。

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
Result: P16-003A受理後も第二Production blockerのEvidenceを得て失敗。
  - Composer install、Frontend generate／check、DOMなしTypeScript typecheckは成功。
  - Generated `.fetch()`の202と直後`.status()`のaccepted 200は成功。
  - DB Stateは第一Attempt後にretry_scheduledへ到達。
  - Internal Diagnosticsはretry_scheduledをFoundへ投影。
  - Operations Row `available_at`: `2026-07-19 14:22:56.143069+00`。
  - Journal `scheduledAt`: `2026-07-19T14:22:56.000000Z`。
  - 同時点のPublic Status curlは500 `{"status":"error","code":"internal_error"}`。
  - Generated `.wait()`はinternal:internal_errorで即時停止。

docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Integration/ApplicationHttpRuntimeTest.php
Result: OK (6 tests, 73 assertions)。Quickstart明示Status Authorizerのaccepted 200、`private, no-store`、`Retry-After: 1`を確認。
```

Full PHP／Frontend／Consumer／Skeleton／Publication／Website GateはBlocker修正前のため未実行である。Website Publication／Deploy／Cloudflare変更は行っていない。

## Acceptance Criteria

- [x] QuickstartがApplication所有Status Authorizerと明示Bindingを持つ
- [x] Same-origin Actor一致だけAllowし、欠落／不一致をDenyする実装を持つ
- [x] Frontend Typecheckが`.status()`の7 State／Failureと`.wait()`のTerminal／FailureをNarrowingする
- [x] `.fetch()`一回、直後`.status()`一回、有限`.wait()`、短DeadlineのTest Journeyを実装した
- [x] Skeleton／Publication／Framework Update GuardへAuthorizerとFrontend Test Sourceを追加した
- [x] Quickstart明示Status AuthorizerをCompileするApplication Runtime Testを200契約へ同期した
- [ ] Generated `.wait()`がWorker Retry後のCompleted Typed Outcomeを返す
- [ ] Real HTTP 401／404／Retry／Terminal／Sensitive Evidenceを完走する
- [ ] Guide／Website Source／Internal Documentationをすべて同期する
- [ ] Full Quality Gateを完走する
- [ ] Phase 16をCloseする
- [x] WorkerはCommitしていない

## Remaining Issues

1. P16-003Aのexecution Actor Continuity修正はAccepted済みである。
2. `PostgreSqlJournalDataCodec`が`AttemptRetryScheduledData.scheduledAt`を`DATE_ATOM`でEncodeし、Operations Rowが保持するマイクロ秒をCanonical Journalで失う。StatusのRetry時刻整合性検証がIntegrity Failureになる。
3. Canonical Retry時刻精度修正の受理後にP16-007 Real HTTP E2Eを再実行する必要がある。
4. Quickstart README／Guideは途中まで同期済みだが、Website Source生成、Internal Documentation、Phase Acceptance／TODO／STATE Closeoutは未完了である。
5. Skeleton／Publication／Framework UpdateとFull Gateは未実行である。

## Suggested Next Action

1. OrchestratorがCanonical Retry時刻精度修正の別Task Packetを作成する。
2. WorkerがJournal Data Codecでマイクロ秒を保持し、Retry ScheduledのOperations Row／Journal一致とStatus Foundを実DB回帰で固定する。関連するCanonical Timestamp Fieldの同じ精度問題もTask Packetで範囲を明示する。
3. Orchestrator受理／別Commit後、P16-007を再開する。
4. Application Runtime 200同期済み差分を維持したままReal HTTPの202→accepted→retry_scheduled→completed Typed Outcomeと有限Timeoutを再実行する。
5. 残りDocumentation、Skeleton／Publication／Framework Update、全Gate、Phase 16 Closeoutを完走する。
