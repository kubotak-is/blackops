# P6-002: Public API Architecture Guard

Status: Accepted

## Goal

`#[PublicApi]`で宣言されたFramework APIのSignatureへ`BlackOps\Internal`型が露出しないこと、およびInternal型がPublic APIとして宣言されないことを、全Test実行時に自動検証するArchitecture Guardを追加する。

## In Scope

- `src/`配下のPHP型を対象にするPublic API Architecture Test
- `#[PublicApi]`型のPublic Constructor、Method、Property、継承型、実装Interfaceに現れる型の検査
- Union / Intersection / Nullable Typeの再帰検査
- `BlackOps\Internal`型への`#[PublicApi]`付与禁止
- Architecture Guard自体の正常系・違反検出Test
- DeptracとArchitecture Guardの責務を説明する内部Documentation
- 対応するTODOの完了状態更新

## Out of Scope

- 既存Public APIの追加・削除・改名
- Production CodeのNamespace移動
- `#[PublicApi]`未付与型を自動的にPublic APIとみなす規則
- PHPDoc型の解析
- SemVer / API Diff Tool導入
- CI Service設定Fileの新規追加

## Relevant Specifications

- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/17-core-api.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/decisions/021-source-layout.md`
- `develop/decisions/022-namespace-dependencies.md`
- `develop/decisions/023-core-api-shape.md`
- `develop/decisions/046-mvp-delivery-plan.md`

## Files Allowed to Change

- `tests/Architecture/**`
- `docs/internal/core-contracts.md`
- `docs/internal/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-002-public-api-architecture-guard.md`
- `develop/orchestration/reports/P6-002-public-api-architecture-guard.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Guardは通常の`vendor/bin/phpunit`で必ず実行される
- Source Fileの探索はRepositoryのPSR-4配置を前提とし、外部ProcessやNetworkへ依存しない
- Production CodeをArchitecture Test都合で変更しない
- Deptracの既存検査を置き換えない

## Acceptance Criteria

- [x] `src/`配下の全PHP型がArchitecture Guardの検査対象になる
- [x] `#[PublicApi]`型の公開Signatureへ`BlackOps\Internal`型が現れた場合にTestが失敗する
- [x] Union / Intersection / Nullable Type内のInternal型も検出される
- [x] `BlackOps\Internal`型へ`#[PublicApi]`が付与された場合にTestが失敗する
- [x] 現在のProduction CodeがArchitecture Guardを通過する
- [x] Deptracとの責務分担がDocumentationへ記録される
- [x] TODOのPublic API CI検証項目が完了へ更新される
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter PublicApiArchitecture
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

`develop/orchestration/reports/P6-002-public-api-architecture-guard.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
