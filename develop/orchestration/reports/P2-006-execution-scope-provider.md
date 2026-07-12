# P2-006: Execution Scope Provider Report

Status: Accepted

## Summary

Operation実行境界のcurrent contextをLogger decoratorから参照できるように、Internal Execution Scope Providerを追加した。

`ExecutionScopeProvider` はOperationEnvelopeをstackとして管理し、nested scope後に親scopeを復元する。callbackが例外を投げた場合も `finally` でscopeを終了する。Inline DispatcherはHandler実行中だけscopeを開始する。

## Changed Files

- `src/Internal/Execution/ExecutionScopeProvider.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `src/Internal/Journal/JournalObservationPipeline.php`
- `tests/Internal/Execution/ExecutionScopeProviderTest.php`
- `tests/Internal/Execution/InlineDispatcherTest.php`
- `docs/internals/execution-context.md`
- `develop/orchestration/tasks/P2-006-execution-scope-provider.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Scope ProviderはInternal serviceとし、Public APIへは露出しない。
- Scopeの保持対象はOperationEnvelopeとした。Logger decoratorがOperation definition、value、ExecutionContext、strategyを必要に応じて参照できるため。
- Inline Dispatcherのconstructor parameter上限を守るため、Observer projectorとaggregatorは `JournalObservationPipeline` にまとめた。
- Scope Providerは現時点では単純なstackであり、Fiber-local分離は後続Taskへ送る。
- PSR-3 Logger decorator、Logger context自動付与、Runtime ComposerからLoggerへの配線は後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'ExecutionScopeProviderTest|InlineDispatcherTest' --display-deprecations
Result: OK (14 tests, 30 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (301 tests, 690 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 467 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Internal Execution Scope Providerが追加される
- [x] scope外ではcurrentがnullである
- [x] scope内ではcurrent OperationEnvelopeを参照できる
- [x] nested scope後に親scopeが復元される
- [x] 例外発生時にもscopeが終了する
- [x] Inline DispatcherのHandler実行中にcurrent OperationEnvelopeを参照できる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- PSR-3 Logger decoratorは未実装。
- Logger context自動付与は未実装。
- Fiber-local scope分離は未実装。
- Runtime ComposerからLoggerへScope Providerを配線する入口は未実装。

## Suggested Next Action

PSR-3 Logger decoratorを追加し、Execution Scope ProviderからOperation metadataを自動contextとして付与する。
