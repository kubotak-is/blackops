# Upgrade Guide

BlackOpsはExperimentalです。1.xのMinor Release間でも破壊的変更を行う場合があり、Backward Compatibilityは保証しません。変更を適用する前にApplication SourceとDatabaseをBackupし、検証環境でUpgradeしてください。

## 1.0.0から1.1.0

### 1. Composer Constraintを更新する

Applicationの`composer.json`でFramework Constraintを更新し、Lock Fileを再生成します。

```json
{
  "require": {
    "blackops/framework": "^1.1"
  }
}
```

```bash
composer update blackops/framework --with-all-dependencies
```

FrameworkはApplication所有のEntrypoint、生成済みOperation、Migration、Configurationを自動更新しません。

### 2. BlackOps CLIをRoot Entrypointへ置き換える

Application Rootで次をそのまま実行し、Skeleton `1.1.0`と同じEntrypointを新規作成します。旧`bin/blackops`は`dirname(__DIR__)`をApplication Rootとして使う実装のため、単純な`mv bin/blackops blackops`ではPath解決が壊れます。

```bash
install -m 0755 /dev/stdin blackops <<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

use BlackOps\Application\Application;

require __DIR__ . '/vendor/autoload.php';

/** @var Application $application */
$application = require __DIR__ . '/bootstrap/app.php';

exit($application->console()->run());
PHP

php blackops list
```

`php blackops list`が成功した後、旧Entrypointを削除し、Deploy Script、Compose、Process Manager、CIから`php blackops`を呼び出してください。

```bash
rm bin/blackops
```

Framework UpdateだけではApplication所有のEntrypoint PathやSourceは変わりません。この手順ではSkeleton `1.1.0`の完全版を使い、`__DIR__`がApplication Rootを指す状態を明示的に作ります。

### 3. BlackOps CLI Command名を置換する

| 1.0.0 | 1.1.0 |
| --- | --- |
| `blackops:build:compile` | `build:compile` |
| `blackops:operation:list` | `operation:list` |
| `blackops:database:status` | `database:status` |
| `blackops:database:migrate` | `database:migrate` |
| `blackops:worker:run` | `worker:run` |
| `blackops:retention:plan` | `retention:plan` |
| `blackops:retention:purge` | `retention:purge` |
| `blackops:scheduler:run` | `scheduler:run` |
| `blackops:scheduler:daemon` | `scheduler:daemon` |

旧`blackops:*`名はAliasとして残りません。Application Command名としては利用できます。

### 4. HTTP Runtimeを選択する

Skeleton `1.1.0`はFrankenPHP Worker ModeをDefaultにします。Skeletonの`Caddyfile`、`public/worker.php`、`Dockerfile.frankenphp`、`compose.yaml`をApplicationへ手動でMergeし、Long-running Processで安全な構成にしてください。

- Request、Actor、Tenant等のRequest固有StateをService Propertyやstaticへ保持しない
- `FRANKENPHP_MAX_REQUESTS`を環境に合わせて設定する。SkeletonのDefaultは`1000`
- Classic Fallbackを使う場合は`Caddyfile.classic`と`CLASSIC_HTTP_PORT`をMergeし、`classic-mode` Profileを明示する

Worker Modeへ移行しない場合も、既存Classic RuntimeはApplication側で継続できます。ただしSkeleton `1.1.0`のDefaultとは異なります。

### 5. HTTP ClientのError処理を更新する

- malformed JSONとNon-object JSONは`status=error`と`code`を含むHTTP 400になる
- Missing Field、型Binding、Value Validationは`operationId`、`category`、`code`、`violations`を含むHTTP 422になる
- 正常なInline 200、Deferred受付202、Operation IDのContractは変わらない

Client Testで400／422を明示的に検証してください。

### 6. BuildとMigrationを実行する

Framework Migration Schemaは1.0.0から変更していません。1.1.0ではApplication Migration Runtimeを追加しているため、Application固有Migrationがある場合はFramework Migrationの後に同じFlowで実行されます。

```bash
php blackops operation:list
php blackops build:compile
php blackops database:status
php blackops database:migrate --dry-run
php blackops database:migrate
```

### 7. Applicationを検証する

Inline、Deferred受付、Worker Retry、Outcome、JournalのSensitive Mask、Validation 422を検証します。新規OperationやMigrationはGeneratorで作成できますが、既存Sourceは書き換えられません。

```bash
php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create
php blackops make:migration CreateOrdersTable
```

## 1.1.0から1.2.0 Preview

このPreviewはRepository `main`の未公開`1.2.0` candidate向けです。`1.2.0`はLatest Stable、Production Ready、Packagist、公開Skeletonではありません。作業前にApplication Source、Database、Secret／Storage Key、Generated ArtifactをBackupし、検証環境で順序どおり実行してください。公開済みStable `1.1.0`のTag／Release／Packagistと、この章より前の1.0→1.1手順は変更しません。

### 1. BackupとRollback境界を固定する

Application RootでGit差分、`composer.json`／`composer.lock`、`bootstrap/`、`config/`、`app/`、`migrations/`、`resources/js/`、`var/build/`を保存します。PostgreSQLのDatabase／SchemaをBackupし、Deploymentで使うStorage KeyとKey VersionをSecret Managerから再取得できることを確認します。Secret、Session Token、Raw Protected PayloadをこのGuide、Git、Logへ貼り付けないでください。

`1.2.0`でのProtected Storage Migrationは不可逆です。Migration前のBackupと旧Keyを保持し、失敗時はDatabase／Application Source／Generated Artifactを同じBackup組へ戻します。旧FrameworkだけへComposerを戻して新Schemaを読ませるRollbackは行わず、旧Schemaへ戻す場合はBackup RestoreとDeployment停止を組み合わせます。

### 2. Candidate SourceとComposerを準備する

公開Packageを使うStable Installと混同しないよう、`1.2.0` candidateはRepository Sourceから検証します。Candidate SourceをLocal Path Repositoryとして指定する場合は、Application Rootで次を実行します。

```bash
composer config repositories.blackops-framework '{"type":"path","url":"../blackops","options":{"symlink":false,"versions":{"blackops/framework":"1.2.0"}}}'
composer require --no-update --no-interaction blackops/framework:^1.2
```

既存Applicationの`composer.json`を直接編集する場合もFramework Constraintだけを`^1.2`へ更新し、次のStepでLockを再生成します。Candidate未公開の環境ではPackagistから`^1.2`を解決できないため、Local Path／VCS Repositoryを明示してください。

### 3. Runtime BootstrapとApplication-owned Dependencyを確認する

`bootstrap/app.php`はApplicationの直接Importを確認したうえで、Frameworkが提供するRuntime Capabilityだけを構成します。

```php
return Application::configure(dirname(__DIR__))
    ->withEnvironmentFile()
    ->withConfiguration()
    ->create();
```

Opt-in Candidate-Skeleton Laneでは`public/index.php`が`SapiRuntime::run($application)`、Workerが`SapiRuntime::runWorker($application)`を呼ぶ構成へ手動で合わせます。Compatibility-first LaneはStable Entrypoint／Runtimeを保持します。どちらのLaneでもFramework UpdateはApplication-owned Entrypoint、Bootstrap、Config、既存Operation／Migration、Frontend Sourceを上書きしません。

Stable SourceとCandidate Sourceの差分を確認し、次のManual Merge MatrixからUpgrade Laneを一つ選びます。Canonical Source比較は[Stable 1.1.0とmainの差分](https://github.com/kubotak-is/blackops/compare/1.1.0...main)で行います。Local比較はFramework Repository Root（このGuideを実行するApplication Rootとは別のCheckout）で`git diff 1.1.0..main -- examples/quickstart`を実行するか、Application Rootから`git -C ../blackops-framework diff 1.1.0..main -- examples/quickstart`のようにFramework Repository Pathを明示します。

**Compatibility-first Lane**はStable `1.1.0`のBootstrap、Public Entrypoint、既存Config、Caddyfile、既存Direct Dependencyを保持したままComposerをCandidateへ更新します。ただしCandidateの`build:compile`が読む`frontend_manifest`だけは、次の最小Config追加をApplication-owned変更として適用します。Candidate-only Authentication、Tenant、Storage、Telemetry、Scheduled等は使わず、ConsumerのComposer更新前後Source不変を確認した後にこのConfigだけを変更し、`build:compile`、`operation:list`で既存ApplicationのGenerator／Artifact継続だけを確認します。このLaneはCandidate HTTP／Worker互換性を主張しません。CandidateのHTTP／Worker RuntimeはStorageKeyProviderを無条件に要求するため、ProviderなしのStable providers=[] ApplicationをHTTP／Workerへ進めないでください。

**Opt-in Candidate-Skeleton Lane**は下表の差分を確認してから、必要なApplication-owned境界だけを手動で取り込みます。Featureを使わないApplicationへProviderやConfigを一括追加しません。

| Application-owned boundary | Stable `1.1.0`を保持／Candidateから手動で追加する作業 |
| --- | --- |
| Entrypoint／HTTP | `blackops`、`public/index.php`、`public/worker.php`、`Caddyfile`、Classic fallbackをApplicationへ配置し、`SapiRuntime::run()`／`runWorker()`とProcess ManagerのEnvironmentを確認する。1.1.0→candidateでこれらに差分がない場合は保持し、Deploymentが必要な場合だけ更新する。 |
| Bootstrap／Config | Candidate Capabilityを使う場合だけ`bootstrap/app.php`へ`withEnvironmentFile()`／`withConfiguration()`を追加し、関連する`config/app.php`、`database.php`、`execution.php`、`journal.php`、`operations.php`、`retention.php`をApplicationの直接Importに合わせて更新する。 |
| Providers／Security | 採用するFeatureに対応するHTTP Authenticator、Console／Scheduled Actor・Tenant Provider、`StorageKeyProvider`、Status／Data Authorizer、Telemetry ProviderだけをService Providerへ明示Bindingする。Credential／Key MaterialはArtifact、Manifest、Logへ書かない。 |
| Generated／Application Source | 既存Operation、Migration、Frontend Sourceは保持し、`build:compile`、`frontend:generate`、`frontend:check`だけでGenerated Artifactを再生成する。 |

Compatibility-first Laneの最小Config追加（Application Rootの`config/app.php`）は次のとおりです。既存の`operation_manifest`／`http_manifest`は保持し、`command_manifest`はCandidateのoptional fallbackに任せます。

```php
'build' => [
    // existing operation_manifest and http_manifest entries remain unchanged
    'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php',
],
```

この追加後の期待結果は`Build artifacts written.`、`operation:list`の既存`welcome.show`表示です。`frontend_manifest`が空または相対Pathの場合は`Application configuration key "app.build.frontend_manifest" must be a non-empty absolute path.`で停止するため、Source Hash確認後にだけ追加します。

Candidate HTTP／Worker Runtimeへ進むOpt-in Laneでは、Frameworkの[Key Provider登録例](https://github.com/kubotak-is/blackops/blob/main/docs/guide/tenant-protection%2Emd#2-key-providerをapplicationへ登録する)をApplication-owned Service Providerへ最小構成で追加します。Runtime Composerは`StorageKeyProvider`を必須解決するため、次のBindingだけを省略してはいけません。

`app/ApplicationServiceProvider.php`へ次の完全なApplication-owned Providerを配置します。

```php
<?php

declare(strict_types=1);

namespace App;

use App\Security\SampleStorageKeyProvider;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\StorageProtection\StorageKeyProvider;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(StorageKeyProvider::class, SampleStorageKeyProvider::class);
    }
}
```

`ApplicationServiceProvider`を`config/app.php`の既存Application Configurationへ登録し、Stable Configにない`services`配列だけを追加します。

```php
'services' => [
    App\ApplicationServiceProvider::class,
],
```

`SampleStorageKeyProvider`の完全なApplication-owned実装は[QuickstartのProvider Source](https://github.com/kubotak-is/blackops/blob/main/examples/quickstart/app/Security/SampleStorageKeyProvider%2Ephp)を`app/Security/SampleStorageKeyProvider.php`へ配置します。ProductionではこのClassをSecret Manager／KMS Adapterへ置き換え、Provider ClassとBindingだけをArtifactへ含めます。

Local検証ではQuickstartの`SampleStorageKeyProvider`を使い、Secret値を出力・記録せずにDisposable checkoutのuntracked `.env`へ注入します。ComposeはHost shellのExportを自動転送しないため、次のように`.env`を作成して検証後に削除します。

```bash
set -euo pipefail
test ! -e .env
umask 077
cleanup() { rm -f .env; docker compose down >/dev/null 2>&1 || true; }
trap cleanup EXIT
cp .env.example .env
storage_key="$(openssl rand -base64 32)"
grep -v '^BLACKOPS_STORAGE_KEY=' .env.example > .env
printf 'BLACKOPS_STORAGE_KEY=%s\n' "$storage_key" >> .env
unset storage_key
test "$(grep -c '^BLACKOPS_STORAGE_KEY=' .env)" -eq 1
```

上の`set -euo pipefail`、`.env`作成、Step 4〜7の全Commandは、同じDisposable Application RootのShellで順に実行します。Step 3のCode Blockだけを別Shellで終了すると`EXIT` trapが直ちに`.env`を削除するため、後続Stepへ進む前にShellを終了／分割しないでください。最後のStep 7または途中Failureでのみtrap cleanup（`.env`削除後にCompose停止）が走ります。

ProductionではSecret Manager／KMS Adapterへ置き換え、値をGuide、Git、`.env` Snapshot、Manifest、Artifact、Logへ保存しません。DatabaseはBackup後にFramework Migrationを先に適用し、既存Protected／Plaintext Schemaを自動変換しません。Local disposable DBを作り直す場合だけ、対象Volumeを確認して破棄・再作成し、ProductionではRestore／Forward Fix境界を選びます。Provider未登録時の期待結果は安全な`Storage protection provider is required for application bootstrap.`であり、Key／Payloadは出力しません。

`EphemeralOutcome`を使うCredential ResponseはHTTP Response一回だけ返し、Journal／Outcome／Status／Generated Artifactへ保存しません。Transactional Serviceを新たに利用する場合だけCandidate Framework Proxy Profileを有効化し、Stable `1.1.0`とCandidateのComposerにRay package removalは存在しないためRay削除Migrationは行いません。

`vlucas/phpdotenv`、PSR-7／PSR-17、SAPI、UUIDv7をApplicationが直接Importしていない場合だけ、重複するDirect Dependencyを削除します。Stable Quickstartから移行する場合は`vlucas/phpdotenv`と`nyholm/psr7`がFramework Runtimeへ整理され、`nyholm/psr7-server`と`laminas/laminas-httphandlerrunner`はCandidate QuickstartがImportしないため削除されることを確認します。DBAL／Migrations、Frontend、外部PSR AdapterなどApplication Sourceが実ImportするPackageはApplication側へ残します。変更後に次を実行します。

```bash
composer update blackops/framework --with-all-dependencies
composer validate --strict
```

### 4. Configuration、Security、Actor／Tenant境界を手動で構成する

Named DBAL Connection、HTTP Middleware、Authentication／Authorization、Console Actor／Tenant Provider、Session、Diagnostics、Logging、Frontend、Scheduler、Outbox、Storage Protection、ObservabilityのConfigをApplicationの責務として確認します。Credential、Session Token、Actor／Tenant ID、Trace／Metric PayloadはConfig／Manifest／Logへ保存しません。

Protected Storageを利用するApplicationは、`StorageKeyProvider`、Tenant Provider、Purpose、Canonical Associated Data、Key Versionを先に構成し、旧Plaintext／旧Protected Schemaを自動変換しないことを確認します。Key未準備のままMigrationやRotationを実行しないでください。

### 5. Database MigrationをBackup後に順序実行する

Framework Migrationを先に実行し、続けてApplication Migrationを同じDeployment手順で実行します。Candidateには9つのFramework PostgreSQL MigrationがStable `1.1.0`後に追加されています。特に`Version20260808000000.php`と`Version20260808010000.php`は非空Table／既存Plaintext／旧Protected Schemaを拒否し、不可逆なProtected Storage Column／Constraintを導入します。

```bash
docker compose build app http
docker compose up -d postgres
docker compose run --rm app php blackops database:status
docker compose run --rm app php blackops database:migrate --dry-run
docker compose run --rm app php blackops database:migrate
docker compose run --rm app php blackops database:status
```

`database:migrate`のMigration guardが確認するのはTable／Schemaの状態と非空Legacy Dataです。Key Material、Tenant／Purpose Binding、Application PolicyのPreflightはDatabase Commandの責務ではなく、`StorageKeyProvider`／Data AuthorizerのApplication-owned検査としてMigration前に実行します。Migrationが非空Tableまたは旧Schemaで停止した場合は理由を解消して再実行し、Provider／PolicyのErrorはApplicationの安全なConfiguration／Authorization Errorとして扱います。手動で列やConstraintを追加してMigration履歴を偽装しないでください。

Candidateで追加される9つのFramework MigrationとDowngrade境界は次のとおりです。

| Migration | Downgrade boundary |
| --- | --- |
| `Version20260724000000.php` | 明示的に不可逆。Backup RestoreでのみRollbackする。 |
| `Version20260724010000.php` | `down()`を持つがData loss／再構成を伴うため、BackupなしのDowngradeを保証しない。 |
| `Version20260724100000.php` | `down()`を持つがData loss／再構成を伴うため、BackupなしのDowngradeを保証しない。 |
| `Version20260724110000.php` | `down()`を持つがData loss／再構成を伴うため、BackupなしのDowngradeを保証しない。 |
| `Version20260728133000.php` | `down()`を持つがData loss／再構成を伴うため、BackupなしのDowngradeを保証しない。 |
| `Version20260803000000.php` | 明示的に不可逆。Backup RestoreでのみRollbackする。 |
| `Version20260808000000.php` | 非空Table／既存Plaintextをfail-closedに拒否し、明示的に不可逆。 |
| `Version20260808010000.php` | 旧Protected Schemaをfail-closedに拒否し、明示的に不可逆。 |
| `Version20260808100000.php` | 明示的に不可逆。Backup RestoreでのみRollbackする。 |

`CanonicalJournalReader`／`OutcomeReader`を直接呼ぶ旧PublicApi利用は、認可済みのDefault-deny OperationData Queryへ移行します。Readerの型／MethodはInfrastructure SPIとしてPublicApi aggregate StoreのAdapter内に残りますが、Application end-userへ直接公開しません。JSONLを保存／転送するApplicationは現行の`kind`、`schemaVersion`、`attempt`、`telemetry` Envelopeを受け入れるよう確認し、Tenant／Actor／PurposeとStorage Keyを明示してください。

### 6. Build、Frontend、Generated Artifactを再生成する

Framework Update後にBuild Artifactを再生成します。生成済みApplication Sourceは変更されず、Generated Manifest／DI Container／Frontend TreeだけがCandidate Frameworkから更新されます。

```bash
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops operation:list
docker compose run --rm app php blackops frontend:generate
docker compose run --rm app php blackops frontend:check
```

`var/build/`、`resources/js/blackops/`、Manifest、Proxy Profile ArtifactをReviewし、Application-owned SourceのUnexpected Diffがあれば停止してRollback境界を確認します。Framework Proxy ProfileはBuild ID／Content Hash／Profile Unitを検証し、古いArtifactをRuntimeへ混在させません。

RayはStable `1.1.0`にもCandidateにもComposer packageとして存在しないため、既存ApplicationからRayを削除するUpgrade手順はありません。`#[Transactional]`／`#[AfterCommit]`を新規採用するApplicationだけがFramework-owned Proxy ProfileとSignature／DI制約を検証します。

### 7. Runtime、Worker、Observabilityを検証する

Classic／Worker Runtime、HTTP Middleware、Inline／Deferred、Transaction／AfterCommit、Status／Outcome、Scheduler、Outbox／Replay、Session／Tenant再認可、Storage Protection、Structured JSONL、OpenTelemetry Provider Failure isolationを検証します。ApplicationがSDK／Exporter／Collector／Credential／Health Route／CLIを明示構成し、FrameworkはNo-op可能なAPI-only境界だけを提供します。

```bash
docker compose up -d http
docker compose --profile worker up -d worker
docker compose run --rm app php blackops operation:list
worker_ready=0
for attempt in 1 2 3 4 5; do
    if docker compose ps --status running --services | grep -Fxq worker; then
        worker_ready=1
        break
    fi
    sleep 2
done
test "${worker_ready}" -eq 1
response_headers="$(mktemp)"
response_body="$(mktemp)"
probe_cleanup() { rm -f "${response_headers}" "${response_body}"; }
trap 'probe_cleanup; cleanup' EXIT
http_ready=0
for attempt in 1 2 3 4 5; do
    if curl -fsS -D "${response_headers}" -o "${response_body}" http://127.0.0.1:8080/welcome; then
        http_ready=1
        break
    fi
    sleep 2
done
test "${http_ready}" -eq 1
grep -Eiq '^HTTP/[^[:space:]]+[[:space:]]+200([[:space:]]|$)' "${response_headers}"
grep -Eiq '^content-type:[[:space:]]*application/json([;[:space:]]|$)' "${response_headers}"
test "$(<"${response_body}")" = '{"message":"Welcome to BlackOps"}'
docker compose down >/dev/null 2>&1 || true
```

このProbeはStable Applicationの`/welcome`がHTTP `200`、`application/json`、exact body `{"message":"Welcome to BlackOps"}`を返し、Worker Serviceが`running`であることだけを確認します。実OperationのProvider-present Positive／Provider-missing safe Negativeは、同じCandidate SHAのP22-003 Full Gateで実行します。

ConsumerでStable Tag `1.1.0`のApplicationへLocal Framework `1.2.0`を更新する場合は、`tests/Consumer/framework-update-generators.sh`を実行します。このConsumerは実際のannotated TagからStable Sourceを作成し、Application-owned FileのHash不変、Framework Lock `1.2.0`、Generator／Migration／Seederの旧／新出力境界、Update後の`build:compile`／`operation:list`を検証します。Consumerの一時Applicationはcleanup後に再利用せず、HTTP／Worker Runtime Probeは新しいApplication CheckoutへManual Merge MatrixとStorageKeyProvider準備を適用してから実行します。P22-003 fixed-SHA Full Gateは両lane共通のDatabase migration/setup（DDL guard evidence）を先に行い、Provider-presentのHTTP／Worker PositiveとProvider-missingのHTTP／Worker safe Negativeを同じCandidate SHAで実行します。

### 8. Rollbackと公開境界

Candidate検証に失敗した場合はProcessを停止し、同一Backup組からDatabase／Source／Generated Artifactを復元してから旧Frameworkを再解決します。`1.1.0` Tagを移動、削除、上書きせず、`1.2.0`をLatest Stableとして表示しないでください。このPreviewではTag／Push／Release／Packagist／Skeleton Publication／Deployを行わず、完全なFull GateとPublicationは後続のRelease Gateで実行します。P22-003 fixed-SHAの入力は、両lane共通のDatabase migration/setup（DDL guard evidence）、Provider-presentのHTTP／Worker Positive、Provider-missingのHTTP／Worker safe Negativeを同じCandidate SHAで実行することです。
