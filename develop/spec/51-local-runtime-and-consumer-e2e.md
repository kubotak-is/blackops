# Local Runtime and Consumer End-to-End

## Purpose

QuickstartをFramework RepositoryのRoot Autoloadから完全に分離した一時ConsumerとしてComposer Installし、Quickstart所有のDocker／FrankenPHP／PostgreSQL RuntimeでBuild、Migration、HTTP、Worker、Retry、Outcome、Sensitive Projection、RetentionをEnd-to-End検証する。

Default Compose起動はHTTPとPostgreSQLだけを対象とし、Build、Migration、Worker、Scheduler、Purgeを暗黙実行しない。

## Public JSONL Journal Configuration

`config/journal.php` はObserved Journal JSONL Backendを定義する。

```php
return [
    'jsonl' => [
        'enabled' => true,
        'path' => dirname(__DIR__) . '/var/log/journal.jsonl',
        'delivery' => 'best_effort',
    ],
];
```

Validation Contract:

- `jsonl` は配列
- `enabled` はBoolean。省略時false
- enabled=falseの場合はPath／Deliveryを要求せずObserverを構成しない
- enabled=trueの `path` は空でない絶対Path
- Parent Directoryは存在し、Directoryで、書込可能
- `delivery` は `best_effort` または `required`
- Quickstart Defaultは `best_effort`

FrameworkはLog Directoryを作成しない。JSONL FileはAppend Binary Modeで開き、既存内容を切り捨てない。File Open ErrorはConfig Keyと責務だけを示すPublic Bootstrap Errorへ変換し、Credentialを含めない。

HTTP RuntimeはJSONL Observer、Sensitive Projection Filter、Observed Journal Projector、Observer Binding／Aggregator／Pipelineを内部構成し、Inline Dispatcherへ渡す。Quickstart、Bootstrap、ConfigはInternal型を参照しない。

Best Effort Observer FailureはCanonical Operationを失敗させない。Required Deliveryを明示した場合は既存Observer Failure Contractに従う。Raw Sensitive ValueをObserved JSONLへ渡さない。

P7-006ではInline WelcomeのSensitive Header MaskをE2E検証する。Worker側Observer Lifecycle／Flushは必須範囲外とする。

## Quickstart Runtime Files

```text
examples/quickstart/
  Caddyfile
  Caddyfile.classic
  Dockerfile
  Dockerfile.frankenphp
  compose.yaml
```

### CLI Image

PHP 8.5 CLI、Composer、PCNTL、PDO PostgreSQL、ZIPを持つ。Application SourceやFramework SourceをImageへ固定Copyせず、Compose／Consumer TestがApplication Directoryを `/app` へMountする。

Host UID／GID Build Argumentを受け、CLI生成FileをHost User所有にできる。WorkerとMaintenanceも同じCLI Imageを使う。

### HTTP Image

Official FrankenPHP 1／PHP 8.5 Debian ImageへPDO PostgreSQL等の必要Extensionを追加する。Application Directoryを `/app` へMountし、Default Caddyfileから`public/worker.php`をWorker Modeで実行する。`Caddyfile.classic`は`public/index.php`をRequestごとに実行する明示Fallbackである。

HTTP ImageはComposer Install、Artifact Build、Migrationを起動時に行わない。

### PostgreSQL

PostgreSQL 18を使用し、`pg_isready` Health Checkを持つ。Credentialは`.env`／Process Environmentから与え、Imageへ埋め込まない。

## Compose Services and Profiles

Default:

```text
postgres
http
```

Explicit Tooling／Profile:

```text
app        CLI run target
worker     profile: worker
scheduler  profile: maintenance
http-classic profile: classic-mode
```

- `http` はWorker Modeで起動し、Healthy PostgreSQLを待つが、Schema MigrationやArtifact Buildを実行しない
- `http-classic` は明示`classic-mode` Profileだけで起動する
- `worker` は明示Profile／Commandでのみ起動する
- `scheduler` は明示Profile／Commandでのみ起動する
- Retention Purge ServiceをDefaultで起動しない
- HTTP PortはEnvironmentで上書きでき、Local Defaultは8080
- Classic Fallback PortはEnvironmentで上書きでき、Local Defaultは8081
- Named PostgreSQL Volumeを使用する

Quickstart Setup順序は明示する。

1. Composer Install
2. `.env.example` から `.env` Copy
3. `var/build`／`var/log` 準備
4. Artifact Build
5. Migration Status／Migrate
6. Default Compose起動

## Consumer Isolation

Consumer E2EはRepository Sourceを直接実行せず、一時Directoryへ `examples/quickstart/` をCopyする。

一時ConsumerだけにComposer Path Repositoryを注入する。

```text
type: path
url: /framework
symlink: false
versions.blackops/framework: 1.0.0
```

Framework RootをRead-only `/framework` としてComposer ContainerへMountし、PackageをConsumer `vendor/` へCopy Installする。Checked-in Quickstart `composer.json` は変更せず、Path Repository、Absolute Local Path、Lock、VendorをSourceへ戻さない。

Consumer RuntimeはTemp Consumerの `vendor/autoload.php` だけを使う。Framework RootのDev Autoload、Root `vendor/autoload.php`、Root Test Namespaceへ依存しない。

Test終了時はCompose Project／Volume／Container／Image／Temp DirectoryをCleanupする。失敗時もTrapでCleanupする。

## End-to-End Scenario

1. Temp Consumer Composer Install
2. `operation:list` がWelcome／Report／Diagnostics FailureをDiscovery
3. `build:compile` が3 Artifactを生成
4. Build時点でFramework Schemaが存在しない
5. `database:status` はRead-only
6. `database:migrate` 明示実行後だけSchemaが存在
7. Default `postgres`／Worker Mode `http` 起動
8. `GET /welcome` が200／Expected JSON
9. Welcome Sensitive Header Raw値がJSONLに存在せずMask値が存在
10. 認証付き`POST /failures`がSafe 500／UUIDv7 Operation ID
11. FailureがReceived／Attempt Started／Attempt Failed／Operation Failedへ到達
12. Human／JSON Inspectが同じID、Failed State、Timeline、Attempt、Availability、Maskを表示
13. PCNTLを持つnamed CLI ContainerでViewerを明示起動し、同じNetwork NamespaceからLoopback HTTPを検証
14. ViewerのTokenなし404、Bootstrap／Session、Canonical Path、GET／HEAD、POST 405が成立
15. Application／Framework JSONLが同じOperation IDを持ち、Credential／Sensitive／Exception Message／Raw Actor IDを出さない
16. `POST /reports` が202／Operation ID
17. Worker 1回目でRetry Scheduled
18. Due Time後のWorker再実行でCompleted
19. PostgreSQL Operation StateがCompleted
20. Outcome Rowが存在し、Encoded Outcomeが保存される
21. Retention PlanとPurge Dry Runが成功
22. Scheduler／Purge Confirmは実行しない

## Consumer Test Entrypoint

RepositoryのConsumer E2E Entrypointは `tests/Consumer/` 配下に置き、Rootから一つのCommandで実行できるようにする。Shell ScriptはSource Fileを編集せず、Temp CopyとDockerだけを変更する。

HTTP Response、JSON、Operation ID、Database State、JSONLは機械的に検証し、目視確認を成功条件にしない。Secret値はTest専用値を使い、Command OutputやReportへ実Credentialを残さない。

## Verification

- Quickstart所有Compose Configがvalidation成功
- Default Service SetにWorker／Schedulerが含まれない
- Default `http`がWorker Modeで、Classic Fallbackが明示Profileに分離される
- ImagesがPHP 8.5、FrankenPHP 1、必要Extensionを持つ
- Temp Consumer Composer InstallがRoot Dev Autoloadなしで成功
- Public Bootstrap／EntrypointにInternal Importがない
- Explicit Build／Migration後だけHTTPが起動可能
- Inline／Deferred／Retry／Outcome／RetentionがConsumer Runtimeで成功
- Sensitive Raw値がJSONLに存在しない
- HTTP／Human／JSON／Viewer／Application LogがFailure Operation IDを共有する
- Viewer Process／ContainerとToken／Cookie／Temporary Artifactが終了時に残らない
- Test後にSource TreeへVendor、Lock、Artifact、Log、Path Repositoryが残らない

## Traceability

- Journal Decision: [Quickstart Journal Observer](../decisions/070-quickstart-journal-observer.md)
- Installed Layout: [Installed Application Layout and Bootstrap](43-installed-application-layout-and-bootstrap.md)
- Phase Plan: [Phase 7 Delivery Plan](45-phase-7-delivery-plan.md)
- Quickstart: [Feature-first Quickstart Application](49-feature-first-quickstart-application.md)
