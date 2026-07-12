# P8-001 Post-create Initialization Report

## Summary

Skeleton所有のExecutable PHP Entrypoint `bin/setup`を追加し、Composer `post-create-project-cmd`とManual Setupが同じ処理を使うようにした。SetupはProject Rootを自身の位置から解決し、未作成`.env`のbyte-for-byte Copyと`var/build`／`var/log`の冪等準備だけを行う。

既存`.env`は内容、Permission、Timestampを変更しない。Setupは次の明示Commandを表示するが、Composer Install、Network、Docker、Database、Migration、Artifact Build、Worker、Scheduler、Retentionを実行しない。

Orchestrator Reviewを受け、`.env`作成後のwrite／close failureをall-or-cleanupへ修正した。今回の実行で作成したHandleを可能な限り閉じ、作成途中の`.env`を削除してから非0終了するため、次回Setupが部分Fileを既存設定として保持しない。

## First-run and Idempotency Evidence

`tests/Consumer/quickstart-setup.sh`はQuickstartを一時ProjectへCopyし、Project Root外のWorking DirectoryからSetupを直接実行する。`.env.example`と`.env`のbyte比較、生成Directoryの存在と空状態を確認した。

再実行前に利用者固有`.env`を作成し、Content Hash、Permission、Timestampを記録した。再実行後に3値がすべて同一であり、既存`.env`を保持したMessageを確認した。

`var/build`がFileの場合、`.env.example`がない場合、Project Rootへ`.env`を作成できない場合をそれぞれ検証し、対象責務だけを示す安全なMessageと非0終了を確認した。

Environment Copyはread、exclusive create、全byte write、closeを一つの例外境界で扱う。create成功後の例外ではHandle Closeと今回作成したTargetの削除を試み、Targetが残った場合はcleanup failureを明示する。Permissionによるcreate failureでは`.env`不在をTestする。write／close faultの移植可能な注入はSetupへTest Hookを持ち込まず、実装InvariantとしてReview対象にした。

## Composer Script and Manual Setup Evidence

Quickstart Composer Metadataは次を定義する。

```json
{
  "scripts": {
    "post-create-project-cmd": "@php bin/setup"
  }
}
```

Consumer Testは`composer run-script post-create-project-cmd`とDirect `php bin/setup`の両方で同じ`.env`／Directory結果を確認した。READMEとRuntime Guideは`--no-scripts`時に`php my-app/bin/setup`を実行するManual導線を記載する。Remote Create-projectはPackage公開後の機能として明確に分離した。

## Side Effect Boundary Evidence

Direct Setup TestはPHPの`exec`、`shell_exec`、`system`、`passthru`、`proc_open`、`popen`を無効化したProcessで成功する。Setup前後のFile Metadata差分から`.env`以外の既存Fileが変更されず、生成Directory内にFileがないことを確認する。

Setup SourceはComposer AutoloadやFramework Runtimeを読み込まず、Filesystem APIとSTDOUT／STDERRだけを利用する。Docker等のCommand名は次手順として出力する文字列であり、外部Processとして起動しない。

## Changed Files

- `examples/quickstart/bin/setup`
- `examples/quickstart/composer.json`
- `examples/quickstart/README.md`
- `tests/Consumer/quickstart-setup.sh`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `docs/guide/installed-application-status.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/mvp-e2e.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-001-post-create-initialization.md`
- `develop/orchestration/reports/P8-001-post-create-initialization.md`
- `develop/STATE.md`

Framework Production Codeは変更していない。

## Decisions and Assumptions

- SetupはFrameworkやVendor Autoloadへ依存しないStandalone Project Toolとした。
- Composer Hookは`@php`を使い、Composerが実行中のPHP Runtimeと同じRuntimeでSetupを起動する。
- 既存`.env`が存在する場合はFile Typeを問わず非上書きとし、そのPathへ書込を行わない。
- Missing `.env.example`はCopy前提不成立、Project Root Permission拒否は実Copy Failureとして別々に検証した。
- Remote `composer create-project`は未公開のため、P8-001ではComposer Scriptを直接実行し、Local／Remote Create-project SmokeはP8-002以降へ残した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (6 tests, 93 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (647 tests, 2190 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 350 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1489 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-setup.sh
Result: Quickstart setup tests passed. Direct, Composer, idempotency, CWD independence, failures, side-effect boundary succeeded.

! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
Result: No matches.

! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
Result: Both negated checks exited 0.

! rg -n 'Spec(ification)?...|D...|P...|TODO.md:...' src tests examples --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

Review修正後、Focused Architectureは`6 tests / 93 assertions`、Consumer Setup、Mago Format／Lint／Analyze、全Guardが再成功した。Full Suiteは修正後に`647 tests / 2190 assertions`で一度成功した。以降の最終再検証2回では、既存`SignalHeartbeatTest::testSigalrmHeartbeatsDuringSynchronousHandlerAndRestoresSignalState`だけがheartbeat count 0で間欠的に失敗した。Focused Signal Suiteは`7 tests / 15 assertions`で成功した。Setup Codeとの実行経路上の関連はなく、同じFailureの反復実行は停止した。

Orchestrator AcceptanceではComposer Validation、Focused Architecture `6 tests / 93 assertions`、Consumer Setup、Full PHPUnit `647 tests / 2190 assertions` を一度ずつ再実行し、すべて成功した。成功を得るための反復実行は行っていない。

## Acceptance Criteria

Task Packetの10項目をすべて満たした。Composer Metadata、初回Setup、冪等性、CWD非依存、安全なFailure、Side Effect不在、Direct／Composer両経路、Manual Setup文書、全品質Command、管理文書を完了した。

## Remaining Issues

Setup実装のBlockerはない。既存Signal heartbeat timing instabilityはP8-001Aで独立して安定化する。Local Split Artifact、通常／`--no-scripts` Create-project Smoke、Framework Constraint同期、Distribution Publication、Packagist公開は後続TaskのScopeである。

## Suggested Next Action

既存Signal heartbeat timing instabilityをP8-001Aで安定化した後、P8-002 Local Split and Create-project SmokeをTask化する。
