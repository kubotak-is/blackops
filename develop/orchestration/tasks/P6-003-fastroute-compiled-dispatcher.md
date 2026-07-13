# P6-003: FastRoute Compiled Dispatcher

Status: Accepted

## Goal

HTTP Manifest CompilerがFastRoute用Dispatcher DataをBuild時に生成してManifestへ保存し、Production RuntimeがそのCompile済みDataを使ってStatic / Dynamic Routeを解決するようにする。

## In Scope

- FastRoute 1.3を使うBuild時Route Compile
- Compile済みFastRoute Dispatcher DataのHTTP Manifest Payload格納
- HTTP Manifest Schema Versionの更新
- HTTP Manifest LoaderでDispatcher Dataを検証するFail Fast
- Runtime `HttpRouteRegistry`のFastRoute Dispatcher利用
- Dynamic Path ParameterのRoute Match返却
- Unknown RouteとMethod Not Allowedの既存HTTP 404互換
- 重複または競合RouteのCompile時拒否
- Manifest File、Build Command、Production Runtime Smoke Testの更新
- FastRoute Build / Runtime境界の内部Documentation更新

## Out of Scope

- Route Attributeの公開API変更
- 複数Route Attribute対応
- Middleware、Authorization、Responder Metadata追加
- Development Dynamic Discovery
- HTTP Method Not Allowed専用405 Response
- FastRoute Cache File機能の利用
- Operation Manifest Schema変更

## Relevant Specifications

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/008-http-routing-and-binding.md`
- `develop/decisions/012-operation-registry-and-manifest.md`
- `develop/decisions/018-mvp-technical-stack.md`
- `develop/decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Http/Routing/**`
- `src/Internal/Console/**`
- `src/Internal/Runtime/**`
- `mago.toml`
- `deptrac.yaml`
- `tests/Http/**`
- `tests/Internal/Console/**`
- `tests/Internal/Runtime/**`
- `docs/internal/http-api-slice.md`
- `docs/internal/runtime-container.md`
- `docs/guide/runtime-bootstrap.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-003-fastroute-compiled-dispatcher.md`
- `develop/orchestration/reports/P6-003-fastroute-compiled-dispatcher.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- ManifestにFastRoute ObjectやClosureを保存しない
- Runtime Request処理でAttribute探索やRoute再Compileを行わない
- Production Runtimeは不正Dispatcher Dataから動的Route構築へFallbackしない
- HTTP Manifest Payload Shape変更に合わせSchema Versionを更新する
- Operation / HTTP ManifestのApplication Build ID一致検証を維持する
- 既存のPath Parameter Bindingを維持する
- MagoとDeptracへFastRoute Library型の解析設定だけを追加し、既存Ruleを緩和しない

## Acceptance Criteria

- [x] HTTP Manifest CompilerがFastRoute Dispatcher Dataを生成する
- [x] HTTP ManifestへDispatcher DataがPHP配列として保存される
- [x] HTTP Manifest Loaderが欠落または不正なDispatcher Dataを拒否する
- [x] HTTP Manifest Schema VersionがPayload変更に合わせて更新される
- [x] Runtime Route MatchがCompile済みFastRoute Dispatcherを使用する
- [x] Static Route、Dynamic Route、Path Parameterが既存どおり解決される
- [x] Unknown RouteとMethod Not Allowedが既存どおりHTTP 404になる
- [x] 重複または競合RouteがBuild時に拒否される
- [x] Production Runtime Smoke TestがVersioned ManifestとFastRouteを通して成功する
- [x] FastRouteのBuild / Runtime責務がDocumentationへ記録される
- [x] MagoとDeptracがFastRoute Production型を解析し、既存Architecture Ruleを維持する
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'OperationRequestHandlerTest|HttpOperationManifestFileTest|CompileHttpManifestCommandTest|CompileBuildArtifactsCommandTest|ProductionRuntimeArtifactLoaderTest|ProductionRuntimeComposerTest|ProductionRuntimeSmokeTest'
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

`develop/orchestration/reports/P6-003-fastroute-compiled-dispatcher.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
