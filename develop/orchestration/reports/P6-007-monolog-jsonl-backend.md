# P6-007: Monolog JSONL Backend Report

Status: Completed

## Summary

- Monolog 3 `Logger`、`StreamHandler`、`JsonFormatter`を構成し、PSR-3 `LoggerInterface`として返す内部`MonologJsonlLoggerFactory`を追加した。
- Default Channelを`blackops`、Default最低Levelを`info`として決定的にした。Channelと最低LevelはScalar入力で上書きできる。
- Stream PathまたはStream ResourceをMonolog `StreamHandler`へそのまま渡し、File初期化、書込み、Level Filterを独自再実装しないようにした。
- `JsonFormatter`をnewline-delimited Batch Mode、Record改行有効で構成し、一Recordを有効な一行JSONとして出力するようにした。
- Factoryの戻り値をPSR-3へ限定し、Monolog固有型は`BlackOps\Internal\Logging`実装内だけに閉じた。
- `ExecutionScopedLogger`でFactoryのBackendを包み、Operation ContextとSensitive Projection後のUser ContextだけがJSONへ出力されることをEnd-to-End Testした。
- PasswordとNested Token Keyおよび値が出力JSONに存在せず、Filter前Contextが別Fieldへ複製されないことを確認した。
- Custom Channel／Level／Message／Context、Default Level Filter、複数Recordの一行性、File Path、Stream Resource、初期化／書込み例外透過をTestした。
- Testが作るFile Pathを追跡し、成功時と例外時の双方で`tearDown()`により一時Fileを削除するようにした。
- Magoの型解決対象とDeptracのLibrary CollectorへMonologを登録し、InternalからThird-party Libraryへの依存として検証できるようにした。
- Framework実装者向けに利用方法、推奨Composition、Sensitive Filter順序、例外境界、拡張範囲をDocumentationへ記録した。

## Changed Files

- `src/Internal/Logging/MonologJsonlLoggerFactory.php`
- `tests/Internal/Logging/MonologJsonlLoggerFactoryTest.php`
- `mago.toml`
- `deptrac.yaml`
- `docs/internal/monolog-jsonl-backend.md`
- `docs/internal/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-007-monolog-jsonl-backend.md`
- `develop/orchestration/reports/P6-007-monolog-jsonl-backend.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Task Packetが当初参照した`develop/spec/14-logging-and-traceability.md`は存在しなかった。`develop/spec/README.md`が示す正本`develop/spec/10-logging-and-traceability.md`を確認し、一意な番号誤記としてTask Packetを訂正してから作業した。
- Factoryは直接Monolog型を構成するが、Public Methodの戻り値は`Psr\Log\LoggerInterface`、入力はPHPで表現可能なScalar／`mixed`とした。Stream入力の正確な`resource|string`契約はPHPDocで表現する。
- Default ChannelはFramework名に合わせて`blackops`、Default最低Levelは通常Application Logの既定として`info`とした。
- `JsonFormatter::BATCH_MODE_NEWLINES`、`appendNewline: true`、`ignoreEmptyContextAndExtra: false`を使用し、Context／Extra Fieldを保持した改行終端JSONLとした。
- Streamの型検査、File Open、Write、Level判定、JSON Encodeの例外をFactoryでCatchしない。元のMonolog例外をComposition境界へ返す。
- Sensitive FilterはMonolog Factoryの責務ではなく、既存`ExecutionScopedLogger`が委譲前に必ず実行する。Factory単体の直接利用はOperation Context付与やUser Context Filterを行わないことをDocumentationへ明記した。
- FactoryのPublic MethodにはMonolog型を使わず、Core／Operation APIを変更していない。Source内のMonolog参照は新しいInternal Factoryだけであることを最終監査した。
- MagoはThird-party Sourceを個別includeしないと型解決できないため、`vendor/monolog/monolog/src/Monolog`を解析用includeへ追加した。Deptracには`Monolog` NamespaceをLibrary Layerとして追加した。
- `mago.toml`と`deptrac.yaml`は当初Task許可外だったため作業を止めてOrchestratorへ返し、Task Packetの許可範囲拡張後に変更した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'MonologJsonl|ExecutionScopedLogger'
Result: OK (10 tests, 60 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (512 tests, 1586 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1140 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

初回Mago Analyzeは、`mago.toml`のThird-party型解決includeにMonologが未登録だったため、`Logger`、`StreamHandler`、`JsonFormatter`を不存在Classとして拒否した。許可範囲拡張後にMonolog Sourceをincludeへ追加した。

include追加後はFactoryのnative `mixed` Stream引数がMonologの`resource|string`より広いという1 Issueが残った。PHPDocへ正確な`resource|string`契約を追加し、実行時検証はMonologへ委譲したまま最終Analyzeを成功させた。

## Acceptance Criteria

- [x] Internal FactoryがMonolog 3のPSR-3 Loggerを構成する
- [x] 一件のLogが改行終端された有効なJSON一行として出力される
- [x] Channel、Level、Message、ContextがJSONへ保持される
- [x] 最低Level未満のRecordが出力されない
- [x] `ExecutionScopedLogger`のOperation Contextが出力される
- [x] Sensitive Keyが出力JSONへ現れない
- [x] Core／公開APIにMonolog型が追加されない
- [x] 利用方法と拡張境界がDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Remaining Issues

- なし。

## Suggested Next Action

- MVP残作業の次Task Packetへ進む。

## Orchestrator Review

- Task Packetの誤ったSpec参照が正本`develop/spec/10-logging-and-traceability.md`へ訂正されていることを確認した。
- Monolog固有型がInternal Factory内だけに閉じ、戻り値がPSR-3 `LoggerInterface`であることを確認した。
- Stream処理、最低Level判定、JSON EncodeをMonologへ委譲していることを確認した。
- JSONLの一行性、Channel、Level、Message、ContextをTestで確認した。
- `ExecutionScopedLogger`がOperation Contextを付与し、Sensitive Keyと値をMonologへ渡さないことをTestで確認した。
- Mago includeとDeptrac Library Collectorの追加がMonolog実装参照に限定されることを確認した。
- Targeted PHPUnitを再実行し、`OK (10 tests, 60 assertions)`を確認した。
- Mago AnalyzeとDeptracを再実行し、問題がないことを確認した。
- Review指摘およびBlockerはない。
