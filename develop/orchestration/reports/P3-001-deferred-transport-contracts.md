# P3-001: Deferred Transport Contracts Report

Status: Accepted

## Summary

Deferred Vertical Sliceの土台として、Deferred Strategy、Durable受付Acknowledgement、Transport Message、Claim、Transport PortのPublic Contractを追加した。

HTTP ProcessがSenderだけへ依存でき、Worker RuntimeがReceiver、Heartbeat、Settlementへ依存できるよう、Transport Portは責務別Interfaceへ分離した。Claim Tokenは不透明Tokenとして扱い、例外Backtrace等への露出を避けるためSensitive Parameterとして扱う。

## Changed Files

- `src/Core/Execution/Deferred.php`
- `src/Core/Execution/DeferredOperationMessage.php`
- `src/Core/Execution/DeferredAcknowledgement.php`
- `src/Core/Execution/ClaimRequest.php`
- `src/Core/Execution/OperationClaim.php`
- `src/Core/Execution/OperationSender.php`
- `src/Core/Execution/OperationReceiver.php`
- `src/Core/Execution/ClaimHeartbeat.php`
- `src/Core/Execution/ClaimSettlement.php`
- `src/Core/Execution/ExecutionTransport.php`
- `src/Core/Exception/DeferredTransportException.php`
- `tests/Core/Execution/ExecutionStrategyTest.php`
- `tests/Core/Execution/DeferredTransportContractTest.php`
- `docs/internal/deferred-transport-contract.md`
- `develop/orchestration/tasks/P3-001-deferred-transport-contracts.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `ClaimRequest` はMVP最小としてClaim基準時刻だけを保持する。Lease Owner、Lease期限、Fencing Token等はTransport内部Metadataとして後続のPostgreSQL実装で扱う。
- `DeferredOperationMessage` はCodec済み文字列を受け取るContractとし、Canonical Codec自体はこのTaskでは実装しない。
- `operationType` は空文字を拒否し、`schemaVersion` は1以上を必須にした。
- `OperationClaim` のClaim Tokenは空文字を拒否し、Sensitive Parameterとして扱う。
- `DeferredTransportException` はPHPの言語制約によりreadonly classにしない。`RuntimeException` 継承のPublic Exceptionとして追加した。
- HTTP 202 Response変換、PostgreSQL Transport、Worker Runtime、Retry/Crash Recoveryは後続Taskへ分離した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'ExecutionStrategyTest|DeferredTransportContractTest'
Result: OK (22 tests, 59 assertions).

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (324 tests, 759 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 474 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] `Deferred` がPublic `ExecutionStrategy` として追加される
- [x] `DeferredOperationMessage` がOperation ID、Operation Type、Schema Version、Encoded Payload、Encoded Context、Available Atを保持する
- [x] `DeferredAcknowledgement` がOperation IDとAccepted Atを保持する
- [x] `ClaimRequest` と `OperationClaim` がPublic Contractとして追加される
- [x] `OperationSender`、`OperationReceiver`、`ClaimHeartbeat`、`ClaimSettlement`、`ExecutionTransport` が責務別Public Interfaceとして追加される
- [x] Transport失敗時のPublic Exceptionが追加される
- [x] Core ContractのUnit Testが追加される
- [x] Internals Documentationが更新される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- PostgreSQL Execution Transportは未実装。
- Deferred Strategy Dispatcherは未実装。
- HTTP 202 Response変換は未実装。
- Worker Runtime、Retry、Heartbeat実体、Crash Recovery、Dead Letterは未実装。
- Operation ManifestやOpenAPI生成のDeferred応答拡張は未実装。

## Suggested Next Action

PostgreSQL Deferred受付Store／Transportを実装し、Durable保存成功時に`DeferredAcknowledgement`を返せるようにする。
