# P1-026: Operation Provider Boundary

Status: Accepted

## Goal

Composer PackageやApplicationが公開するOperation DefinitionをBuild時にManifest/Registry側へ渡せる、最小のOperation Provider境界を追加する。

## In Scope

- Public `OperationProvider` Contractを追加する
- Operation Provider群からOperation MetadataをCompileするInternal Compilerを追加する
- Operation Provider群から読み取り専用Operation Registryを構築できることを検証する
- 不正なOperation Definitionや重複Metadataを拒否できることを検証する
- Runtime/Registry Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Composer Package自動Discovery
- Config Loader
- File Scan、Composer Metadata Scan、Token Scan
- Operation Manifest PHP file formatの拡張
- HTTP Route Manifestとの一括Build
- Production bootstrap

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/17-core-api.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Core/**`
- `src/Internal/**`
- `tests/Core/**`
- `tests/Internal/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P1-026-operation-provider-boundary.md`
- `develop/orchestration/reports/P1-026-operation-provider-boundary.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public Contractへ `BlackOps\Internal` 型を露出しない
- Operation ProviderはService Instanceを生成しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Public `OperationProvider` Contractを実装できる
- [x] Operation ProviderがOperation Definition class-string群を返せる
- [x] Operation Provider群からOperation Registryを構築できる
- [x] Operation Provider経由のMetadataをType IDとDefinition Classで検索できる
- [x] 不正なOperation Definitionを拒否できる
- [x] 重複Type IDまたはDefinition Classを拒否できる
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

`develop/orchestration/reports/P1-026-operation-provider-boundary.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
