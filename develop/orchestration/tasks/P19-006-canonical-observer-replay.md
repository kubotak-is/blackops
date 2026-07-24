# P19-006: Canonical Observer Replay

Status: In Progress

## Goal

PostgreSQL Canonical Journalを変更せず、Operation ID、Journal Record ID、または境界付き時刻範囲からCanonical Recordを有限Batchで選択し、現在のSensitive Projectionを再適用して明示したObserverへ同じCanonical Journal Record IDで再投影する。BlackOps CLIへDry-run、監査付きConfirm、永続Checkpoint、失敗後Resumeを追加し、at-least-onceのCrash境界とObserver側Record ID冪等責務を固定する。

## In Scope

- PostgreSQL Canonical JournalのReplay専用Reader／Selection
- Operation、Record、時刻範囲の相互排他的Selector
- 有限BatchとSelector別の安定Keyset順序
- 名前付きObserver Target Registryと明示Target選択
- 現在の`ObservedJournalRecordProjector`／`SensitiveProjectionFilter`による実行時再Projection
- Canonical Record IDを維持したObserver配送
- 永続Checkpoint、同一Checkpointの並行実行防止、失敗後Resume
- Replay Invocation Auditと安全なFailure Fingerprint
- `journal:observer:replay` BlackOps CLI、Lazy Bootstrap、Command Collision Guard
- JSONL Observer EnvelopeへのCanonical `recordId`追加
- Additive PostgreSQL Migration、Schema Helper、Package Export、Fresh Install同期
- Observer Replay Guide、BlackOps CLI Reference、Internal Architecture／Security／Upgrade同期

## Out of Scope

- Terminal Operation Replayまたは新しいOperation IDの発行
- Outbox Relay／Dead Letter Retryの変更
- Canonical JournalへのAppend／Update／Delete
- Public `CanonicalJournalReader`／`CanonicalJournalStore` Interfaceの拡張
- Observer共通のExactly Once保証
- JSONL File自体の既存行検索／重複抑止
- Community Board Product Journey
- External Collector、Remote Exporter、OpenTelemetry
- External Publication／Deploy

## Relevant Specifications

- `develop/spec/02-lifecycle-and-journal.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/24-lifecycle-event-data.md`
- `develop/spec/34-journal-observer.md`
- `develop/spec/35-postgresql-transport-schema.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/81-phase-19-delivery-plan.md`
- `develop/decisions/004-journal-schema-and-security.md`
- `develop/decisions/042-postgresql-transaction-boundaries.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`
- `develop/orchestration/reports/P19-005-relay-runtime-and-blackops-cli.md`

## Files Allowed to Change

- `src/Internal/Replay/**`
- `src/Internal/Journal/JournalObserver*.php`
- `src/Internal/Application/ApplicationJournal*.php`
- `src/Internal/Application/ApplicationObserverReplay*.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConfiguration*.php`
- `src/Internal/Console/JournalObserverReplayCommand.php`
- `src/Internal/Console/FrameworkCommandNames.php`
- `src/Transport/PostgreSql/PostgreSqlObserverReplay*.php`
- `src/Transport/PostgreSql/PostgreSqlJournalSchema.php`
- `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php`
- `src/Logging/JsonlJournalRecordEncoder.php`
- `migrations/postgresql/**`
- `tests/Internal/Replay/**`
- `tests/Internal/Journal/**`
- `tests/Internal/Application/**`
- `tests/Internal/Console/JournalObserverReplayCommandTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Transport/PostgreSql/PostgreSqlObserverReplay*.php`
- `tests/Transport/PostgreSql/PostgreSqlCanonicalJournalStoreTest.php`
- `tests/Logging/JsonlJournalObserverTest.php`
- `tests/Internal/Migration/**`
- `tests/Consumer/community-board-clean-install.sh`
- `tests/Consumer/framework-package-export.sh`
- `examples/quickstart/config/journal.php`
- `examples/community-board/config/journal.php`
- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/content-map.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/**`
- `develop/spec/**`
- `develop/TODO.md`
- `develop/orchestration/tasks/**`
- `develop/orchestration/reports/**`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Contract

### Selection and Ordering

- `journal:observer:replay`は次のSelectorを一つだけ受理する
  - `--operation-id=<uuid>`
  - `--record-id=<uuid>`
  - `--from=<RFC3339> --to=<RFC3339>`の組
- `from`／`to`はUTCへ正規化し、時刻範囲は`[from, to)`、`from < to`とする。片側だけの範囲、空範囲、Selector混在はQuery前に拒否する
- Operation選択は`sequence, record_id`、Record選択は単一Record、時刻選択は`occurred_at, record_id`の安定順序とする
- `--batch-size`は既定100、1以上1000以下とし、一回のInvocationは必ず一Batchで終了する
- Public `CanonicalJournalReader`はOperation単位の既存Contractを維持する。Record／時刻範囲とCheckpoint QueryはPostgreSQL Replay専用Adapterへ閉じる
- Corrupt／Unsupported Canonical Recordは安全に失敗し、Checkpointを飛び越えない

### Target, Projection, and Identity

- `--observer=<stable-name>`は一つ以上を受理し、重複を除いた安定順序へ正規化する
- TargetはApplication起動時に構成済みの名前付きObserverだけとし、不明／無効TargetはCanonical Query前に拒否する
- ReplayはCanonical `JournalRecord`を読み、各配送直前に現在の`ObservedJournalRecordProjector`と`SensitiveProjectionFilter`を適用する。保存済みProjectionを再利用しない
- `ObservedJournalRecord::recordId`、Operation ID、Sequence、Occurred AtをCanonical Recordから維持し、新しいOperation／Journal Recordを作らない
- JSONL Envelopeへ`recordId`を追加し、Targetが同じRecord IDで冪等取り込みできる情報を常に提供する
- Explicit ReplayではTargetの通常Delivery PolicyがBest Effortでも、一つの`observe()`または`flush()`失敗をInvocation Failureとして扱う
- 各Recordについて全Targetの`observe()`とFlush可能Targetの`flush()`が成功した後にだけ、そのRecordまでCheckpointを進める。途中失敗時は既にCheckpoint済みの前Recordを再読込しない

### Dry-run, Confirm, Checkpoint, and Resume

- `--dry-run`と`--confirm`はExactly Oneとし、どちらもない／両方指定を拒否する
- Dry-runはTargetとSelectorを検証し、有限Batchの候補数、先頭／末尾Record ID、has-moreだけを安全に表示する。Observer呼出、Checkpoint／Audit／Canonical StoreへのWriteは行わない
- Confirmによる新規Replayは`--checkpoint=<stable-id>`、`--actor=<non-empty>`、`--reason=<non-empty>`を必須とする。Checkpoint IDは1文字以上128文字以下の`^[a-z0-9]+(?:[._-][a-z0-9]+)*$`に限定する
- CheckpointはSelectorとTarget集合へ不変にBindingする。同じCheckpointを異なるSelector／Targetで再利用した場合は配送前に拒否する
- 同じCheckpointの同時実行はPostgreSQL Lock／Claimで直列化し、二つのRunnerが同時に同じCursorを進めない
- 各Recordの全Target配送／Flushが成功した時点でCursorを原子的に進める。一つのRecordまたはFlushが失敗した場合、そのRecordをResume可能な位置に残してBatchを停止する
- `--resume=<checkpoint>`は保存済みSelector／Targetを使用し、新しい`--actor`／`--reason`を必須とする。Selector／Observerの再指定と併用しない
- Checkpointは完了状態、Cursor、件数、更新時刻だけを保持し、Canonical Payload／Projection Data／Actor Contextを複製しない

### Audit and Crash Semantics

- Confirm／Resume Invocationごとに開始Auditを残し、成功／失敗／完了状態へTerminal化する。Process Crashで開始状態が残ること自体を監査証拠とする
- AuditはCheckpoint、Target名、Selector種別と安全なID／時刻境界、Operator Actor／Reason、件数、開始／終了時刻、先頭／末尾Record ID、安全なFailure Fingerprintだけを保持する
- Audit／Checkpoint／CLI／Log／ExceptionへCanonical Payload、Projection Data、Canonical Actor ID、Credential、SQL、Connection Parameter、Throwable Message／Traceを残さない
- Failure FingerprintはVersion付きDomain SeparatorとThrowable Classだけから作る
- Observer Acceptance／Flush成功後、Checkpoint確定前にCrashした場合は同じRecord IDが再配送され得る。Frameworkはat-least-onceを保証し、TargetはRecord IDで冪等取り込みする
- JSONLはappend-only ObserverでありGeneric Exactly Onceを保証しない。Record IDを出力するが、File内Duplicate抑止は本Taskで実装しない

### Migration and Canonical Immutability

- 既存Migrationを変更せず、新しいAdditive MigrationでReplay Checkpoint／Audit Tableと必要Index／Constraintを追加する
- Schema HelperとVersioned Migrationを一致させ、DownはP19-006 Table／Indexだけを除去する
- Replay SourceはCanonical Journal TableへSELECTだけを行い、Append／Update／Deleteを行わない
- Integration TestはReplay前後のCanonical Row Count、Record ID集合、`encoded_record` bytesが一致し、新しいLifecycle Recordが増えないことを証明する
- Retentionで対象Recordが消失／Tombstone化した場合は安全に停止し、暗黙にCheckpointを進めない

### BlackOps CLI and Compatibility

- Canonical Command Nameは`journal:observer:replay`とし、`FrameworkCommandNames`へ予約する
- Lazy `list`／`help`はDatabase接続やObserver File OpenなしでCommand Metadataを表示できる
- OutputはCheckpoint、selected／delivered／failed件数、先頭／末尾Record ID、has-more／completeだけとし、PayloadやFailure Detailを出さない
- Outbox Dead Letter Retry、Terminal Operation Replay、通常Journal Observationを同じCommand／Identityとして扱わない
- KeyなしRequest、Direct／Outbox Transport、Idempotency、Relay、Retention、Worker／Schedulerを回帰させない
- Public API SignatureへInternal／Doctrine／PostgreSQL型を追加しない
- Production Comment／DocBlockへSpec、Decision、Task、TODO管理番号を書かない

## Required Failure Matrix

| Case | Required Result |
| --- | --- |
| Operation selector | Sequence／Record ID順の有限Batch |
| Record selector | 同じCanonical Record IDを一件再投影 |
| Time selector | `[from,to)`をOccurred At／Record ID順で選択 |
| Mixed／unbounded／invalid selector | Query／Observer／Audit前に拒否 |
| Unknown／disabled observer | Query前に拒否 |
| Dry-run | Observer／Checkpoint／Audit／Canonical StoreへWriteなし |
| Confirm success | 同じRecord IDを現在のSensitive Projectionで配送しCheckpoint／Audit更新 |
| Observe failure | 失敗Recordを飛ばさず安全なAuditで停止 |
| Flush failure | Batch Cursorを完了扱いにせずResume可能 |
| Resume | 保存済みTarget／Selectorの次の未完了Recordから再開 |
| Checkpoint mismatch | 配送前に拒否 |
| Concurrent same checkpoint | 一Runnerだけ所有し、他は安全に拒否 |
| Crash after observer acceptance | 同じRecord IDで再配送可能 |
| Corrupt canonical row | Detailを漏らさず停止しCheckpoint非進行 |
| Canonical journal comparison | Row／ID／encoded bytes／Lifecycle件数が不変 |
| JSONL replay | Envelopeに同じ`recordId`を含む |

## Acceptance Criteria

- [ ] Operation／Record／時刻範囲Selector、有限Batch、安定Keyset順序が実PostgreSQLで検証される
- [ ] Dry-run、Confirm、Checkpoint Binding、Resume、同時実行拒否が検証される
- [ ] Observer Failure／Flush Failure／Crash WindowでRecordを飛ばさず同じRecord IDを維持する
- [ ] 現在のSensitive Filterが再適用され、Raw Sensitive Value／Canonical Actor IDが出力／Auditへ漏れない
- [ ] JSONL EnvelopeがCanonical `recordId`を持ち、通常配送とReplayで同じIdentityを出力する
- [ ] Canonical JournalのRow／Record ID／encoded bytes／LifecycleがReplay前後で不変である
- [ ] Additive Migration、Schema Helper parity、Down、Fresh Install、Package Exportが成功する
- [ ] BlackOps CLI list／help／options／safe output／collision／lazy bootstrapが検証される
- [ ] Public API Architecture、Full PHPUnit、Mago、Deptrac、Documentation Websiteが成功する
- [ ] Outbox Dead Letter Retry／Operation Replay／Community Board Product Journey差分がない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run build
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/community-board-clean-install.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
git status --short
```

## Expected Report

`develop/orchestration/reports/P19-006-canonical-observer-replay.md`へ次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Selection／Ordering Matrix
- Target／Projection／Identity Matrix
- Checkpoint／Resume／Concurrency Matrix
- Audit／Sensitive Evidence
- Canonical Immutability Evidence
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
