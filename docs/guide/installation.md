# インストール

BlackOps Applicationは、Packagistで公開済みのSkeletonからComposer標準の`create-project`で作成します。PHP 8.5、Composer、Docker Composeを利用できる環境を用意してください。

## Stable 1.0.0を作成する

Versionを明示してApplicationを作成します。

```bash
composer create-project blackops/skeleton my-app 1.0.0
cd my-app
```

Composer ScriptはProject所有の`bin/setup`を実行します。`.env`が存在しない場合だけ`.env.example`をCopyし、`var/build/`と`var/log/`を準備します。既存の`.env`は上書きしません。

SetupはDocker、Database、Migration、Artifact Build、Worker、Scheduler、Retentionを起動しません。Applicationの状態を変える処理は、以降の明示Commandで実行します。

## Composer Scriptを使わない場合

Script実行を禁止する環境では、同じSetupを手動で実行します。

```bash
composer create-project --no-scripts blackops/skeleton my-app 1.0.0
php my-app/bin/setup
cd my-app
```

`bin/setup`は再実行可能です。既存`.env`を保持したまま不足するLocal Directoryだけを確認できます。

## main Documentとの違い

Stable `1.0.0`にはFramework／SkeletonのInline、Deferred、CLI Worker、Outcome、Retentionが含まれます。StableのCLI Entrypointは`bin` Directory内です。`main` DocumentはProject Rootの`blackops`へ統一し、まだReleaseしていないGenerator、Application Migration Runtime、7 Validation Attribute、FrankenPHP Worker Modeも説明します。Stableで利用可能かは[Current Status](mvp-status.md)で確認してください。

このWebsiteの[Quickstart](mvp-sample.md)はProject Root Entrypointを含む`main` Channelを明示Installします。Stable `1.0.0`を固定した手順と混ぜないでください。

次は[Quickstart](mvp-sample.md)でInline Request、Deferred受付、Worker実行を一続きで確認します。
