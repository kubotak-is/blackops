# P1-022: Runtime Container PHP Dump

Status: Accepted

## Goal

Compile済みSymfony ContainerをProduction Runtime向けPHP fileとして出力し、読み込んでPSR-11 Containerとして利用できる最小実装を追加する。

## In Scope

- Symfony `PhpDumper` を使う内部Dumperを追加する
- Dump出力は同一Directory内の一時Fileへ書き、atomic renameで完了する
- DumpされたPHP fileを読み込んでContainer Classを生成できることを検証する
- DumpされたContainerをHandler Resolverで利用できることを検証する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Public Service Provider API
- Config Loader
- Production bootstrap
- Cache invalidation
- Multi-file dump
- Preload設定

## Relevant Specifications

- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P1-022-runtime-container-dump.md`
- `develop/orchestration/reports/P1-022-runtime-container-dump.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- DumperはInternal実装とし、公開APIへInternal型を露出しない
- Multi-file dumpは扱わない

## Acceptance Criteria

- [x] Compile済みContainerをPHP fileへ出力できる
- [x] 出力はatomic renameで完了する
- [x] Dump fileを読み込んでContainer Classを生成できる
- [x] DumpされたContainerをPSR-11 Containerとして扱える
- [x] DumpされたContainer経由でHandlerを解決できる
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

`develop/orchestration/reports/P1-022-runtime-container-dump.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
