# P6-009: FrankenPHP Reference Runtime Report

Status: Completed

## Summary

- Official FrankenPHP 1／PHP 8.5 Debian Imageを使うSeparate Reference Imageを追加し、PCNTL、PDO PostgreSQL、Zipを有効化した。
- Existing `app` Test／CLI Serviceを維持し、profile付き`http` ServiceとしてFrankenPHP／Caddyを追加した。
- `http` ServiceへPostgreSQL環境変数とHealthy Dependencyを設定し、Container内Port 80をHostの既定Port 8080へ公開した。
- Raw Superglobal入力からPSR-7 Server Requestを構成するInternal Adapterを追加した。Method、URI、Query、Cookie、Header、Protocol、Body、HTTPSを保持する。
- Application BootstrapからPSR-15 Handlerを取得するFront Controllerを追加し、Pathと戻り値をFail Fast検証した。
- Response Headerを全件事前検証してからStatus、複数Header値、BodyをSAPIへEmitし、HEAD Bodyを抑止するAdapterを追加した。
- Header Injection、部分送信、Body Stream無限Loopを防止し、Response Emit FailureをProcess境界へ伝播するようにした。
- Minimal `/healthz` BootstrapをActual FrankenPHP Containerで起動し、Existing `app` ServiceからHTTP 200 JSONを確認した。
- Runtime Build、Application Bootstrap差替え、Classic Mode、Long-running ProcessのReset要件をGuide／Internal Docsへ記録した。

## Changed Files

- `Dockerfile.frankenphp`
- `compose.yaml`
- `runtime/frankenphp/Caddyfile`
- `runtime/frankenphp/bootstrap.php`
- `runtime/frankenphp/public/index.php`
- `src/Internal/Runtime/FrankenPhp/FrankenPhpFrontController.php`
- `src/Internal/Runtime/FrankenPhp/SapiResponseEmitter.php`
- `src/Internal/Runtime/FrankenPhp/SapiResponseHeaders.php`
- `src/Internal/Runtime/FrankenPhp/SuperglobalRequestHeaders.php`
- `src/Internal/Runtime/FrankenPhp/SuperglobalRequestMetadata.php`
- `src/Internal/Runtime/FrankenPhp/SuperglobalRequestScheme.php`
- `src/Internal/Runtime/FrankenPhp/SuperglobalRequestUri.php`
- `src/Internal/Runtime/FrankenPhp/SuperglobalServerRequestFactory.php`
- `src/Internal/Runtime/FrankenPhp/SuperglobalServerValue.php`
- `tests/Internal/Runtime/FrankenPhp/FrankenPhpFrontControllerTest.php`
- `tests/Internal/Runtime/FrankenPhp/SapiResponseEmitterTest.php`
- `tests/Internal/Runtime/FrankenPhp/SuperglobalServerRequestFactoryTest.php`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/README.md`
- `docs/internals/bootstrap.md`
- `docs/internals/frankenphp-runtime.md`
- `TODO.md`
- `orchestration/tasks/P6-009-frankenphp-reference-runtime.md`
- `orchestration/reports/P6-009-frankenphp-reference-runtime.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Official Docker Guideが示すTag Pattern、PHP 8.5提供、Debian推奨に従い、`dunglas/frankenphp:1-php8.5-trixie`を採用した。実BuildでTag取得とExtension Compileに成功した。
- Reference Imageは`Dockerfile`のCLI Imageと分離し、Compose `runtime` ProfileなしのExisting Workflowへ影響させない。
- Composer Dependency Layerを先に`--no-autoloader`で作り、`src` Copy後に`composer dump-autoload --classmap-authoritative`を実行する。最終Buildでは1598 Classesを生成した。
- Base Imageの実起動引数が`/etc/frankenphp/Caddyfile`を読むことをImage InspectとLogで確認し、そのPathへReference設定を配置した。
- CaddyはDevelopment Smoke向けに明示Plain HTTP Port 80で起動する。Composeは`${BLACKOPS_HTTP_PORT:-8080}:80`を公開し、TLS／DomainはDeployment側の責務とする。
- `create()`はRaw Server ParametersをPSR Requestへ保持し、Field単位でRuntime-safeにStringへNarrowする。Non-string Server Parameterは失わず、Non-string HTTP Header値だけをHeader生成から除外する。
- Responseは全Header Name／Valueを送信前に検証する。Non-string Header KeyをCastせず拒否し、Header Injection時はStatus／Header／BodyのいずれもEmitしない。
- Readが空文字を返してEOFへ到達しないBody Streamは明示例外とし、Long-running Process内のBusy Loopを防止する。
- ReferenceはOfficial Migration Guideが安全な開始点とするClassic Modeを使用する。Worker Mode最適化は別のLifecycle検証なしに有効化しない。
- Application BootstrapはPSR-15 Handlerを返す。ContainerはCompositionで利用できるが、Handler引数やCore／Operation APIへ渡さない。

## Commands and Results

```text
docker compose --profile runtime build http
Result: Image blackops/framework-http:reference Built from dunglas/frankenphp:1-php8.5-trixie. Authoritative autoload contains 1598 classes.

docker compose --profile runtime up -d http
Result: PostgreSQL healthy; blackops-http-1 started.

docker compose run --rm app php -r '$body = file_get_contents("http://http/healthz"); exit(is_string($body) && str_contains($body, "\"status\":\"ok\"") ? 0 : 1);'
Result: Exit 0. Actual FrankenPHP /healthz returned JSON containing status ok.

docker compose stop http
Result: blackops-http-1 stopped.

docker compose run --rm app vendor/bin/phpunit --filter FrankenPhp
Result: OK (14 tests, 43 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (545 tests, 1690 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 297 files / Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1179 / Warnings 0 / Errors 0.

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches (negated command exited 0).

git diff --check
Result: No output.
```

Actual Smoke初回はCaddy設定を`/etc/caddy/Caddyfile`へ配置したため、Base Image既定の`/etc/frankenphp/Caddyfile`が使用されHTTPS Redirectとなった。Image InspectとContainer Logで実起動Pathを確認して修正した。

配置修正後のSmokeではCaddyを8080だけでListenしたため、Required Internal URLのDefault Port 80がConnection Refusedとなった。Caddyを単一Plain HTTP Port 80、Host Mappingを8080:80へ揃えて再実行し成功した。すべての失敗時を含め、Smoke後に`docker compose stop http`を実行した。

Mago初回実行ではRequest FactoryとEmitterのComplexity、およびSuperglobalのBroad Typeを検出した。URI、Scheme、Header、Metadata、Header Validationを小さなInternal Boundaryへ分離し、型をPHPDocで偽らずField単位のRuntime Narrowingへ修正した。最終Lint／AnalyzeはいずれもIssue 0となった。

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

## Remaining Issues

- なし。

## Suggested Next Action

- MVP残作業の次Task Packetへ進む。

## Orchestrator Review

- Official FrankenPHP 1／PHP 8.5 Debian ImageをSeparate Reference Serviceとして追加し、既存app Test Workflowを維持していることを確認した。
- Composer install後、Source Copyを経てauthoritative autoloadを再生成するBuild順序を確認した。
- Application BootstrapがPSR-15 Handlerだけを返し、Dynamic Discovery／CompileへFallbackしないFront Controller境界を確認した。
- Raw Server Parametersを保持しつつ、Method、URI、HeaderをField単位でRuntime-safeにNarrowすることをTestで確認した。
- 全Response Headerを部分送信前に検証し、複数Header、HEAD抑止、Body no-progress失敗をTestで確認した。
- `http` Serviceを再起動し、Actual `/healthz`が`{"status":"ok"}`を返すことを確認後、Serviceを停止した。
- Targeted PHPUnitを再実行し、`OK (14 tests, 43 assertions)`を確認した。
- Mago LintとDeptracを再実行し、Issues、Violations、Warnings、Errorsが0であることを確認した。
- Review指摘およびBlockerはない。
