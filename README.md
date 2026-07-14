# BlackOps

[![CI](https://github.com/kubotak-is/blackops/actions/workflows/ci.yml/badge.svg)](https://github.com/kubotak-is/blackops/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/blackops/framework.svg)](https://packagist.org/packages/blackops/framework)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.5-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、Project CLIを提供します。

## Status

MVP、Phase 9 Project CLI、Phase 10 Documentation WebsiteのRepository内実装はMain Branchで完成しています。ただし、MVP CompleteとProduction Readyは同じ意味ではありません。

Packagistで公開済みのFramework／Skeleton Stable `1.0.0`はPhase 8時点のPackageです。`make:operation`、`make:migration`、Application Migration Runtimeを含むPhase 9の変更はMain Branchへ実装済みですが、これらを含む新しいStable Releaseはまだ公開していません。

Documentation WebsiteはLocal／CI Buildと公開Artifact境界まで検証済みです。Cloudflare Pages ProjectとGitHub Environment Credentialの外部設定、Preview／Production Deploy、Live Verificationは未完了であり、公開Hostはまだ利用できません。

利用者向けの機能差と制約は[Current Status](docs/guide/mvp-status.md)を参照してください。

## Installation

公開済みSkeletonからApplicationを作成します。

```bash
composer create-project blackops/skeleton my-app
cd my-app
```

`blackops new`専用Installerは提供していません。Composer標準の`create-project`が公式の作成方法です。

## Quickstart

生成したApplication Directoryで、Docker Image、Artifact、Databaseを明示的に準備します。

```bash
docker compose build app http
docker compose run --rm app composer install
docker compose run --rm app php bin/blackops blackops:operation:list
docker compose run --rm app php bin/blackops blackops:build:compile
docker compose run --rm app php bin/blackops blackops:database:migrate
docker compose up -d
```

確認用Endpoint:

```bash
curl -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome

curl -X POST -H 'Content-Type: application/json' \
  -d '{"reportName":"weekly","apiToken":"local-example"}' \
  http://127.0.0.1:8080/reports
```

`main` Channelの未Release機能も含む詳しい手順は[利用者向けQuickstart](docs/guide/mvp-sample.md)を参照してください。Stable `1.0.0`のCommandと混在させないでください。

## Project CLI on Main

Phase 9を含むMain Branchでは、Project Rootに置く薄い`blackops` EntrypointからFramework所有Generatorを利用できます。

```bash
php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create
php blackops make:migration CreateOrdersTable
php blackops blackops:build:compile
php blackops blackops:database:migrate --dry-run
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
