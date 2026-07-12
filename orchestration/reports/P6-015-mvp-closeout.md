# P6-015 MVP Closeout Report

## Summary

BlackOps MVPのDefinition of Doneを現行Production Code、Test、Task Report、最終Commandへ対応付け、10項目すべてをSatisfiedと確認した。

Application開発者向けMVP Status、Framework実装者向けArchitecture OverviewとInline／Deferred／障害時Sequence Diagramを追加した。DOCS／Guide／Internalsの入口を現行文書へ接続し、歴史的な仮称を現行APIと区別した。

TODOは一括完了にせず、MVP関連で実装またはDecisionの証拠があるstale項目だけを現行名称へ同期して完了とした。曖昧な設計課題、未実装機能、明示的なPost-MVP項目は未完了のまま維持した。

## MVP Definition of Done Evidence

| Definition of Done | Status | Evidence |
| --- | --- | --- |
| PHP 8.5で実行できる | Satisfied | `composer.json`はPHP `>=8.5`。Docker Compose上のPHP 8.5.7でSample E2Eと全Testが成功。 |
| SampleのInline／Deferred Operationが動く | Satisfied | `MvpSampleEndToEndTest::testCompiledSampleRunsInlineAndDeferredAcrossWorkerRestart` とP6-012 Reportが `GET /welcome`／`POST /reports` を検証。 |
| 全Lifecycle JournalをOperation IDで追跡できる | Satisfied | Sample E2EがInline／Deferred lifecycle順を検証。`LifecycleStateMachineTest` と `DeferredWorkerRuntimeTest` がRejected、Failed、Retry Scheduled、Dead Letteredを含む標準eventを検証。 |
| HTTP 200／202とOperation IDが返る | Satisfied | Sample E2EでInline Welcomeの200、Deferred Reportの202と`DeferredAcknowledgement.operationId`を検証。D017の決定どおりOperation ID返却はDeferred受付Contract。 |
| Worker再起動後も未処理Deferred Operationを実行できる | Satisfied | Sample E2EはHTTP、Worker A、再起動Worker Bに別DBAL Connection／別Compiled Containerを使い、PostgreSQLから同じOperationを再claimして完了。 |
| Handler例外をAttemptFailedとして記録できる | Satisfied | Sample Attempt 1と`DeferredWorkerRuntimeTest`が安全な `attempt.failed` DataをCanonical Journalへ保存。 |
| 最低一回のRetryを実行できる | Satisfied | SampleがAttempt 1失敗後に `attempt.retry_scheduled` を記録し、Attempt 2でtyped Outcomeまで完了。 |
| Sensitive Filterの最小実装がある | Satisfied | SampleはCanonical Received Recordへ再現用tokenを保持する一方、Observed Projection／JSONLではmask。`SensitiveProjectionFilterTest`がOmit／Mask／HMACも検証。 |
| Manifest／Container Compileが成功する | Satisfied | Sample E2EがOperation Manifest、HTTP Manifest、Symfony DI Containerを同じBuild IDでcompileし、Production Artifact Loaderからload。P6-001／P6-003 Reportがversion／build／FastRoute validationを補強。 |
| Unit TestとIntegration Testが通る | Satisfied | Sample E2E 1 test / 34 assertions、全PHPUnit 586 tests / 1899 assertions、Mago、Deptrac、Composer validationが最終成功。 |

## Changed Files

- `TODO.md`
- `DOCS.md`
- `docs/guide/README.md`
- `docs/guide/mvp-status.md`
- `docs/internals/README.md`
- `docs/internals/architecture.md`
- `orchestration/tasks/P6-015-mvp-closeout.md`
- `orchestration/reports/P6-015-mvp-closeout.md`
- `orchestration/STATE.md`

Production CodeとTestは変更していない。

## Decisions and Assumptions

- D017の「HTTP 200／202とOperation ID」は、同Decisionの項目4-5に従いInlineはHTTP 200、DeferredはHTTP 202とOperation IDを返す要件として評価した。Inline ResponseへのOperation ID追加は行っていない。
- 「全Lifecycle Journal」は一つのSampleだけに全Terminal分岐を詰め込まず、Sample E2E、Lifecycle State Machine Test、Deferred Worker Runtime Testの合成証拠で評価した。
- D017の初期Local Transport候補は後続DecisionによりPostgreSQL Reference Transportへ確定済みとして評価した。
- Canonical Journalは再現性のためSensitive値を保持し得る。Observed Projection、Application／System Logで安全化する境界を正しく記載し、「Journalへ一切保存しない」とはしていない。
- TODOの初期用語 `DispatchMode`、`Journal Entry`、汎用 `Acknowledgement` は、現行 `ExecutionStrategy`、`JournalRecord`、`DeferredAcknowledgement`へ同期した。
- MVP CompleteはProduction Ready、Stable Release、Packagist公開、互換性保証を意味しない。
- Architecture DiagramはProduction Artifact load、Inline append→observe、Deferred acceptance/start/completion transaction分割、handler実行時非Transaction、専用heartbeat connection、lease expiry recovery、Retention fail-closed auditの現行Code Pathに合わせた。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
Result: OK (1 test, 34 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (586 tests, 1899 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 318 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1307 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] D017 Definition of Doneの10項目がすべて証拠付きでSatisfiedと確認される
- [x] Inline／Deferred／Retry／Recovery／Dead Letter／Sensitive／Compile／Retentionの到達点が文書化される
- [x] Architecture OverviewとInline／Deferred／障害時Sequence Diagramが現行実装と一致する
- [x] TODOの既実装・既決定の旧い未完了表示が証拠範囲で修正される
- [x] 未実装／MVP後の項目が未完了のまま保存される
- [x] DOCS／Guide／InternalsからMVP到達点とArchitectureへ到達できる
- [x] MVPの既知制約とPost-MVP境界が明記される
- [x] Sample E2Eと全品質Commandが成功する
- [x] `orchestration/STATE.md`がMVP Completeと次の任意Actionを示す

## Remaining Post-MVP Work

- Transactional Outbox persistence adapter and relay
- Canonical Journal observer replay CLI
- Authentication, authorization, Journal access control, and tenant isolation
- Deferred status/outcome HTTP endpoint and generated client SDK
- Canonical/transport encryption and key management
- OpenTelemetry, CloudWatch, remote logging, SQS, Kafka, SQLite, and MySQL adapters
- Stable structured-log schema/version, extended idempotency support, and lifecycle reconstruction reader
- Generator, Admin UI, Scheduled Operation Strategy, Coalesce, Saga, and advanced worker distribution
- Packagist publication, trademark clearance, Git tag, release notes, compatibility policy, and production certification

## Suggested Next Action

Orchestrator CodexがDoD証拠、TODO分類、Diagram、Documentation、最終CommandをReviewし、受入後にP6-015をCommitする。その後の作業は必須ではなく、Release準備または上記Post-MVP Backlogから新しいMilestoneを選択できる。
