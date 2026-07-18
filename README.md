# BlackOps

[![CI](https://github.com/kubotak-is/blackops/actions/workflows/ci.yml/badge.svg)](https://github.com/kubotak-is/blackops/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/blackops/framework.svg)](https://packagist.org/packages/blackops/framework)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.5-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、Project CLIを提供します。

Repository `main`ではNamed Doctrine DBAL Connection、Constructor Injection、`#[Transactional]`付きOperation／Service、Nested Required、`#[AfterCommit]`を利用できます。Latest Stable `1.1.0`にはまだ収録されていません。

## Status

Latest StableはFramework／Skeleton `1.1.0`です。BlackOpsはExperimentalであり、1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。破壊的変更と移行手順は[CHANGELOG](CHANGELOG.md)と[Upgrade Guide](UPGRADE.md)で確認してください。

Documentation WebsiteはLocal／CI Buildと公開Artifact境界まで検証済みです。公開は現在延期しているため、Cloudflare Pages ProjectとCredentialは設定せず、公開Hostも提供していません。Credential-gated Workflowは将来の公開経路として維持しています。

利用者向けの機能差と制約は[Current Status](docs/guide/mvp-status.md)を参照してください。

## Installation

公開済みSkeletonからApplicationを作成します。

```bash
composer create-project blackops/skeleton my-app 1.1.0
cd my-app
```

`blackops new`専用Installerは提供していません。Composer標準の`create-project`が公式の作成方法です。

このCommandが作成するのはStable `1.1.0`のApplicationです。StableにはHeader AuthenticationとPhase 13のDatabase／Transaction Journeyが未収録で、`POST /orders`も含まれません。

## Repository main Preview Quickstart

以下はStable `1.1.0`で作成した`my-app`向けではありません。[利用者向けQuickstart](docs/guide/mvp-sample.md)の「Repository main Preview」でFramework SourceとQuickstartをLocal Path Repositoryとして準備してから、生成した`blackops-preview` DirectoryでDocker Image、Artifact、Databaseを明示的に準備します。

```bash
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php blackops operation:list
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops database:migrate
docker compose up -d
```

確認用Endpoint:

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome

curl -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reference":"order-001"}' \
  http://127.0.0.1:8080/orders

curl -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reportName":"weekly","recipientEmail":"reports@example.com"}' \
  http://127.0.0.1:8080/reports

curl -X POST -H 'Content-Type: application/json' \
  -H 'X-Sample-Token: local-example' \
  -d '{"reference":"incident-demo-001","sensitiveNote":"private diagnostic note"}' \
  http://127.0.0.1:8080/failures
```

`/failures`のSafe 500が返すOperation IDは、Docker-only Quickstartでは`docker compose run --rm app php blackops operation:inspect <id> [--json]`へ渡します。PostgreSQLはHostへPublishせず、ViewerはCLI ContainerのLoopback限定なので、このDocker構成からHost BrowserへViewerを公開できません。ViewerはConsumer E2EのようにViewerとHTTP Clientを同じCLI Container／Local Network Namespaceへ置いて検証します。

Browserで`php blackops operation:viewer`を使うには、Application／PHP CLI／PostgreSQL／Browserが同じLocal Network Namespaceから到達可能なNative Runtimeが必要です。Non-loopback Bindへ緩めません。Application／Framework相関Logは`var/log/application.jsonl`で同じIDを使い、Sensitive ValueとActor IDをSafe Projectionします。

Stableと`main` Previewの準備方法、利用可能なEndpointの差は[利用者向けQuickstart](docs/guide/mvp-sample.md)を参照してください。

## Project CLI

Project Rootに置く薄い`blackops` EntrypointからFramework所有Generatorを利用できます。

```bash
php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create
php blackops make:migration CreateOrdersTable
php blackops build:compile
php blackops database:migrate --dry-run
```

Framework UpdateはProject Rootの`blackops`や生成済みSourceを書き換えません。更新後のCommandとStubは、その後に新規生成するFileへ反映されます。

## Development

Frameworkの開発CommandはWSL2内のRepository Rootで、Docker Composeを通して実行します。

```bash
docker compose build app
docker compose run --rm app composer install

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
```

同じ品質Gateは[GitHub Actions CI](.github/workflows/ci.yml)で`main`へのPushとPull Requestごとに実行されます。詳細な環境構築は[Development Setup](docs/internal/development-setup.md)を参照してください。

## Documentation

- [Framework利用者向けDocumentation](docs/guide/README.md)
- [Installation](docs/guide/installation.md)
- [First Typed Self-handled Operation](docs/guide/first-operation.md)
- [Local Runtime](docs/guide/runtime-bootstrap.md)
- [Framework実装者向けInternals](docs/internal/README.md)
- [Documentation Website Delivery](docs/internal/documentation-website.md)
- [Application Bootstrap](docs/guide/application-bootstrap.md)
- [Project Generators and Framework Updates](docs/guide/project-generators.md)
- [Database Migrations](docs/guide/database-migrations.md)
- [Data Retention](docs/guide/retention.md)

## License

[MIT License](LICENSE)
