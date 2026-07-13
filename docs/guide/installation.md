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

Stable `1.0.0`にはFramework／SkeletonのInline、Deferred、Worker、Outcome、Retentionが含まれます。`main` Documentには、まだStable Releaseへ含まれていない`make:operation`、`make:migration`、Application Migration Runtimeの説明も含まれます。Stableで利用可能かは[Current Status](mvp-status.md)で確認してください。

次は[Directory Structure](directory-structure.md)でInstall直後の配置を確認します。
