# P2-007: Execution Scoped Logger Report

Status: Accepted

## Summary

PSR-3 Loggerへ委譲しつつ、Execution Scope ProviderからOperation metadataを自動contextとして付与するInternal Logger decoratorを追加した。

`ExecutionScopedLogger` はPSR-3 `LoggerInterface` として利用でき、scope内ではOperation ID、Type ID、Attempt ID、Correlation ID、Causation ID、Execution Strategyを付与する。User contextは `context` namespaceへ分離し、Sensitive Projection Filterを通してからinner loggerへ渡す。

## Changed Files

- `src/Internal/Logging/ExecutionScopedLogger.php`
- `src/Internal/Execution/ExecutionScopeProvider.php`
- `src/Internal/Execution/InlineDispatcher.php`
- `tests/Internal/Logging/ExecutionScopedLoggerTest.php`
- `docs/internal/execution-scoped-logger.md`
- `docs/internal/README.md`
- `mago.toml`
- `deptrac.yaml`
- `develop/orchestration/tasks/P2-007-execution-scoped-logger.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Logger decoratorはInternal実装とした。Public APIへInternal Scope Providerを露出させないため。
- Scope Providerは `current()` をOperationEnvelopeのまま維持し、Logger用に `currentOperationTypeId()` を追加した。
- Inline DispatcherはHandler実行scope開始時にOperation Type IDを渡す。
- User contextは必ず `context` namespaceへ格納し、framework-owned `operation` fieldを上書きできないようにした。
- Logger contextはSensitive Projection Filterのarray projectionを通す。
- `psr/log` をMago includesとDeptrac Library layerへ追加した。
- Runtime ComposerからLoggerを構成する入口、Monolog-specific integration、OTel propagation、Samplingは後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'ExecutionScopedLoggerTest|ExecutionScopeProviderTest|InlineDispatcherTest'
Result: OK (16 tests, 41 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (303 tests, 701 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 470 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Internal Logger decoratorがPSR-3 LoggerInterfaceとして追加される
- [x] Scope内LogへOperation ID、Type ID、Attempt ID、Correlation ID、Causation ID、Execution Strategyが付与される
- [x] Scope外LogにはOperation metadataを付与しない
- [x] User contextは `context` namespaceへ分離される
- [x] Sensitive keyはUser contextから除外される
- [x] PSR Log型解決とDeptracが成功する
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Runtime ComposerからLoggerを構成する入口は未実装。
- Service ProviderからLoggerを配線する設定は未実装。
- Monolog-specific integrationは未実装。
- OTel propagation、Sampling、JSONL application log encoderは未実装。

## Suggested Next Action

Runtime compositionでExecution Scope ProviderとExecutionScopedLoggerを共有できる入口を追加し、HandlerへPSR-3 Loggerを注入できる配線に進む。
