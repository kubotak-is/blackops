# P6-009: FrankenPHP Reference Runtime

Status: Accepted

## Goal

公式Reference HTTP RuntimeとしてFrankenPHP + PHP 8.5のContainer、Caddy設定、PSR-15 Front Controller境界を追加し、Applicationが構成した`RequestHandlerInterface`を実HTTP Requestから実行できる最小Runtimeを提供する。

## In Scope

- 公式FrankenPHP PHP 8.5 Debian Imageを使うReference Dockerfile
- PostgreSQL、Zip、PCNTL等のMVP Runtime Extension
- Caddy／FrankenPHP設定とDocker Compose `http` Service
- Application Bootstrap FileからPSR-15 `RequestHandlerInterface`を取得するFront Controller
- SuperglobalからPSR-7 ServerRequestを生成するInternal FrankenPHP Adapter
- PSR-7 ResponseのStatus、Header、BodyをSAPIへEmitするInternal Adapter
- GET／POST JSON Body、Query、Cookie、Header、HTTPS判定、HEAD Body抑止のTest
- Reference Bootstrapによる`/healthz` Smoke Endpoint
- Application Bootstrap戻り値、Path、Response Emit失敗のFail Fast
- Actual FrankenPHP Container起動とHTTP Smoke Test
- Reference RuntimeのBuild、起動、Application接続、長期Process注意点のDocumentation

## Out of Scope

- Application固有Operation／Handler
- Generated Front Controller
- Authentication／Authorization
- Multipart Upload
- TLS証明書／Production Domain設定
- Kubernetes／Cloud固有Deployment
- FrankenPHP Worker Mode最適化
- Core／Operation Public API変更
- Deferred Worker Processの同一Container統合

## Relevant Specifications and Decisions

- `develop/spec/12-mvp-scope.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/decisions/018-mvp-technical-stack.md`
- `develop/decisions/058-frankenphp-runtime-premise.md`
- Official Docker guide: `https://frankenphp.dev/docs/docker/`
- Official migration guide: `https://frankenphp.dev/docs/migrate/`

## Files Allowed to Change

- `Dockerfile.frankenphp`
- `compose.yaml`
- `runtime/frankenphp/**`
- `src/Internal/Runtime/FrankenPhp/**`
- `tests/Internal/Runtime/FrankenPhp/**`
- `docs/guide/runtime-bootstrap.md`
- `docs/internal/bootstrap.md`
- `docs/internal/README.md`
- `docs/internal/frankenphp-runtime.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-009-frankenphp-reference-runtime.md`
- `develop/orchestration/reports/P6-009-frankenphp-reference-runtime.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- FrankenPHP固有型やFunctionをCore／Operation Public APIへ露出しない
- Front ControllerはApplication BootstrapからPSR-15 Handlerを取得し、ContainerをHandlerへ渡さない
- Production startup時にDynamic Discovery／CompileへFallbackしない
- Request BodyをLogまたはError Messageへ含めない
- Response Headerは複数値を失わずEmitし、HEADではBodyを出力しない
- Header Injectionを許さない
- Reference ImageはPHP 8.5系とFrankenPHP 1系を明示する
- Existing `app` Test／CLI ServiceとPostgreSQL Test Workflowを壊さない
- Actual HTTP Smoke Test終了後にReference Containerを停止する

## Acceptance Criteria

- [x] FrankenPHP 1 + PHP 8.5のReference ImageをBuildできる
- [x] `http` ServiceがCaddy／FrankenPHPで起動する
- [x] Front ControllerがApplication BootstrapのPSR-15 Handlerを実行する
- [x] Superglobal相当入力からMethod、URI、Query、Cookie、Header、Bodyを保持したPSR-7 Requestを生成する
- [x] HTTPS情報をPSR-7 URIへ反映する
- [x] PSR-7 ResponseのStatus、複数Header、BodyをEmitする
- [x] HEAD ResponseはBodyをEmitしない
- [x] 不正Bootstrap戻り値を明示的に拒否する
- [x] `/healthz`がActual Container経由でHTTP 200 JSONを返す
- [x] Existing app ServiceのFull Test／品質Commandが成功する
- [x] Runtime接続方法とFrankenPHP固有境界がDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose --profile runtime build http
docker compose --profile runtime up -d http
docker compose run --rm app php -r '$body = file_get_contents("http://http/healthz"); exit(is_string($body) && str_contains($body, "\"status\":\"ok\"") ? 0 : 1);'
docker compose stop http
docker compose run --rm app vendor/bin/phpunit --filter FrankenPhp
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-009-frankenphp-reference-runtime.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
