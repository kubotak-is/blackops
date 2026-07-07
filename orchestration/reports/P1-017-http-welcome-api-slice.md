# P1-017: HTTP Welcome API Slice - Implementation Report

Status: Accepted

## Summary

HTTP `GET /welcome` のAPI-only Vertical Sliceを実装した。PSR-7 RequestをOperationへBindingし、Inline DispatcherでHandlerを実行し、ResponderでHTTP Responseを返す。さらに同じHTTP経由実行でPostgreSQL Canonical JournalへCompleted Lifecycle 4 Eventが保存されることを統合Testで確認した。

## Changed Files

- `src/Http/Attribute/Route.php` (add): Operation Definition用HTTP Route Attribute。
- `src/Http/Binding/OperationValueBinder.php` (add): 空ConstructorまたはQuery ParameterからOperationValueを生成する最小Binder。
- `src/Http/Routing/HttpOperationRoute.php` (add): HTTP RouteとOperationの対応。
- `src/Http/Routing/HttpRouteRegistry.php` (add): Method + Pathのexact match Registry。
- `src/Http/Routing/HttpRouteCompiler.php` (add): `#[Route]` AttributeからRoute Registryを作る最小Compiler。
- `src/Http/Responder/JsonOperationResponder.php` (add): Completed／Rejected ResultをAPI-only Responseへ変換。
- `src/Http/OperationRequestHandler.php` (add): PSR-15 Request Handler。
- `tests/Http/OperationRequestHandlerTest.php` (add): `/welcome` Response、204、Rejected JSON、GET body拒否、404、DB Journal統合を検証。
- `docs/internals/http-api-slice.md` (add): HTTP API Sliceの責務と制限を記録。
- `docs/internals/README.md` (edit): HTTP API Slice文書へのリンクを追加。
- `deptrac.yaml` (edit): HTTP層からPSR HTTP Libraryへの依存を許可。
- `mago.toml` (edit): PSR HTTP関連Vendor型解決を追加。
- `orchestration/tasks/P1-017-http-welcome-api-slice.md` (add): Task Packet。
- `orchestration/STATE.md` (edit): P1-017進行・完了状態へ更新。

## Decisions and Assumptions

- D047が未決のため、HTML RenderingやFrontend Client Contractには踏み込まず、API-only JSON/204 Responseに限定した。
- Route matchingは最初のVertical Slice用にMethod + Pathの完全一致のみとした。Dynamic Path ParameterとFastRoute統合は後続Taskで扱う。
- Binderは空ConstructorまたはQuery Parameterの同名Bindingだけを扱う。Path/Header/Body Attributeは後続Taskで追加する。
- Runtime DI Container本体はOut of Scopeとし、統合Test内でInlineDispatcherとPostgreSQL Storeを明示的に組み立てた。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (191 tests, 465 assertions)。Runtime PHP 8.5.7。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 252 / Warnings 0 / Errors 0。

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] `#[Route(method: 'GET', path: '/welcome')]` をOperationへ付与できる
- [x] `GET /welcome` がOperationValueへBindingされる
- [x] HandlerのCompleted ResultがHTTP 200 JSONになる
- [x] EmptyOutcomeはHTTP 204になる
- [x] Rejected Resultが安定したJSON Errorになる
- [x] `GET /welcome` 実行後、PostgreSQL JournalへCompleted Lifecycle 4 Eventが保存される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Remaining Issues

- Dynamic Path Parameter、Path/Header/Body Attribute Bindingは未実装。
- Operation Manifest CLIとFastRoute統合は未実装。
- Runtime DI Container Compileは未実装。
- Deferred HTTP 202、Authentication／Middlewareは未実装。

## Suggested Next Action

HTTP Binding AttributeとRoute Manifestの土台を追加し、`GET /welcome` の実装を動的探索からManifest相当の構成へ近づける。

## Codex Review

Accepted at `2026-07-08T01:56:59+09:00`。
