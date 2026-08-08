# Troubleshooting

問題が起きたら、表示された症状だけで判断せず、原因候補を確認してから修正します。Operation IDは一つの処理を受付からTerminal Stateまで追跡する識別子です。出力やLogへCredentialを貼り付けないでください。

Taskの実行順を先に確認する場合は、[Testing](testing.md)、[Deployment](deployment.md)、[ConsoleCommand](console-command.md)、[Outbox](outbox.md)、[BlackOps CLI](project-cli.md)へ戻ります。ここではFailureの分類、確認コマンド、安全な出力境界だけを扱います。

## `database:seed`がArtifact Errorになる

**症状:** `Database seeding artifacts are unavailable.`または`Database seeding runtime could not be resolved.`が表示されます。

**考えられる原因:** Root Seederを追加・変更した後に`build:compile`していない、Application Build IDが変わった、またはCompiled Containerが欠落しています。

**修正方法:** Migrationを適用し、現在のSourceと設定でBuildしてからSeedを再実行します。

```bash
php blackops database:migrate
php blackops build:compile
php blackops database:seed
```

`Database seeding failed.`の場合はApplication Seederが失敗しています。CommandはSQL、投入値、Credential、Throwableを意図的に表示しません。Application側の安全なLogとDatabase状態を確認し、Transaction、Conflict、再実行方針を修正してください。

## Typed Self-handled Signature Error

**症状:** `build:compile`がTyped Self-handled `handle()`のSignature Errorを表示します。

**考えられる原因:** `handle()`がPublic／Non-staticでない、第一引数が具象`OperationValue`でない、第二引数が`ExecutionContext`でない、Return Typeが具象`Outcome`または`void`でない、あるいはNullable／Union／Optional Parameterを使っています。Typed標準形と`#[HandledBy]`を同時に指定した場合もAmbiguousとして失敗します。

**確認方法:** Operation Classの`handle()`を確認し、次のコマンドでもう一度Compileします。

```bash
php blackops build:compile -vvv
```

**修正方法:** `public function handle(ConcreteValue $value): ConcreteOutcome`、またはContextが必要な場合だけ`public function handle(ConcreteValue $value, ExecutionContext $context): ConcreteOutcome`へ直します。Typed標準形から`#[Accepts]`、`#[Returns]`、`OperationHandler`を外します。

## 401にOperation IDがある場合とない場合

**症状:** Quickstartの`/welcome`が401を返し、Header欠落時はOperation IDがあるのに、不正な`X-Sample-Token`ではOperation IDがありません。

**考えられる原因:** Header欠落はAnonymous AuthenticationとしてOperationへ進み、`#[Authorize]`がLifecycle内でRejectします。不正HeaderはAuthentication MiddlewareがOperation受付前に停止します。

**確認方法:** Local Example Tokenで3経路を比較します。CredentialをLogへ出力しないでください。

```bash
curl -i http://127.0.0.1:8080/welcome
curl -i -H 'X-Sample-Token: invalid' http://127.0.0.1:8080/welcome
curl -i -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
```

**修正方法:** Localでは`.env`へ空でない`SAMPLE_API_TOKEN`を明示し、Headerと一致させます。未設定または空の設定は既知値へFallbackせずRuntime構成Errorになります。ProductionでSample Token方式を使い続けず、ApplicationのAuthenticator、Secret管理、Actor／Permission検索へ置き換えます。Header値をOperation Valueへ追加して解決しないでください。

## Operation Discovery／Manifest未登録

**症状:** `operation:list`へ新しいOperationが出ず、HTTP Routeも404になります。

**考えられる原因:** Sourceが`config/operations.php`のDiscovery Root外にある、ClassがComposer Autoload対象外である、`Operation`を実装していない、またはBuild後にSourceだけを変更しています。[Manifest](glossary.md#manifest)はBuild時に生成するRuntime検索Artifactです。

**確認方法:** Discovery結果とConfigを確認します。

```bash
php blackops operation:list
php -r '$config = require "config/operations.php"; var_export($config["discovery"] ?? null); echo PHP_EOL;'
```

**修正方法:** OperationをDiscovery Root配下へ置き、Composer Autoloadを更新してから再Buildします。通常のApplication Featureを`OperationProvider`へ手動列挙しないでください。PackageやRoot外SourceだけProviderで登録します。

## Build Artifact不在／Build ID不一致

**症状:** HTTP、Worker、またはConsole CommandがArtifact不在、Format不正、Build ID不一致で起動しません。

**考えられる原因:** `var/build/`を生成していない、別ReleaseのArtifactをDeployした、または`APP_BUILD_ID`を変えた後に再Buildしていません。Production RuntimeはSource DiscoveryへFallbackしません。

**確認方法:** Configured PathとFileを確認し、現在のSourceでCompileできるか試します。

```bash
php -r '$config = require "config/app.php"; var_export($config["build"] ?? null); echo PHP_EOL;'
ls -l var/build/operations.php var/build/http.php var/build/container.php
php blackops build:compile
```

**修正方法:** Deploy対象のSource、Dependency、Configを同じBuild工程へ固定し、その工程で3 Artifactを再生成します。古いArtifactを別ReleaseへCopyしません。

## Frontend Contract ArtifactがInvalid／Stale

**症状:** `frontend:generate`または`frontend:check`がContract Artifact不正として失敗します。

**考えられる原因:** `var/build/frontend.php`がない、Schemaが古い、Operation／HTTP／Frontend ManifestのBuild IDが違う、またはPHP Operation変更後に再Buildしていません。Frontend CommandはSource Reflectionや`build:compile`へFallbackしません。

**確認方法:** Backend Artifactを同じApplication Build IDで作り直し、Commandを順番どおり実行します。CredentialやArtifact PayloadをErrorへ貼り付けないでください。

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

**修正方法:** Source、Composer Dependency、`APP_BUILD_ID`、`config/app.php`を同じBuild工程へ固定し、その工程でArtifactを再生成します。別Releaseの`frontend.php`やGenerated TreeをCopyしません。

## Frontend Generated TreeがMissing／Drift

**症状:** `frontend:check`がExit 1で`missing`または`has drift`を表示します。

**考えられる原因:** `resources/js/blackops/`をまだ生成していない、生成後にPHP Contractが変わった、Generated Fileを手動編集／追加した、または別Build IDのTreeが残っています。

**確認方法:** CheckはRead-onlyなので、実行前後のApplication Sourceを変更せず状態を分類できます。

```bash
php blackops frontend:check
echo $?
```

**修正方法:** Application-owned `resources/js/application/`を編集し、Generated `resources/js/blackops/`は編集しません。現在のArtifactから`php blackops frontend:generate`を実行し、続けてCheckします。Non-marker DirectoryやSymlinkを強制削除せず、所有者を確認してから別Pathへ退避します。

## Generated TypeScriptがCompileできない

**症状:** `pnpm test`または`tsc`がGenerated OperationのImport、Value Input、Result Narrowingで型Errorを返します。

**考えられる原因:** Generate前、古いGenerated Tree、手書きのURL／Response型との競合、OperationValue変更にApplication-owned Consumerが追従していない、またはLockfileと異なるTypeScriptを使っています。

**確認方法:** Frozen LockfileとCanonical Chainを使い、最初にDriftを除外します。

```bash
pnpm install --frozen-lockfile
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
pnpm test
```

**修正方法:** Generated FileをCastや`any`で隠さず、PHP OperationValue／OutcomeまたはApplication-owned Consumer Sourceを修正して再生成します。Unsupported Collection／DTO／Enumを無理にScalarへ見せず、現行Supported Typeへ戻します。

## `.fetch()`がTransport Resultを返す

**症状:** `.fetch()`が`missing_fetch`、`invalid_base_url`、`network_error`、`aborted`、`unexpected_response`のTransport Resultを返します。

**考えられる原因:** Runtimeに`globalThis.fetch`がなくInjected Fetchもない、Base URLがHTTP／HTTPS Origin形式でない、Network／Abortが発生した、またはResponseのStatus／Content-Type／JSON ShapeがCompiled Contractと一致しません。

**確認方法:** `result.kind === 'transport'`と安定した`result.error.code`だけを確認し、Raw Response Body、Token、Thrown Error MessageをLogへ出さないでください。SSR／Node／Testでは呼出単位の`fetch`と`baseUrl`を明示します。

```ts
const result = await ShowWelcome.fetch({}, { baseUrl, fetch: runtimeFetch });

if (!result.ok && result.kind === 'transport') {
  const safeCode: string = result.error.code;
  void safeCode;
}
```

**修正方法:** RuntimeへWeb Fetch互換実装を注入し、安全なHTTP／HTTPS Base URLを使います。`unexpected_response`ではServerの公開Response ContractとGenerated ClientのBuild IDを揃えます。Raw BodyをResultへ追加するPatchやGlobal Mutable Credential Clientで回避しません。

## Deferred HTTPが202だがOutcomeがない

**症状:** HTTPは`202 Accepted`とOperation IDを返しますが、Outcomeが作られません。

**考えられる原因:** Workerを起動していない、Workerが別Database／Schemaを見ている、Retry Delay前である、またはProcess SupervisorがWorkerを停止しています。

**確認方法:** 同じEnvironmentでWorkerを1 Loopだけ実行し、対象Operation IDのJournalに`operation.accepted`、`attempt.started`、`attempt.retry_scheduled`、Terminal Eventがあるか確認します。

```bash
curl -i -H 'X-Sample-Token: local-example' \
  http://127.0.0.1:8080/operations/<operation-id>
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

`<operation-id>`は202 Responseの値へ置き換えます。Worker未起動ならStatusは`accepted`のままです。`var/log/journal.jsonl`はHTTP ProcessのObserved Projectionなので、Worker完了を待つSourceには使いません。

**修正方法:** HTTPとWorkerへ同じDatabase／Schema／Build Artifactを渡し、常駐WorkerをProcess ManagerまたはCompose Worker Profileで監督します。Retry Scheduledの場合はDelay後のAttemptを待ちます。

## Statusが404 `operation_unavailable`を返す

**症状:** 202で受け取ったOperation IDを`GET /operations/{operationId}`へ渡しても404になります。

**考えられる原因:** IDがUnknown、`OperationStatusAuthorizer`が未BindingまたはDeny、Current ActorとOrigin Actorが不一致、あるいはSubject自体がRetentionで完全削除されています。Frameworkは存在とDenyを区別させません。

**確認方法:** 同じCredentialを使っているか、Application Service Providerが`OperationStatusAuthorizer::class`をApplication実装へBindingしているかを確認します。Operation IDやActor IDだけをLogへ追加しないでください。

**修正方法:** ApplicationのStatus PolicyでCurrent Actor、Origin Actor、Tenant／Resource関係を評価します。Operation IDを知っていることだけをAllow条件にせず、Framework既定Denyを無効化する全許可Policyも置きません。QuickstartのSame-origin PolicyはLocal ExampleなのでProduction Policyへ置き換えます。

## Statusが410 `operation_expired`を返す

**症状:** 以前は取得できたOperationが410になります。

**考えられる原因:** AuthorizerはAllowしましたが、Terminal DetailまたはOutcomeがRetentionで削除され、Purge Auditから期限切れを証明できました。

**確認方法:** `retention:plan`と承認済みRetention Policyを確認します。Unknown／Denyは404なので、410を認可判定の代わりに使いません。

**修正方法:** 必要な保持期間をApplicationのPolicyとして見直します。削除済みCanonical PayloadをStatus ResponseやBackupから無断で復元せず、Legal Hold、Access Control、Purge承認を運用します。

## `.wait()`が`poll_timeout`を返す

**症状:** `.wait()`がTerminal StateではなくTransport Resultの`poll_timeout`を返します。

**考えられる原因:** Worker未起動、Retry Delay中、処理時間がDeadlineを超えた、またはStatus Request自体が期限内に完了しませんでした。

**確認方法:** 同じOperation IDへ`.status()`を一回実行し、`accepted`／`running`／`retry_scheduled`と`retryAfterSeconds`を確認します。Timeout後にOperationが自動Cancelされたとは解釈しません。

**修正方法:** Workerを監督し、業務SLOに合う正の`maxWaitMilliseconds`を呼出単位で指定します。無限待機や固定間隔の独自Pollingへ置き換えません。Timeout後もWorkerは処理を続けられるため、後から同じIDで`.status()`または新しい有限`.wait()`を実行できます。

## `.status()`／`.wait()`が`unexpected_response`を返す

**症状:** Serverへ到達できるのにGenerated Clientが`unexpected_response`で停止します。

**考えられる原因:** HTTP Status、JSON Media Type、Schema Version、Operation ID／Type、State別Field、Outcome Shape、`Retry-After`がCompiled Contractと一致しません。

**確認方法:** Generated Treeを再生成し、ServerとClientが同じBuildから作られているか確認します。Raw Body、Credential、Thrown ErrorをResultやLogへ追加しないでください。

**修正方法:** `build:compile -> frontend:generate -> frontend:check -> pnpm test`を同じReleaseで実行します。Malformed／5xxをClient側で自動Retryせず、Server ContractまたはDeploy不整合を修正します。

## Operation ID付き500を調べる

**症状:** Responseが`{"status":"error","code":"internal_error","operationId":"019..."}`を返します。

**確認方法:** IDを変更せず、Human表示、次にJSON表示で確認します。

```bash
php blackops operation:inspect 019...
php blackops operation:inspect 019... --json
```

`received -> attempt.started -> attempt.failed -> operation.failed`の順と、Application／Framework JSONL Logの同じOperation IDを確認します。HTTPやCLIにException Messageがないのは意図したSafe Surfaceです。Canonical DatabaseのRaw RecordをSupport Ticketへ貼り付けないでください。

## Scheduled Operationが`configuration_error`で停止する

**症状:** `operation:schedule:run`がExit `2`で停止し、Configuration Errorを返します。

**考えられる原因:** `database:migrate`または`build:compile`を先に実行していない、Schedule名／Cron／Timezoneが不正、Required Constructor引数を持つValue、または`#[Authorize]`へApplication-owned `ScheduledActorProvider`をBindingしていません。

**確認方法:** SourceとArtifactを更新し、SafeなJSONだけを確認します。

```bash
php blackops database:migrate
php blackops build:compile
php blackops operation:schedule:run --json
```

Credential、Value Payload、Actor情報はErrorへ追加しません。Providerが`null`を返す場合は匿名へFallbackせず認可拒否になります。[Scheduled Operation](scheduled-operation.md)のAuthoringとProvider手順を確認してください。

## Scheduled Occurrenceが`accepted`／`claimed`のまま

**症状:** `operation:schedule:run --json`は`accepted`を返したのに、OccurrenceまたはOperationが完了しません。

**考えられる原因:** Deferred Operationの`worker:run`が起動していない、Workerが別Database／Schemaを見ている、またはLease／Heartbeatの期限切れからRecovery待ちです。

**確認方法:** Operation IDを変更せず、Occurrenceの安全な列とJournalの同じOperation IDを照合します。

```bash
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
php blackops operation:inspect <operation-id> --json
```

`operation:schedule:run --json`は件数だけを返し、Operation IDを出力しません。上のRead-only Occurrence Queryで`operation_id`を得て、`<operation-id>`へ置き換えます。`accepted`はDurable受理であり完了ではありません。Lease Recovery後も同じOperation IDを使い、外部副作用はExactly Onceと解釈しません。

## Scheduled Occurrenceが`skipped_misfire`／`skipped_overlap`になる

**症状:** JSONの`skipped_misfire`または`skipped_overlap`が増え、Operation IDがありません。

**考えられる原因:** Cursorより後から現在のUTC Calendar Minuteまでに一致するSlotが複数あり、最新Slot以外が`skipped_misfire`になった、または同じScheduleの前回Occurrenceが実行中で最新Slotが`skipped_overlap`になった状態です。どちらもRunnable Operationを作らない安全なSkipです。

**確認方法:** `schedule_name`、UTCの`scheduled_at`、`evaluated_at`、`state`、`category`だけを確認します。

Project Rootで、Framework SchemaのRead-only Queryとして実行します。SkeletonのDocker環境ではPostgreSQLへ次のように接続してからSQLを貼り付けます。

```bash
docker compose exec -T postgres psql -U blackops -d blackops
```

別環境ではApplicationのFramework Connection／Schemaへ接続するRead-only PostgreSQL Clientを使い、`blackops`はApplication Configurationに合わせて置き換えます。CredentialをCommand例へ直書きしません。正本のQueryは[OccurrenceとJournalを安全に確認する](scheduled-operation.md#occurrenceとjournalを安全に確認する)にもあります。

```sql
SELECT schedule_name, scheduled_at, evaluated_at, state, category
FROM blackops.schedule_occurrences
WHERE schedule_name = 'reports.daily'
ORDER BY scheduled_at DESC
LIMIT 20;
```

Cron／Timezoneと外部Supervisorの重複起動を見直します。SkipへOperation IDを補って再実行したり、Occurrenceを直接更新したりしません。

IDのない500はOperation成立前のBootstrap／Middleware／Protocol境界の失敗です。`operation:inspect`では追跡できないため、Credentialを除いたFramework Error Log、Config Validation、Build Artifact、Database Connectionを確認します。

InspectのExit CodeはInvalid ID=`2`、Unavailable=`3`、Storage／Decode／Integrity=`4`です。`--json`のErrorは`{"schemaVersion":1,"status":"error","code":"..."}`をstderrへ出します。

## Local Viewerが起動／表示できない

**症状:** `viewer.disabled`、`viewer.invalid_configuration`、`viewer.runtime_unavailable`、`viewer.bind_failed`、またはBrowserで404が返ります。

**確認方法:** `config/diagnostics.php`の`enabled`、`127.0.0.1`、Port競合、CLI RuntimeのPCNTLを確認します。QuickstartはLocalだけEnabledです。

**修正方法:** Viewerを`php blackops operation:viewer`で明示起動し、その起動で一度だけ出るBootstrap URLへ同じLocal Runtimeからアクセスします。Tokenがない、古いTokenを使う、Session Cookieを捨てる、Host Headerが異なる場合の404はFail-closed動作です。Non-loopback Bindへ変更せず、別のLocal Portへ変える場合はConfigと接続先を同期します。POSTは405で、GET／HEADだけが正常です。

## Migration未適用／PostgreSQL接続失敗

**症状:** HTTP、Worker、Outcome、Retention CommandがTable不在またはPostgreSQL接続Errorで失敗します。

**考えられる原因:** Migrationを明示適用していない、`config/database.php`のHost／Port／Database／UserがProcessごとに異なる、またはPostgreSQLが起動していません。

**確認方法:** 接続先をSecretなしで確認し、Read-only Statusを実行します。

```bash
php blackops database:status --no-interaction
docker compose ps postgres
```

**修正方法:** PostgreSQLを起動し、正しいCredentialをEnvironmentから渡してMigrationを適用します。

```bash
php blackops database:migrate --dry-run
php blackops database:migrate
```

HTTP／Worker起動時の暗黙Migrationに頼りません。

## `StorageKeyProvider`が未登録

**症状:** HTTP、Worker、Console、Scheduled、Outbox、Status／Data Query、またはRotationのRuntime compositionがStorage Protectionを解決できず、安全なConfiguration／Storage Protection Errorで停止します。`build:compile`は`StorageKeyProvider`の必須Runtime検査を行わないため、Providerが未登録でも成功する場合があります。

**考えられる原因:** Application Service Providerで`StorageKeyProvider::class`のBindingがない、`config/app.php`の`services`へProviderを登録していない、またはProvider ConstructorがContainerで解決できない状態です。

**確認方法:** [2. Key ProviderをApplicationへ登録する](tenant-protection.md#2-key-providerをapplicationへ登録する)と[Application Command](configuration.md#application-command)を照合します。Storage Protectionを解決するRuntime composition（HTTP、Worker、Console等）を実行し、該当SurfaceのSafe Error／Responseだけを確認します。`build:compile`の成功だけではProvider登録を証明しません。Key Material、Credential、Constructor引数、Throwableを出力せず、`build:compile`には`--json` Optionがないことにも注意してください。

**修正方法:** Application-owned `StorageKeyProvider`、`OperationDataReadAuthorizer`、`OperationStatusAuthorizer`、必要なTenant Providerを`ServiceRegistry::autowire()`で登録し、`config/app.php`へService Providerを追加してからBuildし、HTTP／Worker／Console等のRuntime compositionを再実行します。該当SurfaceのSource／Testで定義されたSafe Error／Responseを期待し、Keyを`set()`、Config、Manifest、Artifact、Logへ置かないでください。

## Unknown Key／Tag Tamper

**症状:** 認可済みのJournal／Outcome Read、Worker、または`storage:protection:rotate --confirm`がEnvelope Integrityを検証できず、安全な固定Failureへ縮約します。Rotation ConfirmのStorage／Protection FailureはExit `1`、Plan／RotateのInput／Configuration FailureはExit `2`です。Journal／Outcome Queryは`operation_journal.protection_failed`または`operation_outcome.protection_failed`等のPublic Safe Codeを返します。`storage:protection:plan`はHeader／Clear MetadataのRead-only集計であり、Unknown Key／Tag Tamperを検出しません。

**考えられる原因:** BOPD HeaderのKey IDをProviderが解決できない、旧KeyのRead期間が終了している、AADのPurpose／Row／Operation／Tenant Scopeが異なる、またはCiphertext／Tagが改変されています。Malformed Headerも同じRuntime Protection Failureへ縮約されます。

**確認方法:** 認可済みApplicationの`OperationJournalQuery::records()`または`OperationOutcomeQuery::find()`、Worker処理、Rotation Confirmを実際に実行し、該当Surfaceの固定Safe Code／Exitだけを確認します。Read-onlyの[5. DatabaseにRaw Valueがないことを確認する](tenant-protection.md#5-databaseにraw-valueがないことを確認する)のBOPD Prefix countと`operation:inspect`はClear lifecycle列のDiagnosticsであり、Envelope Integrity検査やTamper確認ではありません。FingerprintはRotation Auditの失敗記録だけを照合します。Envelope、Nonce、Tag、Key Material、Payload、Tenant Raw IDをSELECT、Log、Ticketへ出しません。

**修正方法:** Unknown Keyなら旧Keyを削除せずRead可能なProviderへ一時的に復旧し、同じPurpose／Tenant Scopeで[Plan（Read-only）](tenant-protection.md#planread-only)を実行します。Tag TamperやAAD不一致はEnvelopeを手動編集せず、原因を隔離して承認済みBackup／Offline変換の手順へ戻します。RuntimeのPlaintext Fallbackは行いません。

## 非空の旧Protected SchemaでMigrationが停止

**症状:** `database:migrate`が変更前のSafe Migration Errorで停止し、Shellへ非ゼロ終了を返します。正確なTop-level終了値はApplicationのConsole Error境界で確認してください。旧保護対象Tableが非空でも、MigrationはRow内容を検査しません。

**考えられる原因:** 既存の旧Protected SchemaにRowが残っており、Experimental v1の新しいEnvelope／Tenant契約へ自動変換できないためです。非空判定は安全停止のためだけに使われます。

**確認方法:** Read-onlyのMigration StatusとTableの空／非空件数だけを確認します。`database:migrate --dry-run`を使い、Payload、Plaintext、Envelope Header、Key Material、Rowの内容をSELECTしません。`database:migrate`には`--no-interaction` Optionはありません。空Tableは`0`件、非空Tableは停止対象です。

**修正方法:** 新規ApplicationではDatabaseをReset／RecreateしてからMigrationし、必要なDataはApplicationが承認したFramework外のOffline変換で別途移行します。既存Tableを直接ALTER、暗黙変換、削除して再実行しないでください。完了後に`database:migrate`、`build:compile`を順に実行します。

## Rotationの`remaining`が0にならない

**症状:** Confirm済みRotationはExit Code `0`でも`remaining`が残る、または一部Rowが`failed`／`skipped`になります。Protection／Runtime FailureならExit Codeは`1`、Input／Confirm／Config Errorなら`2`です。

**考えられる原因:** Purpose、Tenant Pair、Old Key ID、Checkpointが一致していない、CAS競合でSkipされた、失敗Auditを修復していない、またはDatabase以外のReplica、Backup、Dead Letter、Retention Windowに旧Keyが残っています。

**確認方法:** 同じScopeで`storage:protection:plan --json`を再実行し、`purpose`、Tenant Scope、Key ID、Checkpoint、`remaining`、`failed`だけを照合します。Read-only Planは全範囲を集計しますが、Payload、Nonce、Tag、Tenant Raw ID、Key Materialを出しません。失敗FingerprintとCheckpointを安全なAuditから確認します。

**修正方法:** 旧KeyをRead可能なままにし、同じScope／Checkpointで`storage:protection:rotate --confirm --json`を再開します。Failed Auditを原因修復後に再処理し、CAS Skipは再Planで残件を確認します。Databaseの`remaining=0`を確認した後もReplica、Backup、Dead Letter、Retention Windowを別途確認し、すべての境界が完了するまで旧Keyを削除しません。

## journal.jsonlへ出力されない

**症状:** Operationは完了しますが、`var/log/journal.jsonl`が存在しない、またはRecordが増えません。

**考えられる原因:** `config/journal.php`で`enabled`がfalse、Pathが相対Path、Parent Directoryがない／書込不能、または`best_effort` Observerの失敗を見落としています。

**確認方法:** ConfigとDirectory権限を確認します。

```bash
php -r '$config = require "config/journal.php"; var_export($config["jsonl"] ?? null); echo PHP_EOL;'
test -d var/log && test -w var/log && printf 'journal directory is writable\n'
```

**修正方法:** `enabled=true`、既存の書込可能な絶対Path、`best_effort`または`required`を設定します。FrameworkはDirectoryを作らないため、Deploy／Setup工程でParent Directoryを準備します。

## Outcome Status

### OutcomeがPending／Not Found／Expiredか判別できない

**症状:** `OperationOutcomeQuery::find()`が`OperationOutcomeUnavailable`になり、処理中、未知のOperation ID、失敗、保持期限切れを区別できません。

**考えられる原因:** `OperationOutcomeQuery`はCurrent Actor、Current Tenant、`OperationDataPurpose`を受けるDefault-deny Queryです。Unknown、Tenant不一致、Deny、Retention削除は同じUnavailableになります。

**確認方法:** Public `OperationStatusQuery`、`GET /operations/{operationId}`、またはGenerated `.status()`で現在Stateを確認します。

**修正方法:** Public Status Resultを次のように分類します。PHP AdapterでOutcomeだけが必要な場合も、認可済み`OperationOutcomeQuery`へ明示的なPurposeを渡します。

| 判定 | Applicationが返す状態 |
| --- | --- |
| Operationが存在し、非Terminal | Pending |
| CompletedかつOutcomeあり | Completed |
| Rejected／Failed／Dead Letter | Terminal without outcome |
| UnknownまたはDeny | 404 Unavailable |
| Allow済みで期限切れを証明 | 410 Expired |

Persistence PayloadやFramework Table Schemaを利用者向けResponseへ直接公開しません。

## Sensitive値がJournalで見えない

**症状:** `#[Sensitive]`を付けた値が`[masked]`、除外、Hashとして表示され、入力値を確認できません。

**考えられる原因:** Sensitive Projectionが意図どおりObserved Journalへの出力を制限しています。これは不具合ではありません。

**確認方法:** `OperationValue`のPropertyに付けた`#[Sensitive]`と`SensitiveMode`を確認します。Raw値をLogへ追加して検証しないでください。

**修正方法:** Debuggingには非Sensitiveな相関ID、Category、安定したError Codeを使います。Raw Secretが業務処理に不要なら保存しません。秘密値の復元が必要な業務要件は、Applicationの暗号化Store、Key管理、Access Control、監査を別途設計します。

## FAQ: 202は完了を意味しますか

いいえ。`202 Accepted`はDeferred OperationをDurableに受け付けたことだけを意味します。WorkerのAttempt、Retry、Terminal State、Outcomeを同じOperation IDで追跡してください。

## FAQ: 失敗をすべてRejectedへ変換できますか

変換しません。予期された業務拒否だけ`OperationRejectedException`を使います。一時障害は`RetryableException`、BugやInfrastructure Failureは通常のThrowableとしてSupervision／Failure Policyへ渡します。
