# P1-040: Production Runtime Smoke Scenario

Status: Accepted

## Goal

Build artifacts compileからProduction artifact loading、Runtime composition、HTTP request handlingまでのPhase 1導線をEnd-to-Endで検証する。

## In Scope

- Production Runtime Smoke Testを追加する
- Provider configからBuild artifactsを生成する
- 生成したOperation Manifest、HTTP Manifest、Runtime ContainerをProduction Runtime Artifact Loaderで読み込む
- Production Runtime ComposerでHTTP Request Handlerを構成する
- HTTP requestを処理し、Handler responseとLifecycle Journalを検証する
- Task Report、STATEを更新する

## Out of Scope

- Production Code変更
- Public API追加
- Deferred/Worker Runtime
- PostgreSQL接続Factory
- Environment variable loader
- Front-controller scriptの実装

## Relevant Specifications

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/12-mvp-scope.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `tests/Internal/**`
- `develop/orchestration/tasks/P1-040-production-runtime-smoke-scenario.md`
- `develop/orchestration/reports/P1-040-production-runtime-smoke-scenario.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Smoke TestはProduction Runtimeの現在のPhase 1導線だけを検証する
- Deferred/Worker/Retry/Retentionへ範囲を広げない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Provider configからBuild artifactsを生成できる
- [x] 生成ArtifactをProduction Runtime Artifact Loaderで読み込める
- [x] Production Runtime ComposerでHTTP Request Handlerを構成できる
- [x] HTTP requestがHandler responseを返す
- [x] Lifecycle JournalがInline正常系を記録する
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/P1-040-production-runtime-smoke-scenario.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
