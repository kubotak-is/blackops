# P1-025: Runtime Container Compile Command

Status: Accepted

## Goal

Service Provider Config fileを読み込み、Runtime ContainerをCompileしてProduction Runtime向けPHP fileへ出力する最小CLI Commandを追加する。

## In Scope

- Internal Runtime Container Compile Commandを追加する
- CommandがService Provider Config fileを読み込めるようにする
- CommandがRuntime Container CompilerへProvider群を適用できるようにする
- CommandがCompile済みContainerをPHP fileへDumpできるようにする
- DumpされたContainer fileを読み込み、PSR-11 ContainerとしてServiceを解決できることを検証する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Console Application本体へのCommand自動登録
- Composer Package自動Discovery
- Operation Manifestとの一括Build command
- Cache invalidation
- Multi-file dump
- Preload設定
- Service Tag DSL、Factory、Alias、Parameter、Scalar Bindingの詳細DSL

## Relevant Specifications

- `spec/09-runtime-and-di.md`
- `spec/12-mvp-scope.md`
- `spec/13-mvp-technical-stack.md`
- `spec/15-source-layout.md`
- `spec/16-namespace-dependencies.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `orchestration/tasks/P1-025-runtime-container-compile-command.md`
- `orchestration/reports/P1-025-runtime-container-compile-command.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- CommandはInternal実装とし、公開APIへInternal型を露出しない
- Public ContractへSymfony型を露出しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] CommandがProvider Config pathとOutput pathを受け取れる
- [x] CommandがContainer class名とnamespaceを指定できる
- [x] CommandがProvider Configを読み込みRuntime Containerへ適用できる
- [x] CommandがCompile済みContainerをPHP fileへDumpできる
- [x] DumpされたContainerをPSR-11 Containerとして利用できる
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

`orchestration/reports/P1-025-runtime-container-compile-command.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
