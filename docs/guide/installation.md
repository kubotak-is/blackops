# Install

BlackOps Applicationは、Packagistで公開済みのSkeletonからComposer標準の`create-project`で作成します。Stableの手順はPHP 8.5、Composer、Docker Composeだけで完走します。Node、pnpm、Authentication、Seeder、Frontend生成はStableのInstallに含めません。

:::info[Stable 1.1.0]
このページは公開済みStable SkeletonのInstallを扱います。Repository `main` Previewの追加機能は[Quickstart and Skeleton](mvp-sample.md)から進めてください。
:::

## Stable 1.1.0を作成する

Versionを明示してApplicationを作成します。

```bash
composer create-project blackops/skeleton my-app 1.1.0
cd my-app
```

Composer ScriptはProject所有の`bin/setup`を実行します。`.env`が存在しない場合だけ`.env.example`をCopyし、`var/build/`と`var/log/`を準備します。既存の`.env`は上書きしません。

SetupはDocker、Database、Migration、Artifact Build、Worker、Schedulerを起動しません。Applicationの状態を変える処理は、以降の明示的なコマンドで実行します。

Project Rootで、Application ContainerとPostgreSQLを順に準備します。HostのPHP CLIとContainer CLIを混在させず、`php blackops`はすべてContainer内で実行します。

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

`/welcome`はStableの認可匿名（`#[Authorize]`なし）Inline Operationですが、Stable Tagの`WelcomeValue`は機密扱いの必須`X-Sample-Token` Header ValueをBindingします。このHeaderはAuthenticationではなくOperation Inputなので、Local Example値を付けてHTTP 200を確認します。Responseを確認したら`docker compose down`で停止します。MigrationとBuildはHTTP起動へ暗黙に含まれません。

## Composer Scriptを使わない場合

Script実行を禁止する環境では、同じSetupを手動で実行します。

```bash
composer create-project --no-scripts blackops/skeleton my-app 1.1.0
cd my-app
php bin/setup
```

`bin/setup`は再実行可能です。既存`.env`を保持したまま不足するLocal Directoryだけを確認できます。
Setup後は上記Stable Runtime手順（PostgreSQL起動、Container migration／build、必須Header付きWelcome確認、停止）へそのまま合流します。

## Release Policy

Stable `1.1.0`にはProject Root `blackops`、Generator、Application Migration Runtime、7 Validation Attribute、FrankenPHP Worker Modeが含まれます。このWebsiteはRepository `main`のドキュメントであり、Global Middleware、Authentication、Durable ActorContext、`#[Authorize]`、Frontend Operation Bridge等の未Release Surfaceも扱います。Stableとの差は[Stableとmain](mvp-status.md#stableとmain)で明示します。

BlackOpsはExperimentalです。1.x Minor間のBackward CompatibilityとProduction Readinessを保証しません。Upgrade前にRelease NoteとUpgrade Guideを確認し、検証環境でApplicationをTestしてください。[Releases](mvp-status.md)には利用可能な機能と既知の制約をまとめています。

公開済みStableを使う場合は上記提供範囲に留めてください。[Quickstart and Skeleton](mvp-sample.md)の認証付きJourneyはRepository `main` Previewの再現手順から始まります。接続や起動で詰まった場合は[Troubleshooting](troubleshooting.md)を参照してください。
