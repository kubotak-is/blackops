# P1-038: Full Runtime Composition Wrapper

Status: Accepted

## Goal

Production Runtime ArtifactsからHTTP Request HandlerとInline Dispatcherを構成するInternal Composition Wrapperを追加する。

## In Scope

- Internal Production Runtime Composition Wrapperを追加する
- Production Runtime ArtifactsからHTTP Route Registryを構築できることを検証する
- Production Runtime Artifacts、PSR-20 Clock、Canonical Journal Writer、PSR-17 FactoryからInline Dispatcherを構成できることを検証する
- 構成したHTTP Request HandlerでOperationをDispatchし、Lifecycle Journalを書けることを検証する
- Runtime/Bootstrap Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Public API化
- HTTP front-controller script
- PostgreSQL接続Factory
- Environment variable loader
- Deferred/Worker Runtime Composition
- Transport Adapter自動選択

## Relevant Specifications

- `develop/spec/03-execution.md`
- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-038-full-runtime-composition-wrapper.md`
- `develop/orchestration/reports/P1-038-full-runtime-composition-wrapper.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Composition WrapperはInternal実装とし、公開APIへInternal型を露出しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない
- Production Runtimeでは生成済みArtifactを読み込み、動的ScanへFallbackしない

## Acceptance Criteria

- [x] Production Runtime ArtifactsからHTTP Route Registryを構築できる
- [x] Production Runtime ArtifactsからInline Dispatcherを構築できる
- [x] 構成したHTTP Request HandlerでOperationをDispatchできる
- [x] 構成したHTTP Request HandlerがLifecycle Journalを書ける
- [x] Runtime/Bootstrap Internals Documentationが更新される
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

`develop/orchestration/reports/P1-038-full-runtime-composition-wrapper.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
