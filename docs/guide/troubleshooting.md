# TroubleshootingとFAQ

問題が起きたら、表示された症状だけで判断せず、原因候補を確認してから修正します。Operation IDは一つの処理を受付からTerminal Stateまで追跡する識別子です。出力やLogへCredentialを貼り付けないでください。

## Typed Self-handled Signature Error

**Symptom:** `build:compile`がTyped Self-handled `handle()`のSignature Errorを表示します。

**Likely Cause:** `handle()`がPublic／Non-staticでない、第一引数が具象`OperationValue`でない、第二引数が`ExecutionContext`でない、Return Typeが具象`Outcome`または`void`でない、あるいはNullable／Union／Optional Parameterを使っています。Typed標準形と`#[HandledBy]`を同時に指定した場合もAmbiguousとして失敗します。

**How to Verify:** Operation Classの`handle()`を確認し、次のCommandでもう一度Compileします。

```bash
php blackops build:compile -vvv
```

**Fix:** `public function handle(ConcreteValue $value): ConcreteOutcome`、またはContextが必要な場合だけ`public function handle(ConcreteValue $value, ExecutionContext $context): ConcreteOutcome`へ直します。Typed標準形から`#[Accepts]`、`#[Returns]`、`OperationHandler`を外します。

## 401にOperation IDがある場合とない場合

**Symptom:** Quickstartの`/welcome`が401を返し、Header欠落時はOperation IDがあるのに、不正な`X-Sample-Token`ではOperation IDがありません。

**Likely Cause:** Header欠落はAnonymous AuthenticationとしてOperationへ進み、`#[Authorize]`がLifecycle内でRejectします。不正HeaderはAuthentication MiddlewareがOperation受付前に停止します。

**How to Verify:** Local Example Tokenで3経路を比較します。CredentialをLogへ出力しないでください。

```bash
curl -i http://127.0.0.1:8080/welcome
curl -i -H 'X-Sample-Token: invalid' http://127.0.0.1:8080/welcome
curl -i -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

**Fix:** Localでは`.env`へ空でない`SAMPLE_API_TOKEN`を明示し、Headerと一致させます。未設定または空の設定は既知値へFallbackせずRuntime構成Errorになります。ProductionでSample Token方式を使い続けず、ApplicationのAuthenticator、Secret管理、Actor／Permission検索へ置き換えます。Header値をOperation Valueへ追加して解決しないでください。

## Operation Discovery／Manifest未登録

**Symptom:** `operation:list`へ新しいOperationが出ず、HTTP Routeも404になります。

**Likely Cause:** Sourceが`config/operations.php`のDiscovery Root外にある、ClassがComposer Autoload対象外である、`Operation`を実装していない、またはBuild後にSourceだけを変更しています。[Manifest](glossary.md#manifest)はBuild時に生成するRuntime検索Artifactです。

**How to Verify:** Discovery結果とConfigを確認します。

```bash
php blackops operation:list
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
php blackops build:compile
```

**Fix:** Deploy対象のSource、Dependency、Configを同じBuild工程へ固定し、その工程で3 Artifactを再生成します。古いArtifactを別ReleaseへCopyしません。

## Frontend Contract ArtifactがInvalid／Stale

**Symptom:** `frontend:generate`または`frontend:check`がContract Artifact不正として失敗します。

**Likely Cause:** `var/build/frontend.php`がない、Schemaが古い、Operation／HTTP／Frontend ManifestのBuild IDが違う、またはPHP Operation変更後に再Buildしていません。Frontend CommandはSource Reflectionや`build:compile`へFallbackしません。

**How to Verify:** Backend Artifactを同じApplication Build IDで作り直し、Commandを順番どおり実行します。CredentialやArtifact PayloadをErrorへ貼り付けないでください。

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

**Fix:** Source、Composer Dependency、`APP_BUILD_ID`、`config/app.php`を同じBuild工程へ固定し、その工程でArtifactを再生成します。別Releaseの`frontend.php`やGenerated TreeをCopyしません。

## Frontend Generated TreeがMissing／Drift

**Symptom:** `frontend:check`がExit 1で`missing`または`has drift`を表示します。

**Likely Cause:** `resources/js/blackops/`をまだ生成していない、生成後にPHP Contractが変わった、Generated Fileを手動編集／追加した、または別Build IDのTreeが残っています。

**How to Verify:** CheckはRead-onlyなので、実行前後のApplication Sourceを変更せず状態を分類できます。

```bash
php blackops frontend:check
echo $?
```

**Fix:** Application-owned `resources/js/application/`を編集し、Generated `resources/js/blackops/`は編集しません。現在のArtifactから`php blackops frontend:generate`を実行し、続けてCheckします。Non-marker DirectoryやSymlinkを強制削除せず、所有者を確認してから別Pathへ退避します。

## Generated TypeScriptがCompileできない

**Symptom:** `pnpm test`または`tsc`がGenerated OperationのImport、Value Input、Result Narrowingで型Errorを返します。

**Likely Cause:** Generate前、古いGenerated Tree、手書きのURL／Response型との競合、OperationValue変更にApplication-owned Consumerが追従していない、またはLockfileと異なるTypeScriptを使っています。

**How to Verify:** Frozen LockfileとCanonical Chainを使い、最初にDriftを除外します。

```bash
pnpm install --frozen-lockfile
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
pnpm test
```

**Fix:** Generated FileをCastや`any`で隠さず、PHP OperationValue／OutcomeまたはApplication-owned Consumer Sourceを修正して再生成します。Unsupported Collection／DTO／Enumを無理にScalarへ見せず、現行Supported Typeへ戻します。

## `.fetch()`がTransport Resultを返す

**Symptom:** `.fetch()`が`missing_fetch`、`invalid_base_url`、`network_error`、`aborted`、`unexpected_response`のTransport Resultを返します。

**Likely Cause:** Runtimeに`globalThis.fetch`がなくInjected Fetchもない、Base URLがHTTP／HTTPS Origin形式でない、Network／Abortが発生した、またはResponseのStatus／Content-Type／JSON ShapeがCompiled Contractと一致しません。

**How to Verify:** `result.kind === 'transport'`と安定した`result.error.code`だけを確認し、Raw Response Body、Token、Thrown Error MessageをLogへ出さないでください。SSR／Node／Testでは呼出単位の`fetch`と`baseUrl`を明示します。

```ts
const result = await ShowWelcome.fetch({}, { baseUrl, fetch: runtimeFetch });

if (!result.ok && result.kind === 'transport') {
  const safeCode: string = result.error.code;
  void safeCode;
}
```

**Fix:** RuntimeへWeb Fetch互換実装を注入し、安全なHTTP／HTTPS Base URLを使います。`unexpected_response`ではServerの公開Response ContractとGenerated ClientのBuild IDを揃えます。Raw BodyをResultへ追加するPatchやGlobal Mutable Credential Clientで回避しません。

## Deferred HTTPが202だがOutcomeがない

**Symptom:** HTTPは`202 Accepted`とOperation IDを返しますが、Outcomeが作られません。

**Likely Cause:** Workerを起動していない、Workerが別Database／Schemaを見ている、Retry Delay前である、またはProcess SupervisorがWorkerを停止しています。

**How to Verify:** 同じEnvironmentでWorkerを1 Loopだけ実行し、対象Operation IDのJournalに`operation.accepted`、`attempt.started`、`attempt.retry_scheduled`、Terminal Eventがあるか確認します。

```bash
curl -i -H 'X-Sample-Token: local-example' \
  http://127.0.0.1:8080/operations/<operation-id>
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

`<operation-id>`は202 Responseの値へ置き換えます。Worker未起動ならStatusは`accepted`のままです。`var/log/journal.jsonl`はHTTP ProcessのObserved Projectionなので、Worker完了を待つSourceには使いません。

**Fix:** HTTPとWorkerへ同じDatabase／Schema／Build Artifactを渡し、常駐WorkerをProcess ManagerまたはCompose Worker Profileで監督します。Retry Scheduledの場合はDelay後のAttemptを待ちます。

## Statusが404 `operation_unavailable`を返す

**Symptom:** 202で受け取ったOperation IDを`GET /operations/{operationId}`へ渡しても404になります。

**Likely Cause:** IDがUnknown、`OperationStatusAuthorizer`が未BindingまたはDeny、Current ActorとOrigin Actorが不一致、あるいはSubject自体がRetentionで完全削除されています。Frameworkは存在とDenyを区別させません。

**How to Verify:** 同じCredentialを使っているか、Application Service Providerが`OperationStatusAuthorizer::class`をApplication実装へBindingしているかを確認します。Operation IDやActor IDだけをLogへ追加しないでください。

**Fix:** ApplicationのStatus PolicyでCurrent Actor、Origin Actor、Tenant／Resource関係を評価します。Operation IDを知っていることだけをAllow条件にせず、Framework既定Denyを無効化する全許可Policyも置きません。QuickstartのSame-origin PolicyはLocal ExampleなのでProduction Policyへ置き換えます。

## Statusが410 `operation_expired`を返す

**Symptom:** 以前は取得できたOperationが410になります。

**Likely Cause:** AuthorizerはAllowしましたが、Terminal DetailまたはOutcomeがRetentionで削除され、Purge Auditから期限切れを証明できました。

**How to Verify:** `retention:plan`と承認済みRetention Policyを確認します。Unknown／Denyは404なので、410を認可判定の代わりに使いません。

**Fix:** 必要な保持期間をApplicationのPolicyとして見直します。削除済みCanonical PayloadをStatus ResponseやBackupから無断で復元せず、Legal Hold、Access Control、Purge承認を運用します。

## `.wait()`が`poll_timeout`を返す

**Symptom:** `.wait()`がTerminal StateではなくTransport Resultの`poll_timeout`を返します。

**Likely Cause:** Worker未起動、Retry Delay中、処理時間がDeadlineを超えた、またはStatus Request自体が期限内に完了しませんでした。

**How to Verify:** 同じOperation IDへ`.status()`を一回実行し、`accepted`／`running`／`retry_scheduled`と`retryAfterSeconds`を確認します。Timeout後にOperationが自動Cancelされたとは解釈しません。

**Fix:** Workerを監督し、業務SLOに合う正の`maxWaitMilliseconds`を呼出単位で指定します。無限待機や固定間隔の独自Pollingへ置き換えません。Timeout後もWorkerは処理を続けられるため、後から同じIDで`.status()`または新しい有限`.wait()`を実行できます。

## `.status()`／`.wait()`が`unexpected_response`を返す

**Symptom:** Serverへ到達できるのにGenerated Clientが`unexpected_response`で停止します。

**Likely Cause:** HTTP Status、JSON Media Type、Schema Version、Operation ID／Type、State別Field、Outcome Shape、`Retry-After`がCompiled Contractと一致しません。

**How to Verify:** Generated Treeを再生成し、ServerとClientが同じBuildから作られているか確認します。Raw Body、Credential、Thrown ErrorをResultやLogへ追加しないでください。

**Fix:** `build:compile -> frontend:generate -> frontend:check -> pnpm test`を同じReleaseで実行します。Malformed／5xxをClient側で自動Retryせず、Server ContractまたはDeploy不整合を修正します。

## Operation ID付き500を調べる

**Symptom:** Responseが`{"status":"error","code":"internal_error","operationId":"019..."}`を返します。

**How to Verify:** IDを変更せず、Human表示、次にJSON表示で確認します。

```bash
php blackops operation:inspect 019...
php blackops operation:inspect 019... --json
```

`received -> attempt.started -> attempt.failed -> operation.failed`の順と、Application／Framework JSONL Logの同じOperation IDを確認します。HTTPやCLIにException Messageがないのは意図したSafe Surfaceです。Canonical DatabaseのRaw RecordをSupport Ticketへ貼り付けないでください。

IDのない500はOperation成立前のBootstrap／Middleware／Protocol境界の失敗です。`operation:inspect`では追跡できないため、Credentialを除いたFramework Error Log、Config Validation、Build Artifact、Database Connectionを確認します。

InspectのExit CodeはInvalid ID=`2`、Unavailable=`3`、Storage／Decode／Integrity=`4`です。`--json`のErrorは`{"schemaVersion":1,"status":"error","code":"..."}`をstderrへ出します。

## Local Viewerが起動／表示できない

**Symptom:** `viewer.disabled`、`viewer.invalid_configuration`、`viewer.runtime_unavailable`、`viewer.bind_failed`、またはBrowserで404が返ります。

**How to Verify:** `config/diagnostics.php`の`enabled`、`127.0.0.1`、Port競合、CLI RuntimeのPCNTLを確認します。QuickstartはLocalだけEnabledです。

**Fix:** Viewerを`php blackops operation:viewer`で明示起動し、その起動で一度だけ出るBootstrap URLへ同じLocal Runtimeからアクセスします。Tokenがない、古いTokenを使う、Session Cookieを捨てる、Host Headerが異なる場合の404はFail-closed動作です。Non-loopback Bindへ変更せず、別のLocal Portへ変える場合はConfigと接続先を同期します。POSTは405で、GET／HEADだけが正常です。

## Migration未適用／PostgreSQL接続失敗

**Symptom:** HTTP、Worker、Outcome、Retention CommandがTable不在またはPostgreSQL接続Errorで失敗します。

**Likely Cause:** Migrationを明示適用していない、`config/database.php`のHost／Port／Database／UserがProcessごとに異なる、またはPostgreSQLが起動していません。

**How to Verify:** 接続先をSecretなしで確認し、Read-only Statusを実行します。

```bash
php blackops database:status --no-interaction
docker compose ps postgres
```

**Fix:** PostgreSQLを起動し、正しいCredentialをEnvironmentから渡してMigrationを適用します。

```bash
php blackops database:migrate --dry-run --no-interaction
php blackops database:migrate --no-interaction
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

**Likely Cause:** `OutcomeReader`は正常完了したOutcomeだけを返す低Level Contractです。Status Queryを使わず`null`だけで判定しています。

**How to Verify:** Public `OperationStatusQuery`、`GET /operations/{operationId}`、またはGenerated `.status()`で現在Stateを確認します。

**Fix:** Public Status Resultを次のように分類します。PHP AdapterでOutcomeだけが必要な場合に限り`OutcomeReader`を使います。

| 判定 | Applicationが返す状態 |
| --- | --- |
| Operationが存在し、非Terminal | Pending |
| CompletedかつOutcomeあり | Completed |
| Rejected／Failed／Dead Letter | Terminal without outcome |
| UnknownまたはDeny | 404 Unavailable |
| Allow済みで期限切れを証明 | 410 Expired |

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
