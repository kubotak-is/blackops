# Directory構成

公式Skeletonは、Applicationが実行したい一つの意図である[Operation](glossary.md#operation)と、Value、Outcome、ResponderをFeatureとActionの単位でまとめます。

```text
app/
  ApplicationServiceProvider.php
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
  journal.php
  logging.php
  diagnostics.php
  operations.php
  retention.php
public/
  index.php
tests/
var/
  build/
  log/
.env.example
compose.yaml
composer.json
```

## Feature-first Source

`app/Feature/<Feature>/<Action>/`がUse Caseの単位です。WelcomeやReportはDirectoryごと削除でき、Provider一覧やBootstrapを編集する必要はありません。DiagnosticsのTriggerFailureはOperation IDから失敗を追跡するLocal Exampleで、Production Featureとして必要なければDirectoryごと削除できます。`config/operations.php`のDiscovery Rootへ追加したOperationは次回Buildで検出されます。

Application固有のRepository、External Service、Clock等はFeatureの内部または任意の`app/Infrastructure/`へ配置できます。Install直後のSkeletonはDatabase／Transaction例としてOrder Featureと`migrations/Version20260718000000.php`を含みます。追加Migrationは`php blackops make:migration Description`で生成してください。

## Process Boundary

- `bootstrap/app.php`: Public `Application` Builderから共有Application Objectを作る
- `public/index.php`: PSR-7 RequestをPSR-15 HTTP Handlerへ渡す
- `blackops`: Project RootからFrameworkとApplicationのConsole Commandを起動する
- `config/*.php`: 解決済みEnvironment、Build Artifact、Database、Execution Policyを渡す

Application ObjectやDI Containerを業務CodeのService Locatorとして使いません。OperationのDependencyはConstructor Injectionし、Interface Bindingが必要な場合だけService Providerへ登録します。

## Generated State

Compile済みManifestとContainerは`var/build/`、Local Journal等は`var/log/`へ出力します。`.env`、`vendor/`、`var/build/`、`var/log/`はVersion管理しません。Production RuntimeはArtifact不足やBuild ID不一致時にSource DiscoveryへFallbackせず失敗します。

次は[Local Runtime](runtime-bootstrap.md)でBuild Artifact、Migration、HTTP Worker、Deferred Workerの起動境界を確認します。
