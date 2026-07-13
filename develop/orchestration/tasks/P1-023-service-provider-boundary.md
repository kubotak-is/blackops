# P1-023: Service Provider Boundary

Status: Accepted

## Goal

ApplicationやPackageがRuntime Container BuildへService定義を登録できる、最小の公開Service Provider境界を追加する。

## In Scope

- Public `ServiceProvider` Contractを追加する
- Public `ServiceRegistry` Contractを追加する
- Internal Symfony AdapterでPublic Registry操作をSymfony `ContainerBuilder` へ反映する
- Runtime Container CompilerがService Provider群を適用できるようにする
- Unit Testを追加する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Config Loader
- Composer Package自動Discovery
- Operation Provider
- Service Tag DSL
- Factory、Alias、Parameter、Scalar Bindingの詳細DSL
- Production bootstrap

## Relevant Specifications

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Core/**`
- `src/Internal/**`
- `tests/Core/**`
- `tests/Internal/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-023-service-provider-boundary.md`
- `develop/orchestration/reports/P1-023-service-provider-boundary.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public Contractへ `BlackOps\Internal` 型を露出しない
- Public ContractへSymfony型を露出しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Public `ServiceProvider` Contractを実装できる
- [x] Public `ServiceRegistry` ContractからClass Autowiring Serviceを登録できる
- [x] Public `ServiceRegistry` ContractからObject Serviceを登録できる
- [x] Runtime Container CompilerがService Provider群を適用できる
- [x] Service Provider経由で登録したHandlerをCompile済みContainerから解決できる
- [x] Public ContractにInternal型やSymfony型を露出しない
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

`develop/orchestration/reports/P1-023-service-provider-boundary.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
