# Install

BlackOps Applicationは、Packagistで公開済みのSkeletonからComposer標準の`create-project`で作成します。StableのInstall手順はPHP 8.5、Composer、Docker Composeだけで完走します。公開済み`1.2.0` PackageにはAuthentication、Seeder、Frontend Operation BridgeのSourceが含まれますが、Node／pnpm、Seeder実行、Frontend生成はこの最小Install手順では実行しません。

:::info[Latest Experimental Stable 1.2.0]
このページは公開済みStable `1.2.0` SkeletonのInstallを扱います。Experimentalな追加機能とMigration境界は[Quickstart and Skeleton](mvp-sample.md)から確認してください。
:::

## Stable 1.2.0を作成する

Versionを明示してApplicationを作成します。

```bash
composer create-project blackops/skeleton my-app 1.2.0
cd my-app
```

Composer ScriptはProject所有の`bin/setup`を実行します。`.env`が存在しない場合だけ`.env.example`をCopyし、`var/build/`と`var/log/`を準備します。既存の`.env`は上書きしません。

SetupはDocker、Database、Migration、Artifact Build、Worker、Schedulerを起動しません。normal／`--no-scripts`のどちらも、下の共通Key Stepを完了してからRuntime Commandへ進みます。

## Composer Scriptを使わない場合

Script実行を禁止する環境では、同じSetupを手動で実行します。

```bash
composer create-project --no-scripts blackops/skeleton my-app 1.2.0
cd my-app
php bin/setup
```

`bin/setup`は再実行可能です。既存`.env`を保持したまま不足するLocal Directoryだけを確認できます。
Setup後は下のnormal／`--no-scripts`共通Key Stepへ合流します。

公開済み`1.2.0` Quickstart Runtimeには32-byte Base64のLocal Development Key／Local `StorageKeyProvider`が必要です。normal／`--no-scripts`のどちらも、Setup直後に次の同じ必須Key Stepを実行します。Key値はstdoutへ出さず、Gitへ保存せず、ProductionではApplication-owned Secret Manager／KMS Providerへ置き換えます。

```bash
(
    set -euo pipefail
    umask 077
    chmod 600 .env
    test "$(stat -c '%a' .env)" = 600
    storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"
    test -n "${storage_key}"
    decoded_storage_key_length="$(printf '%s' "${storage_key}" | base64 --decode | wc -c)"
    test "${decoded_storage_key_length}" -eq 32
    sed -i "s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|" .env
    test "$(grep -c '^BLACKOPS_STORAGE_KEY=' .env)" -eq 1
    test "$(grep -c '^BLACKOPS_STORAGE_KEY=$' .env)" -eq 0
)
```

共通Key Stepの後、Project RootでApplication ContainerとPostgreSQLを順に準備します。HostのPHP CLIとContainer CLIを混在させず、`php blackops`はすべてContainer内で実行します。

```bash
docker compose build app http
docker compose up -d postgres
docker compose run --rm app php blackops database:migrate
docker compose run --rm app php blackops build:compile
docker compose up -d http
curl -i -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome
docker compose down
```

期待結果はHTTP `200`と次のJSONです。

```json
{"message":"Welcome to BlackOps"}
```

`/welcome`は公開済み`1.2.0`の`#[Authorize]`付きInline Operationです。`SAMPLE_API_TOKEN=local-example`を設定したApplicationへ`X-Sample-Token: local-example`を送るとSample UserとしてAuthenticationされ、Sample Authorization Policyを通過してHTTP `200`を返します。Headerを省略したAnonymous Requestと不正なHeaderは`401`となり、後者はOperation受付前に拒否されます。Responseを確認したら`docker compose down`で停止します。MigrationとBuildはHTTP起動へ暗黙に含まれません。

## Release Policy

Stable `1.2.0`にはProject Root `blackops`、Generator、Application Migration Runtime、7 Validation Attribute、FrankenPHP Worker Mode、Authentication、Durable ActorContext、`#[Authorize]`、Frontend Operation Bridgeが含まれます。このWebsiteは公開Releaseのドキュメントであり、Experimentalな制約は[Releases](mvp-status.md)で明示します。

BlackOpsはExperimentalです。1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。Upgrade前にRelease NoteとUpgrade Guideを確認し、検証環境でApplicationをTestしてください。[Releases](mvp-status.md)には利用可能な機能と既知の制約をまとめています。

公開済みStable `1.2.0`を使う場合は上記提供範囲に留めてください。[Quickstart and Skeleton](mvp-sample.md)の認証付きJourneyも公開済み`1.2.0` Packageから開始できます。接続や起動で詰まった場合は[Troubleshooting](troubleshooting.md)を参照してください。
