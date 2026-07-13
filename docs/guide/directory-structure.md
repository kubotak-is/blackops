# Directory Structure

公式Skeletonは、同じ変更理由を持つOperation、Value、Outcome、ResponderをFeatureとActionの単位でまとめます。

```text
app/
  Feature/
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
bin/
  blackops
bootstrap/
  app.php
config/
  app.php
  database.php
  execution.php
  journal.php
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

`app/Feature/<Feature>/<Action>/`がUse Caseの単位です。WelcomeやReportはDirectoryごと削除でき、Provider一覧やBootstrapを編集する必要はありません。`config/operations.php`のDiscovery Rootへ追加したOperationは次回Buildで検出されます。

Application固有のRepository、External Service、Clock等は任意の`app/Infrastructure/`へ配置できます。Application Migrationが必要になった場合だけ`migrations/`を作成します。空Directoryとして配布する必要はありません。

## Process Boundary

- `bootstrap/app.php`: Public `Application` Builderから共有Application Objectを作る
- `public/index.php`: PSR-7 RequestをPSR-15 HTTP Handlerへ渡す
- `bin/blackops`: FrameworkとApplicationのConsole Commandを起動する
- `config/*.php`: 解決済みEnvironment、Build Artifact、Database、Execution Policyを渡す

Application ObjectやDI Containerを業務CodeのService Locatorとして使いません。OperationのDependencyはConstructor Injectionし、Interface Bindingが必要な場合だけService Providerへ登録します。

## Generated State

Compile済みManifestとContainerは`var/build/`、Local Journal等は`var/log/`へ出力します。`.env`、`vendor/`、`var/build/`、`var/log/`はVersion管理しません。Production RuntimeはArtifact不足やBuild ID不一致時にSource DiscoveryへFallbackせず失敗します。

次は[最初のOperation](first-operation.md)でWelcome Featureの標準Authoringを確認します。
