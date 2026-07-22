# P18-009B: Framework-owned SAPI Runtime

Status: Ready

## Goal

Public `BlackOps\Http\SapiRuntime`へClassic SAPIとFrankenPHP Worker ModeのRequest生成、Response Emit、Safe 500、Environment Restore、Request後Cleanup／GCを集約する。Quickstart／Community Board EntrypointをApplication Instanceを渡すだけの薄いCodeへ移行し、既存`Application::http()` Escape Hatchを維持する。

## In Scope

- Public `SapiRuntime::run(Application): void`／`runWorker(Application): void`
- Framework-owned PSR-17／Server Request Creator／SAPI Emitter
- Classic一回実行と固定Safe JSON 500
- Worker Loop、Failure後継続、`$_ENV` Restore、Framework Cleanup、GC
- Header送信済み／Emit Failure／FrankenPHP Function不在のSafe Failure
- Throwable／Credential／Request Body／Header Value非露出
- `Application::http()` Custom Adapter／Test／Outer Router互換
- Quickstart／Community Board Classic／Worker Entrypoint移行
- Multi-request、Exception Recovery、Connection／Observation／Execution Cleanup、Memory／Restart Consumer
- Public API、Architecture、Unit／Integration／Consumer Test
- Specification、Report、STATE同期

## Out of Scope

- Environment File Public Contractの変更
- Public UUIDv7 Generator、Auth Generator
- Composer Direct Dependency削除、Skeleton／Distribution／Website Closeout
- Authentication、CORS、Application Error Projection
- DBAL Wrapper、Phase 19 Idempotency／Outbox
- External Publication／Deploy

## Relevant Specifications

- `develop/decisions/085-http-configuration-snapshot-lifecycle.md`
- `develop/decisions/114-application-runtime-and-bootstrap-dependency-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/71-full-stack-reference-application.md`
- `develop/spec/78-application-runtime-and-bootstrap.md`
- `develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/orchestration/reports/P18-009A-environment-file-bootstrap.md`

## Files Allowed to Change

- `src/Http/SapiRuntime.php`
- SAPI Adapter／Safe Failure／Worker Loopに必要な`src/Internal/Http/**`、`src/Internal/Application/**`の最小差分
- `composer.json`、`composer.lock`
- 対応する`tests/Http/**`、`tests/Internal/**`、`tests/Architecture/**`、`tests/Consumer/**`とPermanent Fixture
- `examples/quickstart/public/index.php`、`examples/quickstart/public/worker.php`
- `examples/community-board/public/index.php`、`examples/community-board/public/worker.php`、Runtime Consumer回帰の最小差分
- Public API Inventory、Architecture設定
- `develop/spec/43-installed-application-layout-and-bootstrap.md`、`develop/spec/44-public-application-bootstrap-api.md`、`develop/spec/47-public-http-runtime-configuration.md`、`develop/spec/51-local-runtime-and-consumer-e2e.md`、`develop/spec/71-full-stack-reference-application.md`、`develop/spec/78-application-runtime-and-bootstrap.md`、`develop/spec/79-phase-18-runtime-follow-up-delivery-plan.md`
- `docs/internal/frankenphp-runtime.md`とRuntime実装者向け文書の最小同期
- `develop/TODO.md`、`develop/STATE.md`、`develop/orchestration/reports/P18-009B-framework-owned-sapi-runtime.md`

UUID Production Code、`resources/stubs/**`、Application Composer Dependency削除、Skeleton Distribution、`docs/guide/**`、`docs/website/**`は変更禁止とする。Public Contract不足はScopeを広げずReportへ返す。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- WorkerはCommitしない
- `Application::http()`を削除、非推奨化、Signature変更しない
- Raw Throwable Message／Trace、Credential、Request Body、Header ValueをResponse／Logへ出さない
- Worker一RequestのFailureでLoopを終了しない
- Application Serviceの任意State Resetを推測しない
- Migration、Build、Authentication、CORSを暗黙実行しない

## Acceptance Criteria

- [ ] Public `SapiRuntime`がClassic／Workerの2 Methodだけを提供する
- [ ] Default EntrypointがNyholm／Laminas／Worker Loopを直接Import／実装しない
- [ ] Classic Success／Request Failure／Handler Failure／Emit FailureがSafe Contractを満たす
- [ ] Worker複数Request、例外後Request、Environment Restore、Cleanup、GCが成功する
- [ ] Safe 500が固定JSONだけを返しSensitive／Raw Throwableを露出しない
- [ ] `Application::http()` Custom Adapter／Test／Community Board Outer Boundaryが回帰しない
- [ ] Quickstart／Community Board Classic／Worker Consumerが成功する
- [ ] Full PHPUnit、Mago、Deptrac、Composer Strict、Public API／Management ID／diff Guardが成功する
- [ ] UUID／Dependency削除／Distribution／外部Publication差分なし、Worker Commitなし

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/community-board/app examples/community-board/tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict --working-dir=examples/quickstart
docker compose run --rm app composer validate --strict --working-dir=examples/community-board
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart examples/community-board --glob '*.php'
git diff --check
```

Classic／Worker／Memory／Community Board Runtime CommandはRepository内Scriptと直前Reportから列挙し、実行結果をReportへ記録する。

## Expected Report

`develop/orchestration/reports/P18-009B-framework-owned-sapi-runtime.md`へAGENTS.mdの必須Sectionに加え、次を記録する。

- Final Public APIとInternal Adapter Boundary
- Classic／Worker Success／Failure／Emit Matrix
- Environment／Execution／Observation／Connection Cleanup Evidence
- Quickstart／Community Board Multi-request／Memory／Restart Evidence
- `Application::http()`互換Evidence
- Commandsと実結果、未実行理由、Remaining Issue
