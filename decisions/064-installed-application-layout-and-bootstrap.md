# D064: Installed Application Layout and Bootstrap

Status: Awaiting Answer

## Context

P7-001の監査により、現在の `examples/mvp/` はOperation実装のSampleとしては有効だが、独立Composer Project、Process Entrypoint、Environment／Config、Build Output、Local Runtimeを持たないことが確認された。

また、MVP E2EのApplication Bootstrapは24種類の `BlackOps\Internal` 型へ直接依存している。`examples/quickstart/` を実際のInstalled Applicationかつ `blackops/skeleton` のSource of Truthにするには、Directory LayoutとPublic Bootstrap境界を先に決定する必要がある。

確定済みの前提は次のとおり。

- Application作成は `composer create-project blackops/skeleton my-app` を使う
- ExampleとSkeletonは `examples/quickstart/` を共通Sourceとする
- Projectは薄い `bin/blackops` を所有する
- Command実装とGenerator StubはFramework Packageが所有する
- Application CodeとBootstrapは `BlackOps\Internal` を参照しない
- HTTP、Console、Worker、Build、Migration、Retention、Schedulerは同じApplication Configurationを使う
- Documentation Websiteは後続Phaseで構築する

## Question 1: Recommended Application Directory Layout

Installed Applicationの公式推奨Directory Layoutをどの形にするか。

### Option A: Layer and Entry Point Aware

```text
app/
  Application/
    Operation/
      Internal/
  Infrastructure/
    BlackOps/
  UserInterface/
    Http/
      Operation/
bootstrap/
  app.php
config/
  app.php
  execution.php
  journal.php
  operations.php
public/
  index.php
bin/
  blackops
migrations/
tests/
var/
  build/
  log/
```

HTTPから開始するOperationは `app/UserInterface/Http/Operation/`、内部処理は `app/Application/Operation/Internal/` に置く。Infrastructure AdapterとApplication Providerは `app/Infrastructure/` に置く。

### Option B: Flat Operation First

```text
app/
  Operation/
  Handler/
  Infrastructure/
bootstrap/
config/
public/
bin/
migrations/
tests/
var/
```

入口種別やFeatureの違いをDirectoryで表現せず、全Operationを一箇所へ置く。

### Option C: Feature First

```text
app/
  Feature/
    Welcome/
    Report/
  Infrastructure/
bootstrap/
config/
public/
bin/
migrations/
tests/
var/
```

各Feature配下にHTTP、Operation、Handler、Outcomeをまとめる。

### Recommendation

Aを推奨する。

既存のProject Structure仕様と一致し、HTTP入口のOperationとApplication内部のOperationを区別できる。Operation関連FileはAction Directoryへまとめるため、Layer分割によってDefinition、Value、Handler、Outcomeが離れることもない。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 2: Public Bootstrap Shape

`bootstrap/app.php` がApplicationを構成するPublic APIをどの形にするか。

### Option A: Application Builder and Shared Application Object

```php
use BlackOps\Application\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withEnvironment()
    ->withConfiguration()
    ->withOperations()
    ->withServices()
    ->create();
```

`bootstrap/app.php` は共有Application Objectを返す。`public/index.php` と `bin/blackops` は同じObjectからHTTP HandlerまたはConsole Applicationを取得する。低LevelのFactoryとInternal Runtime ClassはFramework内に隠す。

### Option B: Process-specific Public Factories

```php
return [
    'http' => HttpApplicationFactory::create(...),
    'console' => ConsoleApplicationFactory::create(...),
    'worker' => WorkerApplicationFactory::create(...),
];
```

HTTP、Console、Workerごとに別のPublic FactoryをApplicationが呼ぶ。

### Option C: Configuration Arrays and Manual Composition

ApplicationはConfig Arrayを読み、Frameworkの公開Factoryを個別に呼んで各Runtimeを手動構成する。

### Recommendation

Aを推奨する。

Application Configurationを一度だけ定義し、HTTP／Console／Worker間のDB Schema、Provider、Artifact PathのDriftを防げる。Framework Update時に低Level CompositionをFramework側だけで更新でき、Skeleton側のBootstrapを薄く保てる。

`Application` ObjectはService Locatorとして業務Codeから利用するものではなく、Process EntrypointのComposition Rootに限定する。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 3: Starter Feature Content

`composer create-project` 直後のApplicationにどこまで動作例を含めるか。

### Option A: Inline Welcome and Deferred Report

- `GET /welcome`: Inline Operation
- `POST /reports`: Deferred Operation
- Sensitive InputのMask例
- Retry後に成功するWorker例
- Migration／Build／Retention Commandの実行導線

### Option B: Inline Welcome Only

- `GET /welcome`: Inline Operation
- Deferred／WorkerはDocumentationだけで説明する

### Option C: Empty Application

- ProviderとConfigだけを生成する
- 最初のOperationは `make:operation` で利用者が作る

### Recommendation

Aを推奨する。

ExampleとSkeletonを一つにするD063の決定上、実際に動くInline／Deferred／Workerの両方が最初から存在する方がFrameworkの価値とProcess構成を検証しやすい。Sample Featureは一つのDirectory単位で削除可能にし、Production Applicationへの残留を強制しない。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 4: Local Runtime Included in Skeleton

Install直後に利用できるLocal RuntimeをどこまでSkeletonへ含めるか。

### Option A: Complete Docker Compose Quickstart

- PHP 8.5 CLI／Composer用Application Service
- FrankenPHP HTTP Service
- PostgreSQL Service
- Health Check
- Local Defaultを持つ `.env.example`
- HTTP起動、Build、Migration、Worker実行を同じCompose Projectで提供

WorkerとMaintenance Schedulerは必要時にCLIまたはProfileで起動し、Defaultの `docker compose up` で意図せずBackground処理を開始しない。

### Option B: Bare PHP Project Only

Composer FilesとApplication Sourceだけを含め、PHP、PostgreSQL、Web Serverは利用者が用意する。

### Option C: Runtime Files as Separate Example

Skeleton本体はBare PHPとし、Docker Compose／FrankenPHPは別Directoryまたは別PackageからCopyする。

### Recommendation

Aを推奨する。

PHP 8.5、PostgreSQL、FrankenPHPというMVP RuntimeをHost環境へ直接要求せず、Install後の同じCommandをCIでも利用できる。Default起動範囲をHTTPとDatabaseに限定すれば、WorkerやPurgeの意図しない実行も避けられる。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Question 5: Environment Loading Ownership

`.env` とProcess Environmentの読み込みをどこが所有するか。

### Option A: Skeleton-owned Dotenv Bootstrap

SkeletonがDotenv Packageを直接Requireし、`bootstrap/app.php` の先頭でLocal `.env` を読み込む。実Process Environmentを優先し、`.env` はLocal Development用、`.env.example` だけをVersion管理する。FrameworkはDotenv実装へ依存せず、解決済みのEnvironment値を受け取る。

### Option B: Framework-owned Dotenv Loading

Frameworkの `Application::configure()` が `.env` を探索して読み込む。

### Option C: Process Environment Only

Dotenv Dependencyを持たず、Docker Compose、Shell、Deployment Platformから注入されたEnvironmentだけを使う。

### Recommendation

Aを推奨する。

Environment SourceはApplication／Deploymentの責務に保ち、Frameworkを特定Dotenv実装へ固定しない。SkeletonではLocal起動の利便性を提供しつつ、Productionでは実Environmentをそのまま優先できる。

[ANSWER]

<!-- A / B / C -->

[/ANSWER]

## Proposed Installed Tree

全推奨案を選んだ場合、`composer create-project` 直後の概略は次のとおりになる。

```text
my-app/
  app/
    Application/Operation/Internal/
    Infrastructure/BlackOps/
    UserInterface/Http/Operation/
      Report/GenerateReport/
      Welcome/ShowWelcome/
  bin/blackops
  bootstrap/app.php
  config/
    app.php
    execution.php
    journal.php
    operations.php
  migrations/
  public/index.php
  tests/
  var/
    build/.gitignore
    log/.gitignore
  .env.example
  .gitignore
  compose.yaml
  composer.json
  Dockerfile
  Dockerfile.frankenphp
  README.md
```

具体的なFile名、Config Key、Public Class／Method Signatureは回答後のSpecificationで確定する。

## Decision

[DECISION]

<!-- Answers確定後に記録する -->

[/DECISION]

## Consequences

[CONSEQUENCES]

<!-- Answers確定後に記録する -->

[/CONSEQUENCES]

## References

- [D063 Developer Experience Roadmap](063-developer-experience-roadmap.md)
- [Installed Application Boundary](../spec/42-installed-application-boundary.md)
- [P7-001 Installed Application Composition Audit](../orchestration/reports/P7-001-installed-application-composition-audit.md)
