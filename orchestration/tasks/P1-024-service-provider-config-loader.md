# P1-024: Service Provider Config Loader

Status: Accepted

## Goal

Runtime Container Buildへ適用するService Provider群を、PHP Config fileから読み込める最小の内部Loaderを追加する。

## In Scope

- Service Provider Config fileを読み込むInternal Loaderを追加する
- Config fileから `ServiceProvider` instanceを読み込めるようにする
- Config fileから引数なし `ServiceProvider` class-stringを読み込めるようにする
- 読み込んだProvider群を既存Runtime Container Compilerへ適用できることを検証する
- 不正なConfig return value、Provider entry、未生成可能Providerを拒否する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Composer Package自動Discovery
- YAML、JSON、env専用Config Loader
- Service Tag DSL
- Factory、Alias、Parameter、Scalar Bindingの詳細DSL
- Production bootstrap command
- Application Skeletonの確定

## Relevant Specifications

- `spec/07-project-structure.md`
- `spec/09-runtime-and-di.md`
- `spec/15-source-layout.md`
- `spec/16-namespace-dependencies.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `orchestration/tasks/P1-024-service-provider-config-loader.md`
- `orchestration/reports/P1-024-service-provider-config-loader.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- LoaderはInternal実装とし、公開APIへInternal型を露出しない
- Public ContractへSymfony型を露出しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] PHP Config fileから `ServiceProvider` instanceを読み込める
- [x] PHP Config fileから引数なし `ServiceProvider` class-stringを読み込める
- [x] Config fileが単一Providerを返す場合も読み込める
- [x] 読み込んだProvider群をRuntime Container Compilerへ適用できる
- [x] Missing file、非Provider return value、不正entry、生成不能Providerを拒否する
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

`orchestration/reports/P1-024-service-provider-config-loader.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
