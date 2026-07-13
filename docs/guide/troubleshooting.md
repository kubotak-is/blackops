# TroubleshootingとFAQ

問題が起きたら、表示された症状だけで判断せず、原因候補を確認してから修正します。Operation IDは一つの処理を受付からTerminal Stateまで追跡する識別子です。出力やLogへCredentialを貼り付けないでください。

## Typed Self-handled Signature Error

**Symptom:** `blackops:build:compile`がTyped Self-handled `handle()`のSignature Errorを表示します。

**Likely Cause:** `handle()`がPublic／Non-staticでない、第一引数が具象`OperationValue`でない、第二引数が`ExecutionContext`でない、Return Typeが具象`Outcome`または`void`でない、あるいはNullable／Union／Optional Parameterを使っています。Typed標準形と`#[HandledBy]`を同時に指定した場合もAmbiguousとして失敗します。

**How to Verify:** Operation Classの`handle()`を確認し、次のCommandでもう一度Compileします。

```bash
php blackops blackops:build:compile -vvv
```

**Fix:** `public function handle(ConcreteValue $value): ConcreteOutcome`、またはContextが必要な場合だけ`public function handle(ConcreteValue $value, ExecutionContext $context): ConcreteOutcome`へ直します。Typed標準形から`#[Accepts]`、`#[Returns]`、`OperationHandler`を外します。

## Operation Discovery／Manifest未登録

**Symptom:** `blackops:operation:list`へ新しいOperationが出ず、HTTP Routeも404になります。

**Likely Cause:** Sourceが`config/operations.php`のDiscovery Root外にある、ClassがComposer Autoload対象外である、`Operation`を実装していない、またはBuild後にSourceだけを変更しています。[Manifest](glossary.md#manifest)はBuild時に生成するRuntime検索Artifactです。

**How to Verify:** Discovery結果とConfigを確認します。

```bash
php blackops blackops:operation:list
php -r '$config = require "config/operations.php"; var_export($config["discovery"] ?? null); echo PHP_EOL;'
```

**Fix:** OperationをDiscovery Root配下へ置き、Composer Autoloadを更新してから再Buildします。通常のApplication Featureを`OperationProvider`へ手動列挙しないでください。PackageやRoot外SourceだけProviderで登録します。

## Build Artifact不在／Build ID不一致

**Symptom:** HTTP、Worker、またはConsole CommandがArtifact不在、Format不正、Build ID不一致で起動しません。

**Likely Cause:** `var/build/`を生成していない、別ReleaseのArtifactをDeployした、または`APP_BUILD_ID`を変えた後に再Buildしていません。Production RuntimeはSource DiscoveryへFallbackしません。

**How to Verify:** Configured PathとFileを確認し、現在のSourceでCompileできるか試します。

```bash
php -r '$config = require "config/app.php"; var_export($config["build"] ?? null); echo PHP_EOL;'
ls -l var/build/operations.php var/build/http.php var/build/container.php
php blackops blackops:build:compile
```

**Fix:** Deploy対象のSource、Dependency、Configを同じBuild工程へ固定し、その工程で3 Artifactを再生成します。古いArtifactを別ReleaseへCopyしません。

## Deferred HTTPが202だがOutcomeがない

**Symptom:** HTTPは`202 Accepted`とOperation IDを返しますが、Outcomeが作られません。

**Likely Cause:** Workerを起動していない、Workerが別Database／Schemaを見ている、Retry Delay前である、またはProcess SupervisorがWorkerを停止しています。

**How to Verify:** 同じEnvironmentでWorkerを1 Loopだけ実行し、対象Operation IDのJournalに`operation.accepted`、`attempt.started`、`attempt.retry_scheduled`、Terminal Eventがあるか確認します。

```bash
php blackops blackops:worker:run --iterations=1 --idle-sleep-milliseconds=1
grep '<operation-id>' var/log/journal.jsonl
```

`<operation-id>`は202 Responseの値へ置き換えます。

**Fix:** HTTPとWorkerへ同じDatabase／Schema／Build Artifactを渡し、常駐WorkerをProcess ManagerまたはCompose Worker Profileで監督します。Retry Scheduledの場合はDelay後のAttemptを待ちます。

## Migration未適用／PostgreSQL接続失敗

**Symptom:** HTTP、Worker、Outcome、Retention CommandがTable不在またはPostgreSQL接続Errorで失敗します。

**Likely Cause:** Migrationを明示適用していない、`config/database.php`のHost／Port／Database／UserがProcessごとに異なる、またはPostgreSQLが起動していません。

**How to Verify:** 接続先をSecretなしで確認し、Read-only Statusを実行します。

```bash
php blackops blackops:database:status --no-interaction
docker compose ps postgres
```

**Fix:** PostgreSQLを起動し、正しいCredentialをEnvironmentから渡してMigrationを適用します。

```bash
php blackops blackops:database:migrate --dry-run --no-interaction
php blackops blackops:database:migrate --no-interaction
```

HTTP／Worker起動時の暗黙Migrationに頼りません。

## journal.jsonlへ出力されない

**Symptom:** Operationは完了しますが、`var/log/journal.jsonl`が存在しない、またはRecordが増えません。

**Likely Cause:** `config/journal.php`で`enabled`がfalse、Pathが相対Path、Parent Directoryがない／書込不能、または`best_effort` Observerの失敗を見落としています。

**How to Verify:** ConfigとDirectory権限を確認します。

```bash
php -r '$config = require "config/journal.php"; var_export($config["jsonl"] ?? null); echo PHP_EOL;'
test -d var/log && test -w var/log && printf 'journal directory is writable\n'
```

**Fix:** `enabled=true`、既存の書込可能な絶対Path、`best_effort`または`required`を設定します。FrameworkはDirectoryを作らないため、Deploy／Setup工程でParent Directoryを準備します。

## Outcome Status

### OutcomeがPending／Not Found／Expiredか判別できない

**Symptom:** `OutcomeReader::find()`が`null`を返し、処理中、未知のOperation ID、失敗、保持期限切れを区別できません。

**Likely Cause:** `OutcomeReader`は正常完了したOutcomeだけを返すContractです。現行RuntimeはStatus／Outcome HTTP endpointを提供しません。

**How to Verify:** Applicationが所有するStatus ViewでOperation IDの存在、現在State、Terminal Event、Outcome保持期限を確認します。Journalでは`operation.completed`、`operation.rejected`、`operation.failed`、`operation.dead_lettered`を調べます。

**Fix:** ApplicationにStatus ViewとController／CLI Adapterを実装し、次のように分類します。

| 判定 | Applicationが返す状態 |
| --- | --- |
| Operationが存在し、非Terminal | Pending |
| CompletedかつOutcomeあり | Completed |
| Rejected／Failed／Dead Letter | Terminal without outcome |
| Operationを確認できない | Not Found |
| Completed記録がありOutcome保持期限を超過 | Expired |

Persistence PayloadやFramework Table Schemaを利用者向けResponseへ直接公開しません。

## Sensitive値がJournalで見えない

**Symptom:** `#[Sensitive]`を付けた値が`[masked]`、除外、Hashとして表示され、入力値を確認できません。

**Likely Cause:** Sensitive Projectionが意図どおりObserved Journalへの出力を制限しています。これは不具合ではありません。

**How to Verify:** `OperationValue`のPropertyに付けた`#[Sensitive]`と`SensitiveMode`を確認します。Raw値をLogへ追加して検証しないでください。

**Fix:** Debuggingには非Sensitiveな相関ID、Category、安定したError Codeを使います。Raw Secretが業務処理に不要なら保存しません。秘密値の復元が必要な業務要件は、Applicationの暗号化Store、Key管理、Access Control、監査を別途設計します。

## FAQ: 202は完了を意味しますか

いいえ。`202 Accepted`はDeferred OperationをDurableに受け付けたことだけを意味します。WorkerのAttempt、Retry、Terminal State、Outcomeを同じOperation IDで追跡してください。

## FAQ: 失敗をすべてRejectedへ変換できますか

変換しません。予期された業務拒否だけ`OperationRejectedException`を使います。一時障害は`RetryableException`、BugやInfrastructure Failureは通常のThrowableとしてSupervision／Failure Policyへ渡します。
