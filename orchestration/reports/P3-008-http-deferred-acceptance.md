# P3-008: HTTP Deferred Acceptance Report

Status: Accepted

## Summary

Routeを持つDeferred OperationをHTTPから受け付け、Operation CodecでMessage化し、Deferred Acceptance Orchestratorへ渡してHTTP 202 Acknowledgement Responseを返す経路を追加した。

HTTP層はDeferred受付Portへだけ依存し、Registry、Codec、PostgreSQL Transaction構成はInternal実装に閉じている。成功時はOperation IDとAccepted Atを含むJSONを返し、PostgreSQLにはOperation Stateと`operation.received` / `operation.accepted` Journalが保存される。

## Changed Files

- `src/Http/DeferredOperationAcceptor.php`
- `src/Http/OperationRequestHandler.php`
- `src/Http/Responder/JsonOperationResponder.php`
- `src/Internal/Http/DeferredHttpOperationAcceptor.php`
- `tests/Http/DeferredOperationRequestHandlerTest.php`
- `docs/internals/deferred-transport-contract.md`
- `orchestration/tasks/P3-008-http-deferred-acceptance.md`
- `orchestration/reports/P3-008-http-deferred-acceptance.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- HTTP層からInternal実装へ直接依存しないため、`BlackOps\Http\DeferredOperationAcceptor`をHTTP側Portとして追加した。
- 既定のDeferred Acknowledgement JSONは`status`、`operationId`、`acceptedAt`を含める。
- Operation Request HandlerはDeferred受付Portが対象Operationを受け付ける場合だけDeferred経路へ委譲し、それ以外は既存Dispatcher経路を維持する。
- Location Header、Outcome取得URL、OpenAPI / ManifestのDeferred応答拡張は後続Taskへ残した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'DeferredOperationRequestHandlerTest|OperationRequestHandlerTest'
Result: OK (11 tests, 43 assertions). Runtime PHP 8.5.7.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (342 tests, 858 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 600 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] HTTP Deferred受付Portが追加される
- [x] OperationRequestHandlerがDeferred Routeを受付Portへ委譲できる
- [x] Internal Deferred HTTP AcceptorがCodec済みDeferredOperationMessageを生成する
- [x] Deferred Acceptance Orchestratorへ受付が渡される
- [x] 成功時にHTTP 202 JSON Acknowledgementが返る
- [x] HTTP 202 BodyにOperation IDとAccepted Atが含まれる
- [x] PostgreSQLへOperation Stateと`operation.received` / `operation.accepted` Journalが保存される
- [x] Inline Routeの既存挙動が維持される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Worker Runtimeは未実装。
- Worker Claim / Heartbeat / Settlementは未実装。
- Deferred Operation Handler実行は未実装。
- Deferred Outcome取得APIは未実装。
- Location Header生成とOpenAPI / ManifestのDeferred応答拡張は未実装。

## Suggested Next Action

PostgreSQL TransportへWorker Claimを追加し、Accepted OperationをWorker Processが取得できるようにする。
