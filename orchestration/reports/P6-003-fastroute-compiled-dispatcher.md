# P6-003: FastRoute Compiled Dispatcher Report

Status: Accepted

## Summary

- HTTP Manifest CompilerがFastRoute 1.3のGroupCountBased Dispatcher DataをBuild時に生成するようにした。
- HTTP Manifest PayloadへDispatcher DataをPHP配列として保存し、HTTP Manifest Schema Versionを`2`へ更新した。
- LoaderがStatic Route、Variable Route Chunk、Route Map、Variable Name、Operation Type ID参照を検証し、不正DataをProduction Container読込前に拒否するようにした。
- Runtime `HttpRouteRegistry`がManifestのCompile済みDispatcher DataをFastRouteへ直接渡し、Request処理中のAttribute探索とRoute再Compileを行わないようにした。
- FastRouteへ置き換えられ参照ゼロになった旧`HttpPathPattern`を削除し、Custom Path Matcherの置換を完了した。
- Static Route、Dynamic Route、URL decode済みPath Parameter、Unknown Route、Method Not Allowedの互換性をTestした。
- 重複Routeと同一regexへCompileされる競合Dynamic RouteをBuild時に拒否するようにした。
- Production Runtime Smoke TestをDynamic Routeへ変更し、Build、Manifest Load、FastRoute Match、Path Binding、Handler実行を一周させた。
- FastRouteのBuild / Runtime責務、Schema Version、Fail Fast条件を内部・利用者向けDocumentationへ記録した。

## Changed Files

- `src/Http/Routing/FastRouteDispatcherDataCompiler.php`
- `src/Http/Routing/HttpDispatcherDataCodec.php`
- `src/Http/Routing/HttpDispatcherStaticDataCodec.php`
- `src/Http/Routing/HttpDispatcherVariableDataCodec.php`
- `src/Http/Routing/HttpDispatcherVariableChunkCodec.php`
- `src/Http/Routing/HttpDispatcherRouteMapCodec.php`
- `src/Http/Routing/HttpDispatcherVariableNamesCodec.php`
- `src/Http/Routing/HttpManifestRouteHandlerSet.php`
- `src/Http/Routing/HttpOperationManifest.php`
- `src/Http/Routing/HttpOperationManifestArtifactCodec.php`
- `src/Http/Routing/HttpOperationManifestPayloadCodec.php`
- `src/Http/Routing/HttpPathPattern.php`（削除）
- `src/Http/Routing/HttpRouteCompiler.php`
- `src/Http/Routing/HttpRouteRegistry.php`
- `mago.toml`
- `deptrac.yaml`
- `tests/Http/HttpOperationManifestFileTest.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `tests/Internal/Console/CompileBuildArtifactsCommandTest.php`
- `tests/Internal/Console/CompileHttpManifestCommandTest.php`
- `tests/Internal/Runtime/ProductionRuntimeArtifactLoaderTest.php`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Internal/Runtime/ProductionRuntimeSmokeTest.php`
- `docs/internals/http-api-slice.md`
- `docs/internals/runtime-container.md`
- `docs/guide/runtime-bootstrap.md`
- `TODO.md`
- `orchestration/tasks/P6-003-fastroute-compiled-dispatcher.md`
- `orchestration/reports/P6-003-fastroute-compiled-dispatcher.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- FastRoute Dispatcher handlerにはOperation Type IDを保存する。Manifest Runtime RegistryはType IDを不変な`HttpOperationRoute`へ解決する。
- HTTP Manifestは既存のRoute MetadataとOperation Metadataを維持し、`dispatcherData`をPayloadへ追加する。Operation Manifest Schema Versionは変更しない。
- FastRouteのGroupCountBased Data ShapeをFramework側Codecで検証する。ManifestにFastRoute Object、Closure、Service Instanceは保存しない。
- Dispatcher Data内のHandler集合はRoute Metadataが参照するOperation Type ID集合と完全一致させる。欠落、余分なHandler、未知Operation参照、複数Route参照を拒否する。
- FastRouteのMethod Not Allowed結果は既存`OperationRequestHandler` Contractに合わせて未一致とし、HTTP 404を返す。HTTP 405は追加しない。
- FastRouteが返すPath Parameterは既存挙動を維持するため`rawurldecode()`してBinderへ渡す。
- `HttpRouteRegistry`へRouteだけを直接渡すDevelopment／Test経路ではConstructor時にFastRoute Dataを生成できる。Production Compositionは常にManifestから渡されたCompile済みDataを使用する。
- Variable Routeの同一regex競合とStatic Routeの重複はFastRoute Compilerまたは事前のmethod/path検査でBuild時に拒否する。

## Initial Blocker and Scope Expansion

- 最初のFastRoute Production型追加後、`mago analyze`はFastRoute Vendor Sourceが解析対象外のため15 errors / 12 warningsで失敗した。
- 同時点のDeptracはFastRoute NamespaceがLibrary Layer対象外のためUncovered 7になった。
- `mago.toml`と`deptrac.yaml`は当初のFiles Allowed外だったため、Production実装を停止してReportとCheckpointへBlockerを記録した。
- Orchestrator CodexがTask Packetへ両Fileを追加し、FastRoute型解析設定だけを許可したため再開した。
- `mago.toml`へ`vendor/nikic/fast-route/src`を追加し、`deptrac.yaml`のLibrary CollectorへFastRoute Namespaceを追加した。既存Ruleは変更または緩和していない。
- Scope拡張後の最終MagoとDeptracは成功した。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'OperationRequestHandlerTest|HttpOperationManifestFileTest|CompileHttpManifestCommandTest|CompileBuildArtifactsCommandTest|ProductionRuntimeArtifactLoaderTest|ProductionRuntimeComposerTest|ProductionRuntimeSmokeTest'
Result: OK (50 tests, 133 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (471 tests, 1427 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1056 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

実装途中の初回Mago Lintでは、Dispatcher Data検証を2 Classへ集中したため3 errors / 7 warningsとなった。Static Data、Variable Data、Chunk、Route Map、Variable Name、Manifest Route参照へ責務分離し、曖昧な`isset`を明示的なkey存在検査へ変更した後、最終Lintは成功した。

Orchestrator Reviewで、FastRouteへの置換後に`HttpPathPattern`が参照ゼロのdead codeとして残っているとの指摘を受けた。Fileを削除後、targeted PHPUnitは50 tests / 133 assertions、Mago Lint / Analyze、Deptrac、`git diff --check`はすべて再成功した。`HttpPathPattern`への残存参照はない。

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

## Remaining Issues

- なし。

## Suggested Next Action

- MVP仕様のDevelopment Dynamic Discoveryを実装するTask Packetを作成する。

## Orchestrator Review

- Task Packetで許可されたFileだけが変更され、FastRoute型解析のための設定変更がLibrary認識追加だけであることを確認した。
- Dispatcher handlerがOperation Type IDであり、Manifest Route Metadataの参照集合と一致検証されることを確認した。
- Production RuntimeがManifestのCompile済みDataを直接利用し、Request処理時にAttribute探索やRoute再Compileを行わないことを確認した。
- Static / Dynamic Route、URL decode済みPath Parameter、404互換、競合拒否、Dynamic Production SmokeのTestを確認した。
- 旧自前Matcherの`HttpPathPattern`が参照ゼロで残っていたため削除を指摘し、workerの修正と再検証を確認した。
- Targeted PHPUnitを再実行し、`OK (50 tests, 133 assertions)`を確認した。
- Mago LintとDeptracを再実行し、問題がないことを確認した。
- 管理番号Comment検査、残存`HttpPathPattern`参照検査、`git diff --check`が成功することを確認した。
- Review指摘対応後のBlockerはない。
