# Directory構成

公式Skeletonは、Applicationが実行したい一つの意図である[Operation](glossary.md#operation)と、Value、Outcome、ResponderをFeatureとActionの単位でまとめます。

```text
app/
  ApplicationServiceProvider.php
  Security/
    SampleOperationStatusAuthorizer.php
  Feature/
    Diagnostics/
      TriggerFailure/
        TriggerFailure.php
        TriggerFailureValue.php
        FailureTriggered.php
    Order/
      CreateOrder/
        CreateOrder.php
        CreateOrderCommand.php
        CreateOrderValue.php
        OrderCreated.php
      DoctrineOrderRepository.php
      OrderRepository.php
      RecordOrderCommit.php
    Welcome/
      ShowWelcome/
        ShowWelcome.php
        WelcomeValue.php
        WelcomeShown.php
    Report/
      GenerateReport/
        GenerateReport.php
        GenerateReportValue.php
        ReportGenerated.php
migrations/
  Version20260718000000.php
blackops
bootstrap/
  app.php
config/
  app.php
  database.php
  execution.php
  frontend.php
  journal.php
  logging.php
  diagnostics.php
  operations.php
  retention.php
public/
  index.php
resources/
  js/
    application/
      operations.ts
tests/
  Frontend/
    clean.mjs
    real-http.ts
    typecheck.ts
    wait-signal.ts
    write-runtime-package.mjs
var/
  build/
  log/
.env.example
compose.yaml
composer.json
package.json
pnpm-lock.yaml
tsconfig.json
tsconfig.runtime.json
```

## Feature-first Source

`app/Feature/<Feature>/<Action>/`がUse Caseの単位です。WelcomeやReportはDirectoryごと削除でき、Provider一覧やBootstrapを編集する必要はありません。DiagnosticsのTriggerFailureはOperation IDから失敗を追跡するLocal Exampleで、Production Featureとして必要なければDirectoryごと削除できます。`config/operations.php`のDiscovery Rootへ追加したOperationと、`config/app.php`の`command_discovery` Rootへ追加した`#[AsCommand]`は次回Buildで検出されます。

Application固有のRepository、External Service、Clock等はFeatureの内部または任意の`app/Infrastructure/`へ配置できます。Install直後のSkeletonはDatabase／Transaction例としてOrder Featureと`migrations/Version20260718000000.php`を含みます。追加Migrationは`php blackops make:migration Description`で生成してください。

## Process Boundary

- `bootstrap/app.php`: Public `Application` Builderから共有Application Objectを作る
- `public/index.php`: PSR-7 RequestをPSR-15 HTTP Handlerへ渡す
- `blackops`: Project RootからFrameworkとApplicationのConsole Commandを起動する
- `config/*.php`: 解決済みEnvironment、Build Artifact、Database、Execution Policyを渡す

Application ObjectやDI Containerを業務CodeのService Locatorとして使いません。OperationのDependencyはConstructor Injectionし、Interface Bindingが必要な場合だけService Providerへ登録します。

## Generated State

Compile済みOperation／HTTP／Frontend／Command ManifestとContainerは`var/build/`、Local Journal等は`var/log/`へ出力します。Frontendは`php blackops frontend:generate`後に`resources/js/blackops/`、TypeScript Runtime Test後に`.build/`を作ります。

Install直後に配布するApplication-owned Sourceは`config/frontend.php`、`package.json`、`pnpm-lock.yaml`、`tsconfig*.json`、`resources/js/application/`、`tests/Frontend/`です。Generate後の`resources/js/blackops/`、`node_modules/`、`.build/`は配布物へ固定しません。Quickstartの`.gitignore`とCI Tracking Guardがこれらを除外します。

`app/Security/SampleOperationStatusAuthorizer.php`と`ApplicationServiceProvider.php`のBindingはApplication所有です。Framework Updateは上書きしません。SampleはTenant／Production Policyではないため、実ApplicationのActor／Resource関係へ置き換えてください。

各Generated Operationは`.fetch()`／`.status()`／`.wait()`を持ちます。`tests/Frontend/wait-signal.ts`はDOM型を持たないTest専用Helperです。Browser Applicationではnative `AbortController`を使います。

`.env`、`vendor/`、`node_modules/`、`var/build/`、`var/log/`、Generated Frontend Tree、Frontend EmitはVersion管理しません。Production RuntimeはBackend Artifact不足やBuild ID不一致時にSource DiscoveryへFallbackせず失敗します。Production HTTP／WorkerはFrontend ContractやGenerated TypeScriptを読みません。

次は[Local Runtime](runtime-bootstrap.md)でBuild Artifact、Migration、HTTP Worker、Deferred Workerの起動境界を確認します。
