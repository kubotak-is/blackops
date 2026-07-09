# P2-008: Runtime Logging Composition Report

Status: Accepted

## Summary

Production Runtime ComposerでExecution Scope ProviderとJournal Observation Pipelineを共有できる入口を追加した。

既存の `compose()` 呼び出し互換を維持しつつ、拡張依存は `ProductionRuntimeDependencies` と `composeWithDependencies()` へ分離した。HTTP runtime経由のInline dispatchでJSONL Journal ObserverへLifecycle Journalが出力され、Handlerへ注入されたExecutionScopedLoggerが同じExecution Scope ProviderからOperation metadataを読めることを確認した。

## Changed Files

- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Runtime/ProductionRuntimeComposition.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `docs/internals/runtime-container.md`
- `orchestration/tasks/P2-008-runtime-logging-composition.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- 既存の `compose()` は5引数のまま維持した。
- Logging/Observation向けの拡張依存は `ProductionRuntimeDependencies` に集約した。
- `ProductionRuntimeComposition` から実際に使われたExecution Scope Providerを参照できるようにした。
- Runtime Composerはcontainerをhandlerへ渡さない。HandlerへのLogger注入は、application service container側で行う。
- Public runtime bootstrap API、Service Provider自動登録、Monolog-specific integration、file path config、OTel、Samplingは後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter ProductionRuntimeComposerTest
Result: OK (2 tests, 9 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (304 tests, 707 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 474 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Production Runtime Composerへoptional Execution Scope Providerを渡せる
- [x] Production Runtime Composerへoptional Journal Observation Pipelineを渡せる
- [x] ProductionRuntimeCompositionからExecution Scope Providerを参照できる
- [x] HTTP runtime経由でJSONL Journal ObserverへLifecycle Journalが出力される
- [x] Handlerへ注入されたExecutionScopedLoggerがOperation metadataを付与できる
- [x] 既存ProductionRuntimeComposer呼び出し互換を保つ
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Service Provider configでLoggerを自動登録する仕組みは未実装。
- Monolog-specific integrationは未実装。
- JSONL file path config、OTel propagation、Sampling、Public runtime bootstrap APIは未実装。

## Suggested Next Action

Phase 2 closeoutとしてProjection/Loggingの実装・docs・STATEを照合し、必要な最終検証を行う。
