# P1-021: Runtime Container Compile Foundation

Status: Accepted

## Goal

Symfony DependencyInjection ContainerをBuild/Compileし、PSR-11 ContainerとしてFrameworkのRuntime境界へ渡せる最小実装を追加する。

## In Scope

- Symfony `ContainerBuilder` を作成・Compileする内部Compilerを追加する
- Compiled ContainerをPSR-11 Containerとして返す
- Constructor AutowiringでHandlerを解決できることを検証する
- Compile済みContainerへHTTP Manifest CLI Commandを登録できることを検証する
- Symfony DependencyInjectionをMago/DeptracのLibrary解決対象へ追加する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Public Service Provider API
- Config Loader
- Container PHP Dump File生成
- Production bootstrap
- Operation Discovery
- Operation Provider
- Worker Lifecycle Hook

## Relevant Specifications

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internal/**`
- `mago.toml`
- `deptrac.yaml`
- `develop/orchestration/tasks/P1-021-runtime-container-compile.md`
- `develop/orchestration/reports/P1-021-runtime-container-compile.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない
- CompilerはInternal実装とし、公開APIへInternal型を露出しない

## Acceptance Criteria

- [x] Symfony `ContainerBuilder` を作成できる
- [x] ContainerをCompileしPSR-11 Containerとして返せる
- [x] Constructor AutowiringでHandlerを解決できる
- [x] Compile済みContainerからHTTP Manifest CLI Commandを解決できる
- [x] Service LocatorをHandlerやEnvelopeへ渡さない構成である
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

`develop/orchestration/reports/P1-021-runtime-container-compile.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
