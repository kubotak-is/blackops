# BlackOps CLI

Project Rootの`blackops`はApplication所有の薄いEntrypointです。Framework Packageが提供するCommandをProjectの設定から構成します。最初に目的別の一覧を読み、必要なCommandのHelpを確認してから実行してください。Build Artifactを必要とするRuntime Commandは`build:compile`のManifest／Containerを使います。

```bash
php blackops list
php blackops help <command>
```

Global `list`とOperation Commandの`help`はManifest Metadataだけを使い、Handler、Database、Container、Actor Providerを解決しません。Framework／Application Commandの固有`help`はDefinition取得のためLazy Commandを解決する場合があります。旧`bin/blackops`や`blackops:*` Prefixは現行の互換入口ではありません。

## コマンド実行一覧

RuntimeはProject RootのHostまたはApplication Containerです。Containerで実行する場合は`docker compose run --rm app php blackops ...`へ読み替えます。

| 目的 | Command | 実行条件 | 出力／終了Code |
| --- | --- | --- | --- |
| Operationを一覧する | `operation:list` | Project設定と公開Operation定義を読み、SourceからMetadataをCompileする。 | Type ID、Definition、StrategyのTable／`0` |
| Build ArtifactとFramework Profileを生成する | `build:compile` | PHP、Config、Source Root、Application設定を同じBuild IDでCompileし、Content Hash付きFramework Profileも生成する。 | `Build artifacts written.`／`0`、Missing／Invalid／ID不一致はFailure |
| Operationを実行する | Applicationの`<command>` | OperationごとのScalar Long Option、`--json`、生成済みBuild Artifactを使う。 | `completed`／`accepted`、Exit `0`; Validation `2`; Rejected／Internal `1` |
| Databaseの状態を確認する | `database:status` | Database Connectionが必要。 | applied／pending件数／`0` |
| Database Migrationを実行する | `database:migrate [--dry-run]` | `--dry-run`なしはMigrationを適用する。 | SQLまたは`Database migrations applied`／`0` |
| Seedデータを投入する | `database:seed` | Fresh Compiled ContainerとDatabaseが必要。 | `Database seeding completed.`／成功`0`、安全なFailure／`1` |
| Frontendを生成する | `frontend:generate` | Frontend Artifactと`config/frontend.php`が必要。 | `Generated N frontend files...`／`0` |
| Frontend契約を確認する | `frontend:check` | Generated TreeとContract Artifactを読む。 | Fresh／`0`、Missing／Drift `1`、Invalid `2` |
| Deferred Workerを実行する | `worker:run [--iterations] [--idle-sleep-milliseconds]` | PostgreSQL、Heartbeat、PCNTLが必要。 | `Worker stopped. Processed claims: N`／`0` |
| Scheduled Operationを評価する | `operation:schedule:run [--json]` | PostgreSQL、Build Artifact、Schedule Metadataが必要。 | `evaluated`／`accepted`／`skipped_misfire`／`skipped_overlap`／`failed`、Exit `0`／`1`／`2` |
| Operationを診断する | `operation:inspect <operation-id> [--json]` | PostgreSQL Diagnosticsを読む。 | Human／Versioned JSON、Exit `0`／`2` Invalid／`3` Unavailable／`4` Storage |
| Local Viewerを起動する | `operation:viewer` | Local PCNTL、Loopback、Enable Gateが必要。 | Bootstrap URLを一度出力／`0`、安全なFailure／`1` |
| Retention Planを作る | `retention:plan --transport-payload-days=7 --journal-days=30 --outcome-days=14 --dead-letter-days=90` | 4つの公開Retention期間を指定する。Idempotency Recordの第5期間は設定から解決する。 | 対象件数とEligible時刻／`0` |
| Retention PurgeをDry-runする | `retention:purge --dry-run --transport-payload-days=7 --journal-days=30 --outcome-days=14 --dead-letter-days=90` | `--dry-run`と4つの公開Retention期間を指定する。 | Plan件数／`0` |
| Retention Purgeを実行する | `retention:purge --confirm --transport-payload-days=7 --journal-days=30 --outcome-days=14 --dead-letter-days=90 --policy-ref=production-retention-v1 --actor=system:retention` | `--confirm`、Policy、Actorと4つの公開Retention期間を指定する。 | Purge件数／`0` |
| Maintenanceを実行する | `scheduler:run`／`scheduler:daemon [--interval] [--iterations]` | SchedulerとClockが必要。 | tasks／total_affected／`0` |
| OutboxをRelayする | `outbox:relay:run [--batches]`／`--until-empty` | DatabaseとTransportが必要。 | claimed／sent／retried／dead-lettered／stale／`0` |
| Outbox Relay Daemonを実行する | `outbox:relay:daemon [--interval-milliseconds] [--iterations]` | PCNTLとTransportが必要。 | Batch件数／`0` |
| Dead Letterを再開する | `outbox:dead-letter:retry <record-id> --actor=<actor> --reason=<reason>` | Database、監査Actor、Reasonが必要。 | `dead-letter retry scheduled`／`0` |
| Observer ProjectionをReplayする | `journal:observer:replay --dry-run --operation-id=<uuidv7> --observer=application-jsonl --batch-size=100` | Selectorを1つ、`--observer`を1つ以上、`--batch-size`を指定する。 | selected／delivered／failed／`0` |
| Observer Projectionを更新する | `journal:observer:replay --confirm --operation-id=<uuidv7> --observer=application-jsonl --checkpoint=journal-replay-20260701 --actor=operator --reason="restore projection"` | Selector、Observer、Checkpoint、Actor、Reasonを指定する。 | selected／delivered／failed／`0` |
| Storage Protection Planを作る | `storage:protection:plan --purpose=journal_record [--tenant-type=<type> --tenant-id=<id>] --old-key-id=<old> --new-key-id=<new> --batch=100 --checkpoint=<scope> --json` | Purpose、任意のTenant Scope、Old／New Key、Batch 1–1000、Checkpointを指定する。Tenant Scopeを指定する場合はPairが必要。 | `selected`／`remaining`、Exit `0`; 入力Error `2`; Storage Error `1` |
| Storage ProtectionをRotateする | `storage:protection:rotate --purpose=journal_record --old-key-id=<old> --new-key-id=<new> --batch=100 --checkpoint=<scope> --actor=<actor> --reason=<reason> --confirm --json` | Confirm、Checkpoint、Actor、Reason、CAS／Audit／Resumeを指定する。 | `rotated`／`skipped`／`failed`／`remaining`、Exit `0`／`1`／`2` |
| OperationとMigrationを生成する | `make:operation <Feature/Action> --type=<operation.type>`／`make:migration <Description>` | Application filesystemへSourceを追加する。 | `Created: ...`／`0` |
| Authentication Starterを生成する | `make:auth [--force]` | Application filesystemへSourceを追加／更新する。 | Created／Updated／`0` |
| Seederを生成する | `make:seeder <Name>`（[Seederを生成する](project-generators.md#seederを生成する)） | Application filesystemへSeeder Sourceを追加する。 | Created／`0` |

「変更なし」はCommand自体がDatabase／Artifact／Generated Treeを変更しない意味です。Commandのmutation（変更範囲）は実行条件と各詳細Guideで確認し、Operation実行、Migration、Seeder、Purge、Relay、Worker、GeneratorはApplication DataまたはSourceを変更します。Optionの全量と既定値は本文の表と各詳細Guideを参照します。実行前に、Helpへ公開されたOptionとその既定値は`php blackops help <command>`で再確認してください。Helpが本文表の全Optionを必ず列挙するとは限りません。Project Rootの公開Retention Commandは4つの期間Optionだけを受け付けます。Idempotency Recordの第5期間は`config/retention.php`の`idempotency_record_days`で管理し、省略時は4つの基本期間の最長値を使います。

### Projectを作る・Buildする

```bash
php blackops operation:list
php blackops build:compile
```

`operation:list`はProject設定から公開されているOperation定義を発見し、Operation Providerと合わせてMetadataをCompileしてTableへ表示します。このCommandはBuild Artifactを読み込まず、Source DiscoveryとMetadata Compileを実行時に行います。

`build:compile`は`config/operations.php`のOperation、HTTP、Frontend、`config/app.php`のApplication Command Discoveryを同じBuild IDでCompileし、ManifestとSymfony DI Containerを書き出します。Build ArtifactがMissing／Invalid／ID不一致の場合、Operation実行RuntimeはSource ScanへFallbackしません。

Stable `1.2.0`の`build:compile`はFramework-owned proxyを唯一のProfileとして、Build IDとContent Hashに結び付いた不変Framework Artifact Unitを発行します。RuntimeはUnitを事前検証してから生成コードを読み込み、同じBuild IDを上書きしません。Rollbackは以前の完全なContainer・各Manifest・一致するFramework Artifact Unitを同一Release組として戻します。

対象の`#[Transactional]`／`#[AfterCommit]`クラスはFramework Signature／Definition境界を満たすか監査し、Unsupported Diagnosticを修正して新しいBuild IDで再Compileします。生成ContainerをHTTP、CLI、Workerの各起動Smokeで検証し、Rollbackは以前の完全なContainer、Operation／HTTP／Frontend／Command Manifest、`proxy-profiles/<build-id>-<content-hash>`と参照Framework Unitを同一Release組として戻します。

Framework Profileは`never`とnamed variadicを含むSignature Matrixを対応対象とし、unproxied fallbackはありません。

### Operationを実行する

#### Operation Command

Operation Classへ`#[ConsoleCommand]`を付けると、`build:compile`がCommand ManifestへCLI契約を固定します。

```bash
php blackops build:compile
php blackops help order:create
php blackops order:create --reference=order-001
php blackops order:create --reference=order-001 --json
```

`--json`の成功は`{"schemaVersion":1,"status":"completed",...}`、Deferred受付は`status: "accepted"`です。Humanは`Completed.`または`Accepted operation <operation-id>.`です。Binding／ValidationはExit `2`、業務Rejected／Internal ErrorはExit `1`で、ThrowableやCredentialを出力しません。[ConsoleCommand](console-command.md)でAttribute、Authorization、Failure境界を完走できます。

#### Frontend

Frontend Commandは`build:compile`を暗黙実行しません。

```bash
php blackops build:compile
php blackops frontend:generate
php blackops frontend:check
```

`frontend:generate`はContract Artifactから`resources/js/blackops/`（Application設定のOutput）を全再生成します。`frontend:check`はRead-onlyで、Fresh `0`、Missing／Drift `1`、Config／Artifact／Contract Invalid `2`です（固定Contract: **Fresh 0、Missing／Drift 1、Invalid 2**）。Generated Fileを直接編集せず、Application-owned Sourceを修正して再生成します。

### Dataを管理する

```bash
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
php blackops database:seed
```

標準順序は`database:status` → `database:migrate --dry-run` → `database:migrate` → `build:compile` → `database:seed`です。Migration、Build、Seedは暗黙に呼び出されません。

### 診断・復旧する

```bash
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
php blackops operation:inspect <operation-id>
php blackops operation:inspect <operation-id> --json
```

`operation:inspect`のInvalid UUIDv7はExit `2`、Missing／Fully purged／Unauthorizedは`operation.unavailable`としてExit `3`、Storage／Decode／Integrity ErrorはExit `4`です。`operation:viewer`は`config/diagnostics.php`のEnable Gateが必要なLoopback限定Read-only Local Viewerで、起動ごとに変わるBootstrap URLをstdoutへ一度だけ出します。Docker-only QuickstartではHost BrowserからViewerへ接続せず、Human／JSON Inspectを使います。

#### RetentionとMaintenance

`retention:plan`は変更しません。`retention:purge`は`--dry-run`または`--confirm`のいずれか一つを要求し、Confirm時だけPurge Auditを伴って変更します。`scheduler:run`／`scheduler:daemon`はRetention等のFramework Maintenance専用です。Application Scheduleは`operation:schedule:run --json`を外部Supervisorから一回ずつ起動し、Deferredなら`worker:run`で完了させます。[Scheduled Operation](scheduled-operation.md)のCountsとExit `0`／`1`／`2`を確認してください。

#### Outbox／Observer Replay

Outboxは`outbox:relay:run --until-empty`またはDaemonでRelayし、別Processの`worker:run`がchild Operationを実行します。Relayの`sent`だけではHandler完了を意味しません。Dead Letter再開はRecord ID、Actor、Reasonを必須とし、PayloadやThrowableを表示しません。[Outbox](outbox.md)にDispatch → Commit → Relay → Worker → Status／Journal → Retry／Dead Letterの順を示しています。

`journal:observer:replay`はCanonical Journalを変更せず、Selector／Observer／Checkpointを使って現在のSensitive Projectionを有限Batchで再適用します。`--dry-run`は変更せず、ConfirmにはActorとReasonが必要です。Checkpoint／Resumeの詳細は[Observer Replay](observer-replay.md)を参照してください。

`storage:protection:plan`はHeader／Clear Metadataだけを読むRead-only Planです。Tenant Scopeは任意で、省略できます。指定する場合は`--tenant-type`と`--tenant-id`を必ず同時に指定し、片方だけの入力はErrorになります。`storage:protection:rotate`はConfirmなしではDry-runとなり、Confirm時は`--checkpoint`を明示し、非空`--actor`／`--reason`を必須にします。出力へPayload、Tenant Raw ID、Ciphertext、Nonce、Tag、Key Materialは含めません。`remaining`が0でもReplica、Backup、Dead Letter、Retention Windowは別途確認します。旧Keyを先に削除しないでください。

<a id="stablemain境界"></a>

## Stable 1.2.0で利用できる範囲

公開済みExperimental Stable `1.2.0`で案内できるのはProject Root `blackops`、`make:operation`／`make:migration`、Migration、Typed Operation、HTTP／Deferred、Worker、Journal、Outcome、Retention、Tenant／Protected Storage、Frontend Contract、Status／Diagnostics、Console Adapter、Outbox、`make:auth`／`make:seeder`、Observer Replay、Framework Proxy Profile Artifact Unitです。BlackOps BoardはRepository Exampleとして別管理します。Business／Security Audit Trail、署名付き履歴Export、未Releaseの追加CommandはStableの提供範囲ではありません。[Releases](mvp-status.md)の表を正本にします。

## 次にBootstrapの署名を引く

HTTP、Console、SessionのProcess登録を正確に調べる場合は、[Application Bootstrap](application-bootstrap.md)を参照します。
