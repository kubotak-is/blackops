# BlackOps CLI

Project Rootの`blackops`はApplication所有の薄いEntrypointです。Framework Packageが提供するCommandをApplicationのConfiguration Snapshotから構成します。Build Artifactを必要とするRuntime Commandは`build:compile`のManifest／Containerを使い、`operation:list`はSource Discoveryを実行時に行います。公開Commandの全Optionは、実装とVersionに同期した次のHelpを正本にしてください。

```bash
php blackops list
php blackops help <command>
```

Global `list`とOperation Commandの`help`はManifest Metadataだけを使い、Handler、Database、Container、Actor Providerを解決しません。Framework／Application Commandの固有`help`はDefinition取得のためLazy Commandを解決する場合があります。旧`bin/blackops`や`blackops:*` Prefixは1.1.0の互換入口ではありません。

## コマンド実行一覧

RuntimeはProject RootのHostまたはApplication Containerです。Containerで実行する場合は`docker compose run --rm app php blackops ...`へ読み替えます。

| Task | Command | 変更 | Runtime／主なOption | 成功時のOutput／Exit | 詳細 |
| --- | --- | --- | --- | --- | --- |
| Discovery | `operation:list` | なし | `ApplicationConfigurationSnapshot`＋`ApplicationOperationDiscovery`（Source）＋Metadata Compiler | Type ID、Definition、StrategyのTable／`0` | [BuildとDiscovery](#buildとdiscovery) |
| Build | `build:compile` | Artifact生成 | PHP、Config、Source Root、Application ConfigurationのBuild ID | `Build artifacts written.`／`0` | [Build Artifact不在／Build ID不一致](troubleshooting.md#build-artifact不在build-id不一致) |
| Build（main） | `build:compile --proxy-profile=ray|framework` | Profile Artifact生成 | main onlyのProxy Profile、Build ID、Content Hash | `Build artifacts written.`／`0` | [Build Artifact不在／Build ID不一致](troubleshooting.md#build-artifact不在build-id不一致) |
| Operation実行 | Applicationの`<command>` | Operation次第 | Scalar Long Option、`--json` | `completed`／`accepted`、Exit `0`; Validation `2`; Rejected／Internal `1` | [Operation Command](#operation-command) |
| Database | `database:status` | なし | Database Connection | applied／pending件数／`0` | [Database](#database) |
| Database | `database:migrate [--dry-run]` | `--dry-run`なしはMigration適用 | Database Connection | SQLまたは`Database migrations applied`／`0` | [リリース手順](deployment.md#リリース手順) |
| Seeder | `database:seed` | Seedデータ | Fresh Compiled Container、Database | `Database seeding completed.`／成功`0`、安全なFailure／`1` | [Seeder](database-seeding.md) |
| Frontend（main） | `frontend:generate` | Generated Tree書換 | Frontend Artifact、`config/frontend.php` | `Generated N frontend files...`／`0` | [Frontend](frontend.md) |
| Frontend（main） | `frontend:check` | なし | Generated Tree、Contract Artifact | Fresh／`0`、Missing／Drift `1`、Invalid `2` | [最小の実行順](testing.md#最小の実行順) |
| Deferred Worker | `worker:run [--iterations] [--idle-sleep-milliseconds]` | Operation State／Journal | PostgreSQL、Heartbeat、PCNTL | `Worker stopped. Processed claims: N`／`0` | [Inline and Deferred](execution.md) |
| Application Schedule（main） | `operation:schedule:run [--json]` | Due Occurrenceの評価、Inline実行、Deferred受理 | PostgreSQL、Build Artifact、Schedule Metadata | `evaluated`／`accepted`／`skipped_misfire`／`skipped_overlap`／`failed`、Exit `0`／`1`／`2` | [Scheduled Operation](scheduled-operation.md) |
| Diagnostics（main） | `operation:inspect <operation-id> [--json]` | なし | PostgreSQL Diagnostics | Human／Versioned JSON、Exit `0`／`2` Invalid／`3` Unavailable／`4` Storage | [Operation ID付き500を調べる](troubleshooting.md#operation-id付き500を調べる) |
| Diagnostics（main） | `operation:viewer` | なし（Read-only） | Local PCNTL、Loopback、Enable Gate | Bootstrap URLを一度出力／`0`、安全なFailure／`1` | [Local Viewerが起動／表示できない](troubleshooting.md#local-viewerが起動表示できない) |
| Retention | `retention:plan --transport-payload-days=7 --journal-days=30 --outcome-days=14 --dead-letter-days=90 --idempotency-record-days=90` | なし | 5つのRetention期間 | 対象件数とEligible時刻／`0` | [Retention](retention.md) |
| Retention | `retention:purge --dry-run --transport-payload-days=7 --journal-days=30 --outcome-days=14 --dead-letter-days=90 --idempotency-record-days=90` | なし（Planのみ） | `--dry-run`と5つのRetention期間 | Plan件数／`0` | [Retention](retention.md) |
| Retention | `retention:purge --confirm --transport-payload-days=7 --journal-days=30 --outcome-days=14 --dead-letter-days=90 --idempotency-record-days=90 --policy-ref=production-retention-v1 --actor=system:retention` | Purge | `--confirm`、5つのRetention期間、Policy／Actor | Purge件数／`0` | [Retention](retention.md) |
| Maintenance | `scheduler:run`／`scheduler:daemon [--interval] [--iterations]` | Retention等のMaintenance | Scheduler、Clock | tasks／total_affected／`0` | [プロセス一覧](deployment.md#プロセス一覧) |
| Outbox Relay（main） | `outbox:relay:run [--batches]`／`--until-empty` | Row状態更新 | Database、Transport | claimed／sent／retried／dead-lettered／stale／`0` | [RelayとWorkerを分けて実行する](outbox.md#relayとworkerを分けて実行する) |
| Outbox Relay（main） | `outbox:relay:daemon [--interval-milliseconds] [--iterations]` | Row状態更新 | PCNTL、Transport | Batch件数／`0` | [RelayとWorkerを分けて実行する](outbox.md#relayとworkerを分けて実行する) |
| Dead Letter（main） | `outbox:dead-letter:retry <record-id> --actor=<actor> --reason=<reason>` | Retry再開 | Database、監査Actor／Reason | `dead-letter retry scheduled`／`0` | [確認とFailure Journey](outbox.md#確認とfailure-journey) |
| Observer Replay（main） | `journal:observer:replay --dry-run --operation-id=<uuidv7> --observer=application-jsonl --batch-size=100` | なし（Projection確認） | exactly one Selector、one or more `--observer`、`--batch-size` | selected／delivered／failed／`0` | [Observer Replay](observer-replay.md) |
| Observer Replay（main） | `journal:observer:replay --confirm --operation-id=<uuidv7> --observer=application-jsonl --checkpoint=journal-replay-20260701 --actor=operator --reason="restore projection"` | Projection更新 | Selector、Observer、Checkpoint、Actor、Reason | selected／delivered／failed／`0` | [Observer Replay](observer-replay.md) |
| Storage Protection（main） | `storage:protection:plan --purpose=journal_record --old-key-id=<old> --new-key-id=<new> --batch=100 --checkpoint=<scope> --json` | なし（Read-only） | Purpose、Tenant Pair、Old／New Key、Batch 1–1000、Checkpoint | `selected`／`remaining`、Exit `0`; 入力Error `2`; Storage Error `1` | [Tenant and Storage Protection](tenant-protection.md) |
| Storage Protection（main） | `storage:protection:rotate --purpose=journal_record --old-key-id=<old> --new-key-id=<new> --batch=100 --checkpoint=<scope> --actor=<actor> --reason=<reason> --confirm --json` | BOPD EnvelopeのBounded Re-encrypt | Confirm、明示Checkpoint、Actor、Reason、CAS／Audit／Resume | `rotated`／`skipped`／`failed`／`remaining`、Exit `0`／`1`／`2` | [Tenant and Storage Protection](tenant-protection.md) |
| Generator | `make:operation <Feature/Action> --type=<operation.type>`／`make:migration <Description>` | Application Source追加 | PHP filesystem | `Created: ...`／`0` | [Generators](project-generators.md) |
| Generator（main） | `make:auth [--force]`／`make:seeder <Name>` | Application Source追加／更新 | Application filesystem | Created／Updated／`0` | [Session Authentication Starter](security.md#session-authentication-starter) |

「変更なし」はCommand自体がDatabase／Artifact／Generated Treeを変更しない意味です。Operation実行、Migration、Seeder、Purge、Relay、Worker、GeneratorはApplication DataまたはSourceを変更します。Optionの全量とDefaultは必ず`php blackops help <command>`で再確認してください。

## BuildとDiscovery

```bash
php blackops operation:list
php blackops build:compile
php blackops build:compile --proxy-profile=framework
```

`operation:list`は`ApplicationConfigurationSnapshot`を受け取り、`ApplicationOperationDiscovery`で設定済みのApplication Source RootをDiscoveryし、Operation Providerと合わせてMetadataをCompileしてTableへ表示します。このCommandはBuild Artifactを読み込まず、Source DiscoveryとMetadata Compileを実行時に行います。

`build:compile`は`config/operations.php`のOperation、HTTP、Frontend、`config/app.php`のApplication Command Discoveryを同じBuild IDでCompileし、ManifestとSymfony DI Containerを書き出します。Build ArtifactがMissing／Invalid／ID不一致の場合、Operation実行RuntimeはSource ScanへFallbackしません。

`--proxy-profile`はmainでのみ提供し、Build単位で一つだけ選択します。既定値は`ray`で、`framework`を選ぶとFramework-owned proxy manifestを共通のBuild ID／Content Hash Artifact Unitへ束ね、RuntimeはUnitを事前検証してから生成コードを読み込みます。RayとFrameworkのArtifactは同じContainerへ混在させません。移行修正後は同じBuild IDを上書きせず、新しいBuild IDで再Compileしてください。Rollbackは以前の完全なContainer・各Manifest・一致するArtifact Unitを同一Release組として戻します。

Framework移行前は、対象の`#[Transactional]`／`#[AfterCommit]`クラスを監査し、`build:compile --proxy-profile=framework`を実行してUnsupported Signature／Definition Diagnosticを記録します。Diagnosticの対象はFramework対応シグネチャへリファクタするか、そのReleaseでは`--proxy-profile=ray`を明示してRay Artifactを出荷します。修正後は新しいBuild IDで再Compileし、生成ContainerをHTTP、CLI、Workerの各起動Smokeで検証してください。Rollbackは以前の完全なContainer、Operation／HTTP／Frontend／Command Manifest、`proxy-profiles/<build-id>-<content-hash>`（Frameworkなら参照Framework Unitを含む）を同一Release組として戻し、Build ID／Content Hash、HTTP route、CLI command、Worker claimの起動確認を行います。

互換期間にはLegacy Ray 2.20.0限定の例外が二つあります。PHP 8.5では、Attribute対象の`never` return methodを持つProxyをCompileできず、`A never-returning method must not return`で終了します。また、extra named variadic valueをProxy越しに渡すと値を落とします。どちらかを使う対象では既定Rayへ戻さず、`--proxy-profile=framework`を選択してください。Framework Profileは両Signatureを対応対象とし、この案内はunproxied fallbackを許可するものではありません。例外はRay Profileを削除する移行Taskの完了時に終了します。

## Operation Command

Operation Classへ`#[ConsoleCommand]`を付けると、`build:compile`がCommand ManifestへCLI契約を固定します。

```bash
php blackops build:compile
php blackops help order:create
php blackops order:create --reference=order-001
php blackops order:create --reference=order-001 --json
```

`--json`の成功は`{"schemaVersion":1,"status":"completed",...}`、Deferred受付は`status: "accepted"`です。Humanは`Completed.`または`Accepted operation <operation-id>.`です。Binding／ValidationはExit `2`、業務Rejected／Internal ErrorはExit `1`で、ThrowableやCredentialを出力しません。[ConsoleCommand](console-command.md)でAttribute、Authorization、Failure境界を完走できます。

## Frontend

Frontend Commandは`build:compile`を暗黙実行しません。

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

`frontend:generate`はContract Artifactから`resources/js/blackops/`（Application設定のOutput）を全再生成します。`frontend:check`はRead-onlyで、Fresh `0`、Missing／Drift `1`、Config／Artifact／Contract Invalid `2`です（固定Contract: **Fresh 0、Missing／Drift 1、Invalid 2**）。Generated Fileを直接編集せず、Application-owned Sourceを修正して再生成します。

## Database

```bash
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
php blackops database:seed
```

標準順序は`database:status` → `database:migrate --dry-run` → `database:migrate` → `build:compile` → `database:seed`です。Migration、Build、Seedは暗黙に呼び出されません。

## Execution／Diagnostics

```bash
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
php blackops operation:inspect <operation-id>
php blackops operation:inspect <operation-id> --json
```

`operation:inspect`のInvalid UUIDv7はExit `2`、Missing／Fully purged／Unauthorizedは`operation.unavailable`としてExit `3`、Storage／Decode／Integrity ErrorはExit `4`です。`operation:viewer`は`config/diagnostics.php`のEnable Gateが必要なLoopback限定Read-only Local Viewerで、起動ごとに変わるBootstrap URLをstdoutへ一度だけ出します。Docker-only QuickstartではHost BrowserからViewerへ接続せず、Human／JSON Inspectを使います。

## RetentionとMaintenance

`retention:plan`は変更しません。`retention:purge`は`--dry-run`または`--confirm`のいずれか一つを要求し、Confirm時だけPurge Auditを伴って変更します。`scheduler:run`／`scheduler:daemon`はRetention等のFramework Maintenance専用です。Application Scheduleは`operation:schedule:run --json`を外部Supervisorから一回ずつ起動し、Deferredなら`worker:run`で完了させます。[Scheduled Operation](scheduled-operation.md)のCountsとExit `0`／`1`／`2`を確認してください。

## Outbox／Observer Replay

Outboxは`outbox:relay:run --until-empty`またはDaemonでRelayし、別Processの`worker:run`がchild Operationを実行します。Relayの`sent`だけではHandler完了を意味しません。Dead Letter再開はRecord ID、Actor、Reasonを必須とし、PayloadやThrowableを表示しません。[Outbox](outbox.md)にDispatch → Commit → Relay → Worker → Status／Journal → Retry／Dead Letterの順を示しています。

`journal:observer:replay`はCanonical Journalを変更せず、Selector／Observer／Checkpointを使って現在のSensitive Projectionを有限Batchで再適用します。`--dry-run`は変更せず、ConfirmにはActorとReasonが必要です。Checkpoint／Resumeの詳細は[Observer Replay](observer-replay.md)を参照してください。

`storage:protection:plan`はHeader／Clear Metadataだけを読むRead-only Planです。`storage:protection:rotate`はConfirmなしではDry-runとなり、Confirm時は`--checkpoint`を明示し、非空`--actor`／`--reason`を必須にします。出力へPayload、Tenant Raw ID、Ciphertext、Nonce、Tag、Key Materialは含めません。`remaining`が0でもReplica、Backup、Dead Letter、Retention Windowは別途確認します。旧Keyを先に削除しないでください。

## Stable／main境界

Stable `1.1.0`で案内できるのはProject Root `blackops`、`make:operation`／`make:migration`、Migration、Typed Operation、HTTP／Deferred、Worker、Journal、Outcome、RetentionのSurfaceです。Tenant／Protected Storage、Frontend Contract、Status／Diagnostics、Console Adapter、Outbox、`make:auth`／`make:seeder`、Observer Replay、BlackOps BoardはRepository `main`のExperimental Surfaceです。未Release機能をStable ApplicationのInstall手順へ混入させないでください。[Releases](mvp-status.md)の表を正本にします。
