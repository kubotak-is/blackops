# P2-006: Execution Scope Provider

Status: Accepted

## Goal

Operation実行境界のcurrent contextをLogger decoratorから参照できるように、Internal Execution Scope Providerを追加する。

## In Scope

- Internal Execution Scope Providerを追加する
- Scopeをstackとして管理し、nested scope後に親scopeを復元する
- 例外発生時も `finally` でscopeを終了する
- Inline DispatcherのHandler実行境界でscopeを開始・終了する
- Handler実行中にcurrent OperationEnvelopeを参照できることを検証する
- Execution Context Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- PSR-3 Logger decorator実装
- Logger context自動付与
- Fiber-local scope分離
- OpenTelemetry Context propagation
- Runtime ComposerからLoggerへScope Providerを配線する入口

## Relevant Specifications

- `spec/10-logging-and-traceability.md`
- `spec/19-execution-context-api.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Execution/**`
- `tests/Internal/Execution/**`
- `docs/internals/**`
- `orchestration/tasks/P2-006-execution-scope-provider.md`
- `orchestration/reports/P2-006-execution-scope-provider.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- Scopeはstackとして管理し、nested実行後に親scopeを復元する
- Handler例外時にもscopeを終了する

## Acceptance Criteria

- [x] Internal Execution Scope Providerが追加される
- [x] scope外ではcurrentがnullである
- [x] scope内ではcurrent OperationEnvelopeを参照できる
- [x] nested scope後に親scopeが復元される
- [x] 例外発生時にもscopeが終了する
- [x] Inline DispatcherのHandler実行中にcurrent OperationEnvelopeを参照できる
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

`orchestration/reports/P2-006-execution-scope-provider.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
